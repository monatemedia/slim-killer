<?php

declare(strict_types=1);

namespace Bond\ValueObject;

/**
 * 🏗️  CODE CHALLENGE — Implement LoanToValueRatio
 * -------------------------------------------------------------------------
 * The Loan-to-Value (LTV) ratio is the single most important number in bond
 * origination: the loan amount as a percentage of the property's value.
 * A bank will decline or reprice a bond whose LTV is too high.
 *
 * Your job: turn this stub into a proper Value Object that COMPOSES the
 * Money and Percentage objects you studied in this lesson.
 *
 * REQUIREMENTS (the verifier checks every one):
 *   1. Immutable & self-validating — extend the pattern from Money/Percentage.
 *   2. Constructed from two Money values: the loan amount and the property value.
 *   3. Reject a non-positive property value (you cannot divide by zero rands).
 *   4. Reject a currency mismatch between loan and property.
 *   5. Reject loan > property value — an LTV above 100% is illegal in THIS VO
 *      (the over-leverage business rule is detected at construction time).
 *   6. Expose `asPercentage(): Percentage` — e.g. R900k loan on a R1m property -> 90%.
 *
 * TIP: reuse Money::isGreaterThan(), the Money->cents accessor, and
 *      Percentage::fromBasisPoints(). 1% = 100 basis points; 100% = 10000 bps.
 *
 * Run the verifier as you go:   php challenge/verify.php
 * Reference solution:           challenge/solution/LoanToValueRatio.php
 */
final readonly class LoanToValueRatio
{
    public function __construct(
        public Money $loanAmount,
        public Money $propertyValue,
    ) {
        // TODO: validate property value is positive (requirement 3)
        // TODO: validate matching currency (requirement 4)
        // TODO: reject loan > property value (requirement 5)
        throw new \RuntimeException('TODO: implement LoanToValueRatio::__construct()');
    }

    public function asPercentage(): Percentage
    {
        // TODO: compute basis points = loan / propertyValue * 10000, then
        //       return Percentage::fromBasisPoints(...) (requirement 6)
        throw new \RuntimeException('TODO: implement LoanToValueRatio::asPercentage()');
    }
}
