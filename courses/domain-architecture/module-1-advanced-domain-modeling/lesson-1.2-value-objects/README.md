# Lesson 1.2 — Value Objects & Self-Validation
> **Module 1 — Advanced Domain Modeling**
> **Folder:** `lesson-1.2-value-objects/`

> **The single most valuable tool in this course.** Replace primitives (`float`, `string`) with small objects that *cannot* hold an invalid value. After this lesson, no `(float)` bond amount exists anywhere in the Bond domain.

---

## Why this lesson exists

In Lesson 1.0 you proved the Bond flow is anemic: a bond amount is a `float`, an email is a `string`, and nothing stops a negative R5,000,000 bond from being saved. The cure is the **Value Object** — an object defined entirely by its value, immutable, and self-validating. It is the foundation every later pattern stands on: aggregates (Module 2) are built from value objects, and domain services (Module 2) and exceptions (Module 3) pass them around.

By the end of this lesson you will be able to:

1. Name and recognise the **three traits** of a Value Object: immutability, structural equality, self-validation.
2. Diagnose and eliminate **primitive obsession** in the Bond domain.
3. Build `Money` correctly — as integer **cents**, never a float — paired with a `Currency` enum.
4. Build `Percentage` for interest rates and ratios, stored as exact **basis points**.
5. Distinguish **structural equality** (value objects) from **object identity** (a preview of entities, Lesson 1.3).
6. Apply the PHP 8.5 target syntax (`clone with`, `#[NoDiscard]`) for immutable transitions.

> **Golden Rules in focus:** **Rule B** — make illegal states unrepresentable.

---

## The disease: primitive obsession

**Primitive obsession** is using language primitives (`int`, `float`, `string`, `bool`, `array`) to represent domain concepts that have rules. A bond amount is not "a float" — it is *money*: it cannot be negative, it has a currency, and it must do exact arithmetic. The moment you model it as a `float`, you inherit every way a float can be wrong.

Run the problem first:

```bash
cd courses/domain-architecture/module-1-advanced-domain-modeling/lesson-1.2-value-objects
php examples/01-primitive-obsession.php
```

```
=== Example 01 — Why primitives lie ===

1. Negative bond accepted silently:        R-1,250,000.00
2. 0.1 + 0.2 === 0.3 ?                      FALSE (!!)
   Actual value of 0.1 + 0.2:              0.30000000000000004
3. Rands + dollars added blindly:          1,050,000.00 (of what currency?)
4. Misspelled status is still accepted:    'sumbitted'
5. Absurd interest rate accepted:          850%
```

Every line is a latent bug the runtime allows. A value object makes each one **impossible to construct**.

---

## The three traits of a Value Object

### 1. Immutability
Once built, it never changes. A "change" returns a **new** instance. This makes value objects safe to share, pass around, and reason about — no spooky action at a distance. We use `final readonly class` (PHP 8.2+).

### 2. Self-validation
It validates in its **constructor** and throws before an invalid instance can exist. There is no "set then validate later" window. If you hold a `Money`, it is *already* valid — guaranteed by the type system, not by remembering to call a checker.

### 3. Structural equality
Two value objects are equal if their **values** are equal — identity is irrelevant. `Money(50_000_000, ZAR)` equals another `Money(50_000_000, ZAR)` even though they are different objects in memory. We express this with an explicit `equals()` method.

---

## Building `Money` (the right way)

The canonical implementation lives in [`src/Money.php`](src/Money.php) and [`src/Currency.php`](src/Currency.php). Two decisions matter most:

**Store integer cents, never a float.** Floats cannot represent money exactly (you just saw `0.1 + 0.2`). We store the smallest indivisible unit — cents — as an `int`, so addition and subtraction are exact and rounding happens only at explicit edges (`fromMajorUnits`, `applyTo`).

**Pair the amount with a `Currency` enum.** A bare number can be silently added to a different currency. A `Currency` backed enum makes "what money is this?" a type, and `assertSameCurrency()` turns a rands-plus-dollars mistake into a thrown exception instead of a wrong total.

```php
final readonly class Money
{
    public function __construct(
        public int $cents,
        public Currency $currency,
    ) {
        if ($cents < 0) {                                  // self-validation
            throw new InvalidArgumentException("Money cannot be negative; received {$cents} cents.");
        }
    }

    public function add(self $other): self                 // immutability — returns NEW Money
    {
        $this->assertSameCurrency($other);                 // currency safety
        return new self($this->cents + $other->cents, $this->currency);
    }

    public function equals(self $other): bool              // structural equality
    {
        return $this->cents === $other->cents && $this->currency === $other->currency;
    }
}
```

See it work:

```bash
php examples/02-money-in-action.php
```

```
=== Example 02 — Money Value Object ===

Requested bond:   R1,250,000.00
Deposit:          R125,000.00
Financed amount:  R1,125,000.00

1. Negative Money rejected:   Money cannot be negative; received -500 cents.
2. Currency mismatch rejected: Currency mismatch: cannot operate on ZAR and USD.
3. Original after withCents(): R1,250,000.00 (unchanged)
   New adjusted Money:         R999,999.00

4. R0.10 + R0.20 =             R0.30 (exactly R0.30)
```

The `0.1 + 0.2` bug from Example 01 is now structurally impossible.

---

## Building `Percentage`

Interest rates and ratios get the same treatment ([`src/Percentage.php`](src/Percentage.php)). `Percentage` stores exact **basis points** (1% = 100 bps), validates the `0%–100%` range, and can `applyTo()` a `Money` to return `Money` — so an interest calculation stays in exact integer arithmetic the whole way.

```bash
php examples/03-percentage-in-action.php
```

```
=== Example 03 — Percentage Value Object ===

Bond amount:          R1,250,000.00
Annual interest rate: 11.5%
Annual interest:      R143,750.00

10% deposit on bond:  R125,000.00

Absurd rate rejected: Percentage must be between 0% and 100%; received 85000 basis points.
```

> **Note the private constructor + named factories** (`fromPercent`, `fromBasisPoints`). This is a deliberate pattern: callers must say *which unit they mean*, so `Percentage::fromPercent(11.5)` can never be confused with `new Percentage(11.5)` (which would be 11.5 *basis points* — a different number).

---

## Structural equality vs object identity

This is the distinction that defines the rest of Module 1:

```bash
php examples/04-equality-vs-identity.php
```

```
=== Example 04 — Equality vs Identity ===

$a->equals($b)   : true  (same value -> same money)
$a === $b        : false (different instances — and we DO NOT care)

$a->equals($c)   : false (R50,000.00 != R50,001.00)
$a->equals($d)   : false (ZAR != USD)
```

For a **Value Object** we ask *"is it equal?"* and never *"is it the same object?"*. An **Entity** (Lesson 1.3) is the opposite: a `BondApplication` keeps a stable identity even as its attributes change. Knowing which question to ask for a given concept is the core modelling skill of this module.

---

## 🆕 PHP 8.5: immutable transitions with `clone with` and `#[NoDiscard]`

The runnable `src/Money.php` hand-writes its `withCents()` "wither" so the lesson runs on **PHP 8.3+ today**. On the course's target runtime (**PHP 8.5**) you would write it more concisely and make it safer:

```php
#[\NoDiscard('Money is immutable — use the returned instance.')]
public function withCents(int $cents): static
{
    return clone $this with ['cents' => $cents];   // PHP 8.5
}
```

- **`clone with`** produces an immutable copy with only the changed field listed — no need to re-pass every constructor argument.
- **`#[NoDiscard]`** makes ignoring the return value a warning. It catches the classic immutability bug where someone writes `$money->withCents(0);` expecting in-place mutation and silently loses the result.

The full target-runtime version is in [`php85-preview/money-with-clone-with.php`](php85-preview/money-with-clone-with.php). **Do not run it on PHP 8.4 or below** — `clone with` and `#[NoDiscard]` are parse errors there. On Herd 8.5:

```bash
herd use 8.5
php php85-preview/money-with-clone-with.php
```

---

## 🏗️ Code Challenge — `LoanToValueRatio`

The module capstone. Build the third value object — `LoanToValueRatio` — by **composing** `Money` and `Percentage`. The Loan-to-Value ratio (loan ÷ property value) is the most important number in bond origination; a bank declines or reprices a bond whose LTV is too high.

Open [`challenge/LoanToValueRatio.php`](challenge/LoanToValueRatio.php) and implement the stub so it:

1. Is immutable and self-validating (extend the pattern from `Money`/`Percentage`).
2. Is constructed from two `Money` values — the loan amount and the property value.
3. Rejects a non-positive property value (no dividing by zero rands).
4. Rejects a currency mismatch between loan and property.
5. Rejects loan > property value (an LTV above 100% is illegal in this VO).
6. Exposes `asPercentage(): Percentage` — e.g. R900k loan on a R1m property → 90%.

Check your work with the behaviour-based verifier:

```bash
php challenge/verify.php              # checks YOUR implementation
php challenge/verify.php --solution   # the reference solution (always green)
```

The stub starts fully red; a correct implementation goes fully green:

```
Verifying LoanToValueRatio (solution)

  [PASS] R900k loan on R1m property -> 90%
  [PASS] R1m loan on R1m property -> 100%
  [PASS] Rejects property value of zero
  [PASS] Rejects currency mismatch (ZAR loan, USD property)
  [PASS] Rejects loan greater than property value (LTV > 100%)

------------------------------------------------
ALL 5 CHECKS PASSED ✅
```

A reference solution is in [`challenge/solution/LoanToValueRatio.php`](challenge/solution/LoanToValueRatio.php) — try it yourself before peeking. Notice the solution holds **no numbers of its own**: it composes two `Money` objects and derives a `Percentage`, inheriting all their guarantees for free. That compounding is the whole point of value objects.

---

## 📂 Files in this lesson

```
lesson-1.2-value-objects/
├── README.md                          ← You are here
├── src/
│   ├── Currency.php                   ← backed enum (ZAR, USD)
│   ├── Money.php                      ← the headline value object
│   └── Percentage.php                 ← rates & ratios in basis points
├── examples/
│   ├── 01-primitive-obsession.php     ← the disease
│   ├── 02-money-in-action.php         ← Money cures it
│   ├── 03-percentage-in-action.php    ← Percentage applied to Money
│   └── 04-equality-vs-identity.php    ← structural equality vs identity
├── php85-preview/
│   └── money-with-clone-with.php      ← PHP 8.5 target syntax (do NOT run on <8.5)
└── challenge/
    ├── LoanToValueRatio.php           ← your stub to implement
    ├── solution/LoanToValueRatio.php  ← reference solution
    └── verify.php                     ← behaviour-based self-checker
```

---

## 🧠 Quiz — Value Object invariants and equality

1. Why does `Money` store integer cents instead of a `float`? Give the concrete failure a float causes.
2. What are the three traits of a value object? Point to the line in `src/Money.php` that provides each.
3. Why does `Percentage` use a **private** constructor plus `fromPercent()` / `fromBasisPoints()` factories instead of a public constructor?
4. `$a->equals($b)` is `true` but `$a === $b` is `false`. Explain why that is exactly what we want for `Money`.
5. What does `#[NoDiscard]` protect against, and why does it matter specifically for immutable objects?
6. In the challenge, why is it correct for `LoanToValueRatio` to throw when loan > property value, rather than returning a `Percentage` above 100%?

---

## ✅ Lesson 1.2 checklist

- [ ] Run `examples/01-primitive-obsession.php` and list the five bugs primitives allow
- [ ] Immutability with `final readonly class`
- [ ] Self-validation in the constructor (throw before an invalid VO exists)
- [ ] Structural equality via `equals()`
- [ ] Build/understand `Money` (integer cents) and `Currency`
- [ ] Build/understand `Percentage` (basis points) and `applyTo()`
- [ ] Explain structural equality vs object identity (`examples/04`)
- [ ] Read the PHP 8.5 `clone with` / `#[NoDiscard]` preview
- [ ] **Code Challenge:** implement `LoanToValueRatio` until `php challenge/verify.php` is all green
- [ ] Answer the six quiz questions

---

*Next lesson: **Lesson 1.3 — Entities & Domain Identity**, where `BondApplication` gets an `ApplicationId` and we flip from structural equality to identity equality.*
