<?php

declare(strict_types=1);

/**
 * ⚠️  PHP 8.5 PREVIEW — DO NOT RUN ON PHP 8.4 OR BELOW
 * -------------------------------------------------------------------------
 * This file uses syntax that PARSE-ERRORS on older runtimes:
 *   - `clone with [...]`   (PHP 8.5)
 *   - `#[\NoDiscard]`      (PHP 8.5)
 *
 * It is the TARGET form of the Money wither. The runnable `src/Money.php`
 * hand-writes the same behaviour so the lesson works on PHP 8.3+ today.
 * On the course's target runtime, activate it with `herd use 8.5` and run:
 *
 *   php php85-preview/money-with-clone-with.php
 *
 * Compare this `withCents()` to the one in src/Money.php — same semantics,
 * less ceremony, and `#[\NoDiscard]` makes "Money is immutable" enforceable:
 * ignoring the returned value is now a warning, not a silent bug.
 */

namespace Bond\ValueObject\Preview;

enum Currency: string
{
    case ZAR = 'ZAR';
    case USD = 'USD';
}

final readonly class Money
{
    public function __construct(
        public int $cents,
        public Currency $currency,
    ) {
        if ($cents < 0) {
            throw new \InvalidArgumentException("Money cannot be negative; received {$cents} cents.");
        }
    }

    // PHP 8.5: the caller MUST use the result. `$money->withCents(0);` on its own now warns,
    // catching the classic "I thought this mutated in place" immutability bug.
    #[\NoDiscard('Money is immutable — use the returned instance.')]
    public function withCents(int $cents): static
    {
        // PHP 8.5: concise immutable copy with only the changed field listed.
        return clone $this with ['cents' => $cents];
    }

    public function withCurrency(Currency $currency): static
    {
        return clone $this with ['currency' => $currency];
    }
}

$original = new Money(125_000_000, Currency::ZAR);
$adjusted = $original->withCents(99_999_900);

printf("Original: %d cents (%s)\n", $original->cents, $original->currency->value);
printf("Adjusted: %d cents (%s)\n", $adjusted->cents, $adjusted->currency->value);
echo "Original is unchanged — `clone with` produced a new object.\n";
