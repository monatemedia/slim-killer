<?php

declare(strict_types=1);

namespace Bond\Infrastructure\Persistence;

use Bond\Domain\Model\ApplicationStatus;
use Bond\Domain\Model\BondApplication;
use Bond\Domain\Model\IncomeSource;
use Bond\Domain\Repository\BondApplicationRepository;
use Bond\Domain\ValueObject\ApplicationId;
use Bond\Domain\ValueObject\Currency;
use Bond\Domain\ValueObject\Money;
use PDO;

/**
 * SqliteBondApplicationRepository — a REAL database-backed implementation (raw PDO).
 *
 * In the production Slim Killer app this class would be PixieBondApplicationRepository
 * living in src/Infrastructure/Persistence/Bond/, using the injected Pixie handle. We use
 * raw PDO + SQLite here so the lesson runs with zero framework loaded — but the JOB is
 * identical and it is the whole point of the repository pattern: this is the ONE place
 * allowed to know about tables, columns, SQL, and the Money<->DECIMAL conversion. None of
 * that knowledge leaks across the interface into the domain.
 *
 * Note the mapping direction in both methods:
 *   save()  : aggregate  ->  rows   (Money cents -> "1250000.50" DECIMAL string)
 *   ofId()  : rows       ->  aggregate (DECIMAL string -> Money, via reconstitute())
 */
final class SqliteBondApplicationRepository implements BondApplicationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /** Create the schema. (In the real app this is a migration, not the repository's job.) */
    public static function migrate(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS applications (
            id          TEXT PRIMARY KEY,
            bond_amount DECIMAL(15,2) NOT NULL,
            currency    TEXT NOT NULL,
            status      TEXT NOT NULL
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS income_sources (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            application_id TEXT NOT NULL,
            employer       TEXT NOT NULL,
            monthly_amount DECIMAL(15,2) NOT NULL
        )');
    }

    public function save(BondApplication $application): void
    {
        $this->pdo->beginTransaction();

        try {
            // Upsert the application row. Domain Money -> DECIMAL string happens HERE.
            $stmt = $this->pdo->prepare(
                'INSERT INTO applications (id, bond_amount, currency, status)
                 VALUES (:id, :amount, :currency, :status)
                 ON CONFLICT(id) DO UPDATE SET
                     bond_amount = excluded.bond_amount,
                     currency    = excluded.currency,
                     status      = excluded.status'
            );
            $stmt->execute([
                ':id'       => $application->id()->value,
                ':amount'   => $application->requestedAmount()->toDecimalString(),
                ':currency' => $application->requestedAmount()->currency->value,
                ':status'   => $application->status()->value,
            ]);

            // The aggregate owns its line items: replace the whole set as one unit.
            $this->pdo->prepare('DELETE FROM income_sources WHERE application_id = :id')
                ->execute([':id' => $application->id()->value]);

            $insert = $this->pdo->prepare(
                'INSERT INTO income_sources (application_id, employer, monthly_amount)
                 VALUES (:app, :employer, :amount)'
            );
            foreach ($application->incomeSources() as $source) {
                $insert->execute([
                    ':app'      => $application->id()->value,
                    ':employer' => $source->employer(),
                    ':amount'   => $source->monthlyAmount()->toDecimalString(),
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function ofId(ApplicationId $id): ?BondApplication
    {
        $row = $this->pdo
            ->prepare('SELECT id, bond_amount, currency, status FROM applications WHERE id = :id');
        $row->execute([':id' => $id->value]);
        $appRow = $row->fetch(PDO::FETCH_ASSOC);

        if ($appRow === false) {
            return null;
        }

        $currency = Currency::from($appRow['currency']);

        $sourcesStmt = $this->pdo->prepare(
            'SELECT employer, monthly_amount FROM income_sources WHERE application_id = :id ORDER BY id'
        );
        $sourcesStmt->execute([':id' => $id->value]);

        $incomeSources = [];
        foreach ($sourcesStmt->fetchAll(PDO::FETCH_ASSOC) as $sourceRow) {
            $incomeSources[] = new IncomeSource(
                $sourceRow['employer'],
                $this->decimalToMoney($sourceRow['monthly_amount'], $currency),
            );
        }

        // rows -> aggregate. reconstitute() rebuilds the exact stored state.
        return BondApplication::reconstitute(
            new ApplicationId($appRow['id']),
            $this->decimalToMoney($appRow['bond_amount'], $currency),
            ApplicationStatus::from($appRow['status']),
            $incomeSources,
        );
    }

    /**
     * Convert a stored amount back into Money. The parameter is deliberately wide: a
     * DECIMAL column can come back as a string (MySQL/PDO) OR as an int/float (SQLite's
     * NUMERIC affinity collapses "2000000.00" to 2000000). Normalising that storage
     * quirk is exactly the repository's job — the domain must never see it.
     */
    private function decimalToMoney(int|float|string $decimal, Currency $currency): Money
    {
        return new Money((int) round(((float) $decimal) * 100), $currency);
    }
}
