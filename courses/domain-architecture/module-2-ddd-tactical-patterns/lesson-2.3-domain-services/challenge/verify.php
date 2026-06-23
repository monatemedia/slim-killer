<?php

declare(strict_types=1);

/**
 * Self-checking verifier for the AffordabilityService challenge.
 * -------------------------------------------------------------------------
 *   php challenge/verify.php              → checks YOUR challenge/AffordabilityService.php
 *   php challenge/verify.php --solution   → checks the reference solution (always green)
 */

require __DIR__ . '/../autoload.php';

// Load the implementation under test BEFORE the class name is referenced, so the
// autoloader never pulls the src/ copy (which would clash with this one).
$useSolution = in_array('--solution', $argv, true);
require $useSolution
    ? __DIR__ . '/solution/AffordabilityService.php'
    : __DIR__ . '/AffordabilityService.php';

use Bond\Domain\Exception\ApplicantHasInsufficientIncomeException;
use Bond\Domain\Model\BondApplication;
use Bond\Domain\Model\IncomeSource;
use Bond\Domain\Service\AffordabilityService;
use Bond\Domain\ValueObject\Currency;
use Bond\Domain\ValueObject\Money;
use Bond\Domain\ValueObject\Percentage;

$pass = 0;
$fail = 0;

function check(string $label, callable $test): void
{
    global $pass, $fail;
    try {
        $ok = $test();
    } catch (\Throwable $e) {
        $ok = false;
        $label .= "  [threw: " . $e->getMessage() . "]";
    }
    echo $ok ? "  \e[32m[PASS]\e[0m {$label}\n" : "  \e[31m[FAIL]\e[0m {$label}\n";
    $ok ? $pass++ : $fail++;
}

function assertThrows(string $class, callable $fn): bool
{
    try {
        $fn();
        return false;
    } catch (\Throwable $e) {
        return $e instanceof $class;
    }
}

$rate = Percentage::fromPercent(11.5);
$term = 240;

function applicantEarning(int $monthlyRands): BondApplication
{
    $app = BondApplication::start(Money::fromMajorUnits(1_250_000, Currency::ZAR));
    $app->addIncomeSource(new IncomeSource('Job', Money::fromMajorUnits($monthlyRands, Currency::ZAR)));

    return $app;
}

echo "Verifying AffordabilityService (" . ($useSolution ? 'solution' : 'your implementation') . ")\n\n";

check('assess() reports the applicant total income', function () use ($rate, $term) {
    $assessment = (new AffordabilityService())->assess(applicantEarning(45_500), $rate, $term);
    return $assessment->monthlyIncome->equals(Money::fromMajorUnits(45_500, Currency::ZAR));
});

check('assess() max instalment uses the policy (30% of income)', function () use ($rate, $term) {
    $assessment = (new AffordabilityService())->assess(applicantEarning(45_500), $rate, $term);
    return $assessment->maxAllowedInstalment->equals(Money::fromMajorUnits(13_650, Currency::ZAR));
});

check('comfortable income is affordable', function () use ($rate, $term) {
    return (new AffordabilityService())->assess(applicantEarning(45_500), $rate, $term)->isAffordable();
});

check('thin income is NOT affordable', function () use ($rate, $term) {
    return ! (new AffordabilityService())->assess(applicantEarning(30_000), $rate, $term)->isAffordable();
});

check('guardAffordable() throws the typed exception on thin income', fn () => assertThrows(
    ApplicantHasInsufficientIncomeException::class,
    fn () => (new AffordabilityService())->guardAffordable(applicantEarning(30_000), $rate, $term),
));

check('guardAffordable() passes silently on comfortable income', function () use ($rate, $term) {
    (new AffordabilityService())->guardAffordable(applicantEarning(45_500), $rate, $term);
    return true; // no exception thrown
});

check('a looser policy flips the thin applicant to affordable (composability)', function () use ($rate, $term) {
    $loose = fn (Money $income) => Money::fromMajorUnits(15_000, Currency::ZAR);
    return (new AffordabilityService($loose))->assess(applicantEarning(30_000), $rate, $term)->isAffordable();
});

check('the thrown exception carries income + instalment context', function () use ($rate, $term) {
    try {
        (new AffordabilityService())->guardAffordable(applicantEarning(30_000), $rate, $term);
        return false;
    } catch (ApplicantHasInsufficientIncomeException $e) {
        return $e->monthlyIncome->equals(Money::fromMajorUnits(30_000, Currency::ZAR))
            && $e->requiredInstalment->cents > 0;
    }
});

echo "\n" . str_repeat('-', 56) . "\n";
echo $fail === 0
    ? "\e[32mALL {$pass} CHECKS PASSED ✅\e[0m\n"
    : "\e[33m{$pass} passed, \e[31m{$fail} failed\e[0m — keep going.\n";

exit($fail === 0 ? 0 : 1);
