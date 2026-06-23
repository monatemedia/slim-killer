<?php

declare(strict_types=1);

namespace Bond\Model;

use Bond\ValueObject\ApplicationId;
use Bond\ValueObject\Money;
use DomainException;

/**
 * 🏗️  CODE CHALLENGE — Enforce the aggregate's consistency boundary
 * -------------------------------------------------------------------------
 * The plumbing (constructor, accessors, the guardIsDraft() helper, the protected
 * incomeSources() copy) is done. Your job is the three methods that make this a real
 * aggregate root — each must ENFORCE the boundary, not just mutate state.
 *
 * REQUIREMENTS (the verifier checks every one):
 *   1. addIncomeSource(IncomeSource):
 *        - only while Draft (use guardIsDraft)
 *        - reject a source whose currency differs from the requested amount (DomainException)
 *        - otherwise append it
 *   2. totalMonthlyIncome(): Money
 *        - sum of every source's monthly amount, in the application's currency
 *        - an application with no income totals Money::zero(<app currency>)
 *   3. submit():
 *        - only while Draft (use guardIsDraft)
 *        - reject if there is no declared income (DomainException)
 *        - otherwise move to Submitted
 *
 * TIP: Money has ->hasSameCurrencyAs(), ->add(), and Money::zero($currency).
 *      The app's currency is $this->requestedAmount->currency.
 *
 * Run the verifier:    php challenge/verify.php
 * Reference solution:  challenge/solution/BondApplication.php
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
        // TODO: requirement 1
        throw new \RuntimeException('TODO: implement BondApplication::addIncomeSource()');
    }

    public function totalMonthlyIncome(): Money
    {
        // TODO: requirement 2
        throw new \RuntimeException('TODO: implement BondApplication::totalMonthlyIncome()');
    }

    public function submit(): void
    {
        // TODO: requirement 3
        throw new \RuntimeException('TODO: implement BondApplication::submit()');
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
