<?php

declare(strict_types=1);

namespace Bond\Model;

use Bond\ValueObject\ApplicationId;
use Bond\ValueObject\Money;
use DomainException;

/**
 * ✅ REFERENCE SOLUTION — the aggregate root's boundary methods.
 *
 * Each mutator guards first, then changes state. Every invariant the business cares
 * about lives behind the root, so no caller can drive the cluster into an illegal shape.
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

    /** @return list<IncomeSource> */
    public function incomeSources(): array
    {
        return $this->incomeSources;
    }

    public function incomeSourceCount(): int
    {
        return count($this->incomeSources);
    }

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
