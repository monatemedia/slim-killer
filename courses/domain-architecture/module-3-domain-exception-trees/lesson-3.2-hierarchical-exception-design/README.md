# Lesson 3.2 — Hierarchical Exception Design
> **Module 3 — Domain Exception Trees**
> **Folder:** `lesson-3.2-hierarchical-exception-design/`

> **Throw narrow. Catch wide.** Lesson 3.1 drew the line between infrastructure and domain failures. Now we give the *domain* side a deliberate shape: a tree, rooted at an abstract base, branching through a bounded-context node, ending in concrete leaves — so the edge can catch a whole context at once or a single rule for special handling.

---

## Why this lesson exists

In 3.1 `DomainException` was a single flat base. Real applications have *many* business rules, and they need different handling: most should map to a generic `422`, but a few deserve bespoke treatment ("already submitted" → redirect to the existing application, not an error page). A flat list of unrelated exception classes can't express that. A **tree** can.

The shape:

```
DomainException                         (Shared — abstract root: "a business rule said no", app-wide)
└─ BondApplicationException             (bounded-context node — abstract: defines context())
   ├─ ApplicantHasInsufficientIncomeException
   ├─ BondAmountExceedsPropertyValueException
   └─ ApplicationAlreadySubmittedException
```

By the end of this lesson you will be able to:

1. Build an abstract **root** (`DomainException`) and a bounded-context **node** (`BondApplicationException`).
2. Implement concrete **leaf** exceptions that carry safe, structured context.
3. **Throw narrow, catch wide** — pick the handling altitude deliberately.
4. Use `#[Override]` on the context hook and `never` on a guard that always throws.

> **Golden Rule in focus:** **Rule E** — exceptions speak business (now in a structured hierarchy), then get translated.

---

## Root, node, leaf

**Root — [`Bond\Shared\Exception\DomainException`](src/Shared/Exception/DomainException.php).** Abstract, lives in *Shared* because it is the universal "business rule said no" type that every bounded context extends. `catch (DomainException)` catches a business failure from *anywhere* — bond, subscriptions, billing.

**Node — [`Bond\Domain\Exception\BondApplicationException`](src/Domain/Exception/BondApplicationException.php).** Abstract, the bounded-context grouping. It also defines the contract every bond leaf must honour — an abstract `context()` of safe, client-facing fields:

```php
abstract class BondApplicationException extends DomainException
{
    /** @return array<string, scalar>  Safe fields only — never traces, SQL, or paths. */
    abstract public function context(): array;
}
```

**Leaves — the concrete failures.** Each sets a safe message and implements `context()` with `#[\Override]` (it implements the node's abstract method):

```php
final class ApplicantHasInsufficientIncomeException extends BondApplicationException
{
    public function __construct(
        public readonly string $applicationId,
        public readonly int $monthlyIncomeCents,
        public readonly int $requiredInstalmentCents,
    ) {
        parent::__construct('Applicant income does not support the required instalment.');
    }

    #[\Override]
    public function context(): array
    {
        return [
            'application_id'            => $this->applicationId,
            'monthly_income_cents'      => $this->monthlyIncomeCents,
            'required_instalment_cents' => $this->requiredInstalmentCents,
        ];
    }
}
```

> **Why `context()` is abstract on the node:** a bond leaf literally cannot exist without declaring exactly what it is allowed to tell the outside world. There is no default that might accidentally leak — every leaf makes a deliberate, reviewed choice of safe fields.

---

## 🆕 PHP features in this lesson (and they run on 8.3)

Unlike the earlier `php85-preview` files, these run on the lesson's PHP 8.3 runtime today:

- **`#[\Override]`** (PHP **8.3**) on each leaf's `context()` — the compiler verifies it really implements a parent method. Rename or change the node's `context()` signature and every leaf fails loudly instead of silently drifting.
- **`never` return type** (PHP 8.1) on a guard that always throws — see [`BondRules`](src/Domain/Rule/BondRules.php):

```php
private static function rejectOverLeveraged(string $id, int $bond, int $property): never
{
    throw new BondAmountExceedsPropertyValueException($id, $bond, $property);
}
```

`: never` documents that control never returns from this call, and lets static analysers treat the code after it as unreachable.

---

## 💻 Example 01 — The tree

```bash
cd courses/domain-architecture/module-3-domain-exception-trees/lesson-3.2-hierarchical-exception-design
php examples/01-the-tree.php
```

```
Each leaf carries a safe message + structured context (no traces, no SQL):
  • ApplicantHasInsufficientIncomeException
      message: Applicant income does not support the required instalment.
      context: {"application_id":"111...","monthly_income_cents":3000000,"required_instalment_cents":1333038}
  • BondAmountExceedsPropertyValueException
      message: The requested bond exceeds the value of the property.
      context: {"application_id":"111...","bond_amount_cents":150000000,"property_value_cents":120000000}
  • ApplicationAlreadySubmittedException
      message: This application has already been submitted and can no longer be changed.
      context: {"application_id":"111...","current_status":"submitted"}

Every leaf is-a node is-a root is-a Throwable:
  instanceof BondApplicationException : true
  instanceof DomainException          : true
  instanceof Throwable                : true

So ONE catch at the node level handles every bond leaf:
  caught as BondApplicationException -> ApplicantHasInsufficientIncomeException
  caught as BondApplicationException -> BondAmountExceedsPropertyValueException
  caught as BondApplicationException -> ApplicationAlreadySubmittedException

Leaves are usually thrown by guards. BondRules uses a `never`-returning helper:
  guard threw -> BondAmountExceedsPropertyValueException: The requested bond exceeds the value of the property.
```

---

## 💻 Example 02 — Throw narrow, catch wide

The domain throws the most *specific* leaf; the handler chooses its *altitude*:

```bash
php examples/02-catch-at-the-right-altitude.php
```

```
Scenario 'insufficient_income':
  [node]     HTTP 422 bond rejected: Applicant income does not support the required instalment.
             context={"application_id":"111...","monthly_income_cents":3000000,"required_instalment_cents":1333038}

Scenario 'already_submitted':
  [specific] redirect the user to their existing application (111...)

Scenario 'other_context':
  [root]     HTTP 422 domain failure (other context): This email is already subscribed.
```

The handler stacks catches from **specific to wide**:

```php
try {
    raise($scenario, $appId);
} catch (ApplicationAlreadySubmittedException $e) {   // one rule, bespoke handling
    // redirect to the existing application
} catch (BondApplicationException $e) {                // the whole bond context
    // HTTP 422 + $e->context()
} catch (DomainException $e) {                          // any business failure, any context
    // HTTP 422
}
```

This is the payoff of the tree: **the domain only ever throws the narrowest exception it can, and each handler catches at exactly the altitude it cares about.** A new leaf added later is automatically caught by the node and root handlers — no edge changes required.

---

## 🏗️ Code Challenge — Add a leaf

The root, node, and two leaves are provided. Open [`challenge/BondAmountExceedsPropertyValueException.php`](challenge/BondAmountExceedsPropertyValueException.php) and complete the third leaf:

1. Extend `BondApplicationException`.
2. Set the exact message: `"The requested bond exceeds the value of the property."`
3. `context()` returns exactly `application_id`, `bond_amount_cents`, `property_value_cents`.
4. Leak nothing — scalars only, with `#[\Override]` on `context()`.

```bash
php challenge/verify.php              # checks YOUR implementation
php challenge/verify.php --solution   # the reference solution (always green)
```

The stub already passes the structural checks (it's in the tree); the message and context checks fail until you fill them in:

```
Verifying BondAmountExceedsPropertyValueException (solution)

  [PASS] extends BondApplicationException (and DomainException, Throwable)
  [PASS] has the exact safe business message
  [PASS] context() returns exactly the expected keys + values
  [PASS] context() leaks nothing (scalars only, no trace/sql/file keys)
  [PASS] is caught by a catch (BondApplicationException) handler

--------------------------------------------------------
ALL 5 CHECKS PASSED ✅
```

Reference: [`challenge/solution/BondAmountExceedsPropertyValueException.php`](challenge/solution/BondAmountExceedsPropertyValueException.php).

---

## 📂 Files in this lesson

```
lesson-3.2-hierarchical-exception-design/
├── README.md                          ← You are here
├── autoload.php                       ← tiny PSR-4 autoloader (Bond\ -> src/)
├── src/
│   ├── Shared/Exception/
│   │   └── DomainException.php                         ← abstract ROOT (shared kernel)
│   └── Domain/
│       ├── Exception/
│       │   ├── BondApplicationException.php            ← bounded-context NODE (abstract context())
│       │   ├── ApplicantHasInsufficientIncomeException.php
│       │   ├── BondAmountExceedsPropertyValueException.php
│       │   └── ApplicationAlreadySubmittedException.php
│       └── Rule/BondRules.php                          ← guard with a `never` thrower
├── examples/
│   ├── 01-the-tree.php                ← hierarchy, instanceof chain, catch at the node
│   └── 02-catch-at-the-right-altitude.php ← throw narrow, catch wide
└── challenge/
    ├── BondAmountExceedsPropertyValueException.php     ← complete the leaf
    ├── solution/BondAmountExceedsPropertyValueException.php
    └── verify.php                     ← behaviour-based self-checker
```

---

## 🧠 Quiz — Throw-narrow / catch-wide and tree depth

1. Why is `DomainException` placed in `Shared/` rather than in the Bond context?
2. What does making `context()` *abstract* on the node guarantee that a default implementation would not?
3. A handler stacks `catch (ApplicationAlreadySubmittedException)` after `catch (BondApplicationException)`. What goes wrong, and why?
4. What does `#[\Override]` on a leaf's `context()` protect you from during a future refactor?
5. A new rule, `PropertyInFloodZoneException`, is added as a bond leaf next month. Which existing handlers catch it with **no** code change, and why?
6. Why is `never` (not `void`) the right return type for `rejectOverLeveraged()`?

---

## ✅ Lesson 3.2 checklist

- [ ] Implement the abstract `DomainException` root in `Shared/Exception/`
- [ ] Implement the `BondApplicationException` bounded-context node
- [ ] Implement the three concrete leaf exceptions with safe `context()`
- [ ] Use `#[\Override]` on the context hook and `never` on a guard helper
- [ ] Run both examples; catch at the node and at the root
- [ ] **Code Challenge:** complete the third leaf until `php challenge/verify.php` is all green
- [ ] Answer the six quiz questions

---

*Next lesson: **Lesson 3.3 — Rendering & API Boundary Mapping**, where this tree meets the edge: a Slim error handler maps each exception type to an HTTP status and an RFC 7807 Problem Details body — using `context()` for safe fields, suppressing stack traces, and wiring into Slim Killer's `addErrorMiddleware(...)`.*
