# Module 1 — Advanced Domain Modeling
> **Advanced Domain Architecture & Tactical DDD**
> **Folder:** `module-1-advanced-domain-modeling/`

---

## Module Goal

Stop modelling the Bond Application as a flat `array $data` and start modelling it as **rich business objects that cannot hold invalid state**. You will eliminate primitive obsession (`float $bondAmount`, `string $email`) and replace it with self-validating Value Objects, then give the application a stable identity that distinguishes it from its attributes.

By the end of this module you will be able to:

1. Diagnose an **anemic domain model** and explain why public getters/setters around a DB row are an anti-pattern.
2. Move business rules *into* the objects that own the data (a **rich** model).
3. Design immutable, self-validating **Value Objects** (`Money`, `Percentage`, `EmailAddress`) that make illegal states unrepresentable.
4. Distinguish an **Entity** (identity over time) from a **Value Object** (defined by its attributes) and implement `ApplicationId`.
5. Answer the Diagnostic Architectural Question instantly for every class you create.

> **Golden Rules in focus:** **Rule A** (domain imports nothing below) and **Rule B** (make illegal states unrepresentable).

---

## 🆕 PHP 8.5 Features in This Module

| Feature | Lesson | What it does for the domain |
|---------|--------|-----------------------------|
| Asymmetric visibility (`public private(set)`) | **1.1** | Public-readable, internally-writable entity state — no setter sprawl |
| Property hooks (`get`/`set`) | **1.1** | Validation at the data-entry point of a rich entity |
| `readonly` classes | **1.2** | Whole-object immutability for Value Objects |
| `clone with` syntax | **1.2** | Concise immutable transitions: `$money->withAmount(...)` |
| `#[NoDiscard]` attribute | **1.2** | Force callers to *use* the returned VO instead of ignoring it |
| Backed enums | **1.2** | `ApplicationStatus`, `Province`, `Currency` — kill magic strings |
| `never` return type | **1.2** | Guard clauses that throw and never fall through |

---

## 📁 Module Structure

```
module-1-advanced-domain-modeling/
├── README.md                                   ← You are here
├── lesson-1.0-diagnostic-and-anemic-trap/
├── lesson-1.1-rich-vs-anemic/
├── lesson-1.2-value-objects/
└── lesson-1.3-entities-and-identity/
```

---

## Lesson 1.0 — The Diagnostic Question & The Anemic Trap ⭐ Start here

> **Read this and audit the current Bond flow before writing any new code.**

### Topics
- The Diagnostic Architectural Question and the Domain ↔ Infrastructure split.
- What "anemic" means: data bags with getters/setters and **zero behaviour**.
- Auditing Slim Killer's current Bond flow as the canonical anemic example:
  - `ProcessApplyController` → `$request->getParsedBody()` → raw `array`.
  - `SubmitApplicationAction::execute(array $data): bool` — logic that only speaks arrays.
  - `ApplicationRepository::create(array $data)` — `(float) $data['bond_amount']`.
- Why "the database table is currently our only model" is a design smell.

### Bond focus
Walk the existing files and label each line **Domain** or **Infrastructure** using the diagnostic question. Discover that there is currently *no* domain layer at all — only infrastructure pretending to be one.

### Lesson checklist
- [ ] Read the full README and the master `README.md` Diagnostic Question section
- [ ] Open the four "before" files and classify each as Domain or Infrastructure
- [ ] Write, in one sentence, why `(float) $data['bond_amount']` violates Rule B
- [ ] **Quiz:** Spotting anemic models in the wild

---

## Lesson 1.1 — Rich vs. Anemic Domain Models

> **Move the behaviour to the data. The data should defend itself.**

### Topics
- Anemic model (getters/setters + external service does all the work) vs. rich model (object enforces its own invariants).
- Tell, Don't Ask: `$application->approve()` instead of `$application->setStatus('approved')`.
- Why public setters are how invalid state sneaks in.
- Encapsulating state with **asymmetric visibility** so it is readable but not externally writable.
- Property hooks as a validation gate at the point of assignment.

### PHP 8.5 in practice

```php
// PHP 8.5 — a rich entity protects its own state
final class BondApplication
{
    // Readable everywhere, writable ONLY from inside the aggregate
    public ApplicationStatus $status { get => $this->status; }

    public private(set) Money $requestedAmount;

    public function approve(): void
    {
        if ($this->status !== ApplicationStatus::Submitted) {
            // typed domain exception arrives in Module 3
            throw new \DomainException('Only submitted applications may be approved.');
        }
        $this->status = ApplicationStatus::Approved;
    }
}
```

### Bond focus
Contrast the current `SubmitApplicationAction` (asks for an array, sets a bool) with a future `BondApplication` that *owns* `requestedAmount` and exposes intent-revealing methods (`submit()`, `approve()`, `decline()`).

### Lesson checklist
- [ ] Define anemic vs rich in your own words
- [ ] Identify three setters that should become intent-revealing methods
- [ ] Apply `public private(set)` to protect entity state
- [ ] Use a property hook to validate on assignment
- [ ] **Code Challenge:** Refactor one anemic operation from `SubmitApplicationAction` into a rich method on a model
- [ ] **Quiz:** Tell-Don't-Ask and encapsulation

---

## Lesson 1.2 — Value Objects & Self-Validation

> **The single most valuable tool in this course. Replace primitives with objects that cannot be wrong.**

### Topics
- The three traits of a Value Object: **immutability**, **structural equality**, **self-validation**.
- Primitive obsession: why `float $bondAmount` and `string $email` are bugs waiting to happen.
- Money done right: store integer **cents**, never floats; pair with a `Currency` enum.
- Structural equality: two `Money(50000000, ZAR)` are equal; identity is irrelevant.
- Immutable transitions with `clone with`; guard clauses with `never`.
- `#[NoDiscard]` so a computed VO is never silently dropped.

### PHP 8.5 in practice

```php
// PHP 8.5 — immutable, self-validating Value Object
readonly class Money
{
    public function __construct(
        public int      $cents,
        public Currency $currency,
    ) {
        if ($cents < 0) {
            throw new \InvalidArgumentException('Money cannot be negative.');
        }
    }

    #[\NoDiscard('The adjusted Money must be used; Money is immutable.')]
    public function withCents(int $cents): static
    {
        return clone $this with ['cents' => $cents];
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents
            && $this->currency === $other->currency;
    }
}

enum Currency: string { case ZAR = 'ZAR'; case USD = 'USD'; }
```

### Bond focus
Build the value objects the Bond Application has always needed but never had: `Money` (the bond amount), `Percentage` (interest rate / LTV), `EmailAddress` and `ApplicantName` (applicant identity), plus the `Province` and `ApplicationStatus` enums. After this lesson, **no `(float)` cast for money exists in the domain.**

### Lesson checklist
- [ ] Immutability with `readonly` classes
- [ ] Self-validation in the constructor (throw before an invalid VO exists)
- [ ] Structural equality via `equals()`
- [ ] `clone with` for immutable transitions
- [ ] `#[NoDiscard]` on returned VOs
- [ ] Backed enums for `Currency`, `Province`, `ApplicationStatus`
- [ ] **Code Challenge:** Build the immutable `Money` + `Percentage` + `LoanToValueRatio` set (the module capstone)
- [ ] **Quiz:** Value Object invariants and equality

---

## Lesson 1.3 — Entities & Domain Identity

> **Some objects are defined by *who* they are, not *what* they contain.**

### Topics
- Entity vs Value Object: identity continuity vs attribute equality.
- Why two applicants with identical details are still **different** applications.
- Modelling identity explicitly with an `ApplicationId` Value Object (not a raw DB auto-increment leaking into the domain).
- Generating identity inside the domain vs. receiving it from infrastructure.
- Equality by identity: `$a->id->equals($b->id)`, regardless of mutable attributes.

### PHP 8.5 in practice

```php
// PHP 8.5 — identity as a first-class Value Object
readonly class ApplicationId
{
    public function __construct(public string $value)
    {
        if (!preg_match('/^[0-9a-f-]{36}$/', $value)) {
            throw new \InvalidArgumentException('ApplicationId must be a UUID.');
        }
    }

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(4)) . '-...'); // illustrative
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
```

### Bond focus
Give `BondApplication` a stable `ApplicationId` so it has continuity across its lifecycle (Draft → Submitted → Approved) even as its attributes change. Contrast with the current design where the DB `id` *is* the only identity and it leaks straight from the `applications` table.

### Lesson checklist
- [ ] Articulate the Entity vs Value Object distinction with a bond example
- [ ] Implement `ApplicationId` as an identity Value Object
- [ ] Implement identity-based equality on the entity
- [ ] Decide where IDs are generated (domain vs infrastructure)
- [ ] **Quiz:** Identity vs structural equality

---

## ✅ Module 1 Completion Checklist

- [ ] Lesson 1.0 — Diagnostic Question & Anemic Trap (current Bond flow audited)
- [ ] Lesson 1.1 — Rich vs Anemic + asymmetric visibility / property hooks
- [ ] Lesson 1.2 — Value Objects + `readonly` / `clone with` / `#[NoDiscard]`
- [ ] Lesson 1.3 — Entities & Identity (`ApplicationId`)
- [ ] **Code Challenge complete:** `Money` + `Percentage` + `LoanToValueRatio`
- [ ] **Bond Milestone reached:** no `float` bond amount and no `string` email anywhere in the domain

---

*Next module: **Module 2 — DDD Tactical Patterns** — where these isolated value objects and entities are clustered into a single **`BondApplication` aggregate** and the database is finally exiled behind a pure repository interface.*
