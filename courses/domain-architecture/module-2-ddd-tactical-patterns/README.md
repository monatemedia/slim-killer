# Module 2 — DDD Tactical Patterns
> **Advanced Domain Architecture & Tactical DDD**
> **Folder:** `module-2-ddd-tactical-patterns/`

---

## Module Goal

Take the isolated Value Objects and Entities from Module 1 and **cluster them into a consistency boundary** (an Aggregate), then **exile the database** behind a pure domain interface. You will learn where business logic lives when it doesn't belong to any single entity, and you will finally relocate the half-migrated `App\Actions\SubmitApplicationAction` wreckage into a clean `App\Domain\Bond\` structure.

By the end of this module you will be able to:

1. Design an **Aggregate Root** (`BondApplication`) that is the only legal door to its internal entities.
2. Protect a **consistency boundary** so child "line items" (`IncomeSource`) cannot be mutated from outside.
3. Define a **pure repository interface** that trades in aggregates and contains zero SQL.
4. Implement that interface in `src/Infrastructure/Persistence/` using Pixie — and swap it for an in-memory fake in tests.
5. Place multi-entity logic in a **Domain Service** (`AffordabilityService`) instead of forcing it into an entity.

> **Golden Rules in focus:** **Rule A** (domain imports nothing below), **Rule C** (aggregate root is the only door), **Rule D** (repositories trade in aggregates).

---

## 🆕 PHP 8.5 Features in This Module

| Feature | Lesson | What it does for the domain |
|---------|--------|-----------------------------|
| Asymmetric visibility (`public private(set)`) | **2.1** | Expose aggregate state read-only; mutate only via root methods |
| `clone with` syntax | **2.1** | Lifecycle transitions that return a new aggregate state |
| Interface type hints (DIP) | **2.2** | Domain depends on `BondApplicationRepository`, never on Pixie |
| First-class callable syntax | **2.3** | Compose affordability policies as injectable rules |
| `never` return type | **2.1** | Boundary guards that reject illegal mutations |

---

## 📁 Module Structure

```
module-2-ddd-tactical-patterns/
├── README.md                                   ← You are here
├── lesson-2.0-layering-and-the-dependency-rule/
├── lesson-2.1-aggregates-and-boundaries/
├── lesson-2.2-repositories-pure-domain/
└── lesson-2.3-domain-services/
```

---

## Lesson 2.0 — Layering & The Dependency Rule ⭐ Start here

> **Dependencies point inward. The domain knows nothing of the framework.**

### Topics
- The layered/onion model: `Http` → `Domain` ← `Infrastructure`.
- The **Dependency Rule**: source-code dependencies may only point *toward* the domain.
- Why `src/Domain/` may never `use` Slim, Twig, Pixie, PDO, or PSR-7.
- Mapping Slim Killer's directories onto the layers; planning the move of the Bond domain into `src/Domain/Bond/`.
- How PHP-DI (from the OOP course) wires a concrete infrastructure class to a domain interface at the composition root.

### Bond focus
Diagnose the current coupling: `SubmitApplicationAction` lives under `src/Domain/Application/` but is really infrastructure (arrays + booleans), and `ApplicationRepository` mixes domain intent with Pixie calls. Draw the target dependency graph before writing code.

### Lesson checklist
- [ ] Draw the three layers and the inward-pointing dependency arrows
- [ ] List every framework `use` statement currently polluting the "domain" files
- [ ] Plan the `App\Domain\Bond\` namespace move (fixing the PSR-4 break)
- [ ] **Quiz:** The Dependency Rule and layer direction

---

## Lesson 2.1 — Aggregates & Consistency Boundaries

> **Group what must change together. Guard it behind a single root.**

### Topics
- Aggregate vs entity vs value object — the consistency boundary.
- The **Aggregate Root** as the sole entry point; invariants enforced on every mutation.
- "Line items" under a boundary: a `BondApplication` owns its `IncomeSource` collection; you never edit an income source directly.
- Transactional consistency: the whole aggregate is saved/loaded as one unit.
- Protecting internal collections (return copies, mutate only via root methods).

### PHP 8.5 in practice

```php
// PHP 8.5 — aggregate root is the only door to its line items
final class BondApplication
{
    /** @var list<IncomeSource> */
    private array $incomeSources = [];

    public private(set) ApplicationStatus $status = ApplicationStatus::Draft;

    public function addIncomeSource(IncomeSource $source): void
    {
        if ($this->status !== ApplicationStatus::Draft) {
            throw new \DomainException('Income sources are locked after submission.');
        }
        $this->incomeSources[] = $source;
    }

    public function totalMonthlyIncome(): Money
    {
        return array_reduce(
            $this->incomeSources,
            fn (Money $carry, IncomeSource $s) => $carry->add($s->monthlyAmount()),
            Money::zero(Currency::ZAR),
        );
    }
}
```

### Bond focus
Model `BondApplication` as the aggregate root owning `Applicant` and a collection of `IncomeSource` line items. External code adds income only through `addIncomeSource()`, which enforces the "Draft only" invariant — the consistency boundary in action.

### Lesson checklist
- [ ] Identify the aggregate root and its boundary for the Bond Application
- [ ] Implement `IncomeSource` as a guarded child of the root
- [ ] Enforce an invariant on mutation (no edits after submission)
- [ ] Protect the internal collection from outside mutation
- [ ] Use `clone with` / root methods for lifecycle transitions
- [ ] **Quiz:** Aggregate boundaries and transactional consistency

---

## Lesson 2.2 — Pure Domain Repositories

> **The domain asks for aggregates. It never knows there is a database.**

### Topics
- Repository as a collection-like **interface** living in the domain (`src/Domain/Bond/Repository/`).
- Methods speak aggregates: `save(BondApplication $a): void`, `ofId(ApplicationId $id): ?BondApplication`.
- Why no SQL, no Pixie, no `array` leaks across the interface (Rule D).
- The implementation (`PixieBondApplicationRepository`) lives in `src/Infrastructure/Persistence/Bond/` and does the mapping between aggregate ↔ rows.
- Wiring the interface → implementation in `config/services.php` via PHP-DI.
- Swapping in an in-memory fake for tests (Test Behaviours, Not Layouts).

### PHP 8.5 in practice

```php
// src/Domain/Bond/Repository/BondApplicationRepository.php — PURE
namespace App\Domain\Bond\Repository;

interface BondApplicationRepository
{
    public function save(BondApplication $application): void;
    public function ofId(ApplicationId $id): ?BondApplication;
}

// src/Infrastructure/Persistence/Bond/PixieBondApplicationRepository.php
final class PixieBondApplicationRepository implements BondApplicationRepository
{
    public function __construct(private QueryBuilderHandler $db) {}
    // maps Money->cents, ApplicationId->string, etc. onto the `applications` table
}
```

### Bond focus
Replace the concrete `ApplicationRepository::create(array $data)` with the `BondApplicationRepository` interface that accepts a whole `BondApplication`. The Pixie/PDO code — including converting `Money` cents back to the `DECIMAL(15,2)` column — is quarantined in the infrastructure implementation.

### Lesson checklist
- [ ] Define the pure `BondApplicationRepository` interface (aggregates only)
- [ ] Implement `PixieBondApplicationRepository` in `src/Infrastructure/Persistence/Bond/`
- [ ] Map aggregate ↔ DB row inside the implementation only
- [ ] Wire interface → implementation in `config/services.php`
- [ ] Write an in-memory fake repository for tests
- [ ] **Code Challenge:** Persist a `BondApplication` through both the in-memory fake and the Pixie implementation behind the same interface
- [ ] **Quiz:** Repository purity and the DIP

---

## Lesson 2.3 — Domain Services

> **When the logic belongs to no single entity, it gets its own stateless home.**

### Topics
- The smell: an operation that needs data from multiple entities/VOs and doesn't naturally belong to any one.
- Domain Service vs Application Service vs entity method — choosing the right home.
- Keeping domain services **stateless** (OOP Mastery Rule 5): inputs in, result out.
- Composing business policies (e.g., affordability rules) with first-class callables.
- Returning a typed result or throwing a typed domain exception (the bridge to Module 3).

### PHP 8.5 in practice

```php
// PHP 8.5 — stateless domain service spanning multiple aggregates' data
final class AffordabilityService
{
    private const float MAX_INSTALMENT_RATIO = 0.30; // 30% of income

    public function assess(BondApplication $application, Percentage $interestRate): void
    {
        $income     = $application->totalMonthlyIncome();
        $instalment = $application->estimatedMonthlyInstalment($interestRate);

        if ($instalment->exceedsRatioOf($income, self::MAX_INSTALMENT_RATIO)) {
            // typed business exception — built in Module 3
            throw new ApplicantHasInsufficientIncomeException($application->id(), $income, $instalment);
        }
    }
}
```

### Bond focus
Build `AffordabilityService`, which spans the applicant's `IncomeSource` totals and the requested `Money` at a given `Percentage` interest rate. The "approve only if instalment ≤ 30% of income" rule lives nowhere else — not in `BondApplication`, not in `Money` — so it earns a domain service.

### Lesson checklist
- [ ] Recognise when a rule needs a domain service vs an entity method
- [ ] Implement `AffordabilityService` as a stateless service
- [ ] Compose at least one configurable policy as a callable
- [ ] Have the service throw a typed domain exception on rule violation
- [ ] **Quiz:** Domain services vs entity behaviour

---

## ✅ Module 2 Completion Checklist

- [ ] Lesson 2.0 — Layering & the Dependency Rule (target graph drawn)
- [ ] Lesson 2.1 — Aggregates & boundaries (`BondApplication` + `IncomeSource`)
- [ ] Lesson 2.2 — Pure repositories (interface in Domain, Pixie impl in Infrastructure)
- [ ] Lesson 2.3 — Domain services (`AffordabilityService`)
- [ ] **Code Challenge complete:** aggregate persisted via fake *and* Pixie behind one interface
- [ ] **Bond Milestone reached:** zero SQL in the domain; `App\Domain\Bond\` namespaces are PSR-4 correct

---

*Next module: **Module 3 — Domain Exception Trees** — where `AffordabilityService` stops throwing generic errors and starts throwing a typed hierarchy that the HTTP layer maps into clean, leak-free API responses.*
