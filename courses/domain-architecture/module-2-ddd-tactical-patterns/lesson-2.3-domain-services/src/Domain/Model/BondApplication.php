<?php

declare(strict_types=1);

namespace Bond\Domain\Model;

use Bond\Domain\ValueObject\ApplicationId;
use Bond\Domain\ValueObject\Money;
use Bond\Domain\ValueObject\Percentage;
use DomainException;
use InvalidArgumentException;

/**
 * BondApplication — the aggregate root (Lessons 2.1–2.2).
 *
 * It can compute its own estimated monthly instalment, because that is a property of
 * THIS application's requested amount under given lending terms. But notice what it
 * CANNOT decide on its own: whether the applicant can AFFORD that instalment. That
 * decision needs the applicant's income AND a lending policy (the 30% rule), which is
 * exactly why it belongs in a domain service, not here. (See AffordabilityService.)
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

    public function addIncomeSource(IncomeSource $source): void
    {
        $this->guardIsDraft('add income to');

        if (! $source->monthlyAmount()->hasSameCurrencyAs($this->requestedAmount)) {
            throw new DomainException('Income must be declared in the same currency as the requested bond.');
        }

        $this->incomeSources[] = $source;
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

    /**
     * The amortised monthly instalment for this bond at the given annual rate and term.
     * Standard formula: M = P · r(1+r)^n / ((1+r)^n − 1), with r the monthly rate.
     * This is a pure calculation over the application's OWN data, so it lives here.
     */
    public function estimatedMonthlyInstalment(Percentage $annualRate, int $termMonths): Money
    {
        if ($termMonths <= 0) {
            throw new InvalidArgumentException('Loan term must be a positive number of months.');
        }

        $principalCents = $this->requestedAmount->cents;
        $monthlyRate = $annualRate->toFloat() / 100 / 12;

        if ($monthlyRate <= 0.0) {
            $instalmentCents = (int) ceil($principalCents / $termMonths);
        } else {
            $growth = (1 + $monthlyRate) ** $termMonths;
            $instalmentCents = (int) ceil($principalCents * $monthlyRate * $growth / ($growth - 1));
        }

        return new Money($instalmentCents, $this->requestedAmount->currency);
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
            throw new DomainException("Cannot {$action} an application once it is {$this->status->value}.");
        }
    }
}
