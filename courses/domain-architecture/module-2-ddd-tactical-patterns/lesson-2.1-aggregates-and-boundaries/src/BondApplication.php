<?php

declare(strict_types=1);

namespace Bond\Model;

use Bond\ValueObject\ApplicationId;
use Bond\ValueObject\Money;
use DomainException;

/**
 * BondApplication — the AGGREGATE ROOT.
 *
 * An aggregate is a cluster of objects (here: the application plus its IncomeSource
 * line items) treated as a single unit for data changes. The ROOT is the only object
 * outside code is allowed to hold a reference to, and the only door through which the
 * cluster may be modified. This gives us a CONSISTENCY BOUNDARY: every change passes
 * through a method that can enforce the application's invariants.
 *
 * The boundary rules enforced here:
 *   - Income may only be added/removed while the application is a Draft.
 *   - An income source must be in the same currency as the requested bond.
 *   - An application cannot be submitted with no declared income.
 *   - The internal collection is never handed out for outside mutation.
 */
final class BondApplication
{
    /** @var list<IncomeSource> */
    private array $incomeSources = [];

    private ApplicationStatus $status = ApplicationStatus::Draft;

    public function __construct(
        private readonly ApplicationId $id,
        private readonly Money $requestedAmount,
    ) {
        if ($requestedAmount->cents <= 0) {
            throw new DomainException('A bond application must request a positive amount.');
        }
    }

    public static function start(Money $requestedAmount): self
    {
        return new self(ApplicationId::generate(), $requestedAmount);
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

    /**
     * THE DOOR. The only way to add a line item — and it enforces invariants on the way in.
     */
    public function addIncomeSource(IncomeSource $source): void
    {
        $this->guardIsDraft('add income to');

        if (! $source->monthlyAmount()->hasSameCurrencyAs($this->requestedAmount)) {
            throw new DomainException(
                'Income must be declared in the same currency as the requested bond.'
            );
        }

        $this->incomeSources[] = $source;
    }

    public function removeIncomeSource(IncomeSource $source): void
    {
        $this->guardIsDraft('remove income from');

        $this->incomeSources = array_values(array_filter(
            $this->incomeSources,
            fn (IncomeSource $existing) => ! $existing->equals($source),
        ));
    }

    /**
     * Returns a COPY of the collection. PHP arrays are value types, so callers can do
     * what they like with the returned array without ever touching the aggregate's own.
     *
     * @return list<IncomeSource>
     */
    public function incomeSources(): array
    {
        return $this->incomeSources;
    }

    public function incomeSourceCount(): int
    {
        return count($this->incomeSources);
    }

    /** A calculation the aggregate performs over its own line items. */
    public function totalMonthlyIncome(): Money
    {
        return array_reduce(
            $this->incomeSources,
            fn (Money $carry, IncomeSource $source) => $carry->add($source->monthlyAmount()),
            Money::zero($this->requestedAmount->currency),
        );
    }

    public function submit(): void
    {
        $this->guardIsDraft('submit');

        if ($this->incomeSources === []) {
            throw new DomainException('Cannot submit a bond application with no declared income.');
        }

        $this->status = ApplicationStatus::Submitted;
    }

    private function guardIsDraft(string $action): void
    {
        if ($this->status !== ApplicationStatus::Draft) {
            throw new DomainException(
                "Cannot {$action} an application once it is {$this->status->value}."
            );
        }
    }
}
