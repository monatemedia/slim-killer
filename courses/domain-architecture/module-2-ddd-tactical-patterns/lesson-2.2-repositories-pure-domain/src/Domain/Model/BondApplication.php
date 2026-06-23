<?php

declare(strict_types=1);

namespace Bond\Domain\Model;

use Bond\Domain\ValueObject\ApplicationId;
use Bond\Domain\ValueObject\Money;
use DomainException;

/**
 * BondApplication — the aggregate root from Lesson 2.1, plus ONE new capability that
 * repositories need: reconstitution.
 *
 * `start()` creates a brand-new application and runs the normal workflow (Draft, then
 * submit(), etc.). But when a repository loads an application back from storage, the
 * workflow has already happened — it must rebuild the object directly in whatever state
 * was saved, WITHOUT re-running transitions. That is what `reconstitute()` is for.
 * It is the one factory infrastructure is allowed to call; application code uses start().
 */
final class BondApplication
{
    /** @var list<IncomeSource> */
    private array $incomeSources = [];

    private ApplicationStatus $status = ApplicationStatus::Draft;

    private function __construct(
        private readonly ApplicationId $id,
        private readonly Money $requestedAmount,
    ) {
        if ($requestedAmount->cents <= 0) {
            throw new DomainException('A bond application must request a positive amount.');
        }
    }

    /** Application workflow: a fresh Draft with a domain-minted identity. */
    public static function start(Money $requestedAmount): self
    {
        return new self(ApplicationId::generate(), $requestedAmount);
    }

    /**
     * Infrastructure-only factory: rebuild an aggregate exactly as it was stored.
     * It trusts the data (storage already held a valid aggregate) and bypasses the
     * lifecycle guards on purpose.
     *
     * @param list<IncomeSource> $incomeSources
     */
    public static function reconstitute(
        ApplicationId $id,
        Money $requestedAmount,
        ApplicationStatus $status,
        array $incomeSources,
    ): self {
        $application = new self($id, $requestedAmount);
        $application->status = $status;
        $application->incomeSources = array_values($incomeSources);

        return $application;
    }

    public function id(): ApplicationId
    {
        return $this->id;
    }

    public function status(): ApplicationStatus
    {
        return $this->status;
    }

    public function requestedAmount(): Money
    {
        return $this->requestedAmount;
    }

    /** @return list<IncomeSource> */
    public function incomeSources(): array
    {
        return $this->incomeSources;
    }

    public function incomeSourceCount(): int
    {
        return count($this->incomeSources);
    }

    public function totalMonthlyIncome(): Money
    {
        return array_reduce(
            $this->incomeSources,
            fn (Money $carry, IncomeSource $source) => $carry->add($source->monthlyAmount()),
            Money::zero($this->requestedAmount->currency),
        );
    }

    public function addIncomeSource(IncomeSource $source): void
    {
        $this->guardIsDraft('add income to');

        if (! $source->monthlyAmount()->hasSameCurrencyAs($this->requestedAmount)) {
            throw new DomainException('Income must be declared in the same currency as the requested bond.');
        }

        $this->incomeSources[] = $source;
    }

    public function submit(): void
    {
        $this->guardIsDraft('submit');

        if ($this->incomeSources === []) {
            throw new DomainException('Cannot submit a bond application with no declared income.');
        }

        $this->status = ApplicationStatus::Submitted;
    }

    /** Entities are equal by identity (Lesson 1.3). */
    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }

    private function guardIsDraft(string $action): void
    {
        if ($this->status !== ApplicationStatus::Draft) {
            throw new DomainException("Cannot {$action} an application once it is {$this->status->value}.");
        }
    }
}
