# Advanced Domain Architecture & Tactical DDD
### Master Complex Business Logic · Rich Domain Models · Resilient Exception Trees
> **Sequel to the PHP 8.5 OOP Mastery Course · Built on the Slim Killer micro-framework**

> **How to use this README:** This is the master blueprint for your journey into Domain-Driven Design (DDD) and tactical architecture in PHP 8.5. Work through each module **in order**. Tick off `[ ]` checkboxes as you complete each architectural milestone. Do **not** advance to the next module until every item in the current one is checked.

---

## 🎓 Prerequisite

You must have completed the **PHP 8.5 OOP Mastery Course** (`php8.5-oop-mastery-course-overview/`). This course assumes you already own:

- **Composition over inheritance** (Module 1) — every aggregate here is composed, never inherited.
- **The type system as a security layer** (Module 2) — value objects live or die by strict types and enums.
- **Dependency Injection & IoC** (Module 3) — repositories are injected interfaces, never `new`'d.
- **Container automation with PHP-DI** (Module 4) — Slim Killer's container wires the domain to infrastructure.
- **Testing & TDD** (Module 5) — a pure domain is a *testable* domain; we prove it with fakes.
- **Stateless services & lifecycle** (Module 6) — domain services hold no state.

> In the OOP course you learned **how to wire components**. Here you learn **how to model real-world business reality** so faithfully that the framework becomes a removable detail.

---

## 👑 The Diagnostic Architectural Question

This single question governs every file you touch in this course. Before writing any class, ask:

> **"If I delete my framework, my database driver, and my web server tomorrow morning, does the code inside this file still make perfect sense to a business manager at a bond originator?"**
>
> - **YES** → it belongs in the **Domain Layer** (`src/Domain/`) — Entities, Value Objects, Domain Services, Domain Exceptions, Repository *interfaces*.
> - **NO** → it belongs in the **Infrastructure Layer** (`src/Infrastructure/`) or the **HTTP Layer** (`src/Http/`) — Controllers, Pixie/PDO drivers, repository *implementations*, error renderers.

Every lesson re-asks this question against the Bond Application. If you can answer it instantly for any file, you have understood the course.

---

## 🏦 The Case Study: One Problem, Every Module

We do not jump between toy examples. The **entire** course is the relentless refinement of **one** business problem already living in Slim Killer:

**A Bond Application processing system** — a prospective buyer submits a mortgage/finance application for a property. The system must capture the applicant, validate money and percentages, enforce affordability rules, persist the application, and report business-rule failures in a language a loan officer understands.

### Where we start — the current (anemic) reality

The Bond Application already exists in this repo as a **flat, framework-coupled, primitive-obsessed** flow. This is our deliberate "before" picture:

| File | Today's state | The problem |
|------|---------------|-------------|
| `src/Http/Application/ProcessApplyController.php` | Pulls `$request->getParsedBody()` and hands a **raw `array`** straight to an action | No validation boundary; HTTP shape *is* the domain shape |
| `src/Domain/Application/SubmitApplicationAction.php` | `execute(array $data): bool`; declares the legacy `namespace App\Actions` (PSR-4–broken on this branch) | "Domain" logic that only knows arrays and booleans — anemic |
| `src/Infrastructure/Persistence/Application/ApplicationRepository.php` | `create(array $data)` casts `bond_amount` with `(float)` and inserts named columns | Money as a float; no aggregate; no interface |
| `database/migrations/..._create_applications_table.php` | `bond_amount DECIMAL(15,2)`, flat columns | The table schema is currently the only "model" we have |

> Notice there is **no `Money`, no `BondApplication` entity, no repository interface, and no typed domain error** anywhere. A bond amount is a `float`. An applicant's income is unmodelled. An "insufficient income" rejection would surface today as a generic `500` or a silent `false`. **We will fix all of this.**

### Where we finish — the target domain (built across the three modules)

```
src/
├── Shared/
│   └── Exception/
│       └── DomainException.php                 ← Module 3 · abstract root of the whole tree
├── Domain/
│   └── Bond/
│       ├── BondApplication.php                 ← Module 1+2 · Aggregate Root (Entity)
│       ├── ApplicationId.php                   ← Module 1 · identity Value Object
│       ├── Applicant.php                       ← Module 2 · entity inside the aggregate
│       ├── IncomeSource.php                    ← Module 2 · the "line item" under the boundary
│       ├── ValueObject/
│       │   ├── Money.php                        ← Module 1 · immutable, self-validating, cents-based
│       │   ├── Percentage.php                   ← Module 1 · interest rate / ratio guard
│       │   ├── LoanToValueRatio.php             ← Module 1 · derived invariant VO
│       │   ├── EmailAddress.php                 ← Module 1 · kills `string $email`
│       │   └── ApplicantName.php                ← Module 1 · first/last identity attribute
│       ├── Enum/
│       │   ├── ApplicationStatus.php            ← Module 1 · Draft→Submitted→Approved lifecycle
│       │   └── Province.php                     ← Module 1 · no more magic-string provinces
│       ├── Service/
│       │   └── AffordabilityService.php         ← Module 2 · multi-entity domain service
│       ├── Repository/
│       │   └── BondApplicationRepository.php    ← Module 2 · PURE interface (no SQL)
│       └── Exception/
│           ├── BondApplicationException.php      ← Module 3 · abstract bounded-context node
│           ├── ApplicantHasInsufficientIncomeException.php
│           ├── BondAmountExceedsPropertyValueException.php
│           └── ApplicationAlreadySubmittedException.php
├── Infrastructure/
│   └── Persistence/
│       └── Bond/
│           └── PixieBondApplicationRepository.php  ← Module 2 · implements the interface via Pixie/PDO
└── Http/
    ├── Application/
    │   └── ProcessApplyController.php              ← thin adapter: array → domain → response
    └── ErrorHandler/
        └── DomainExceptionRenderer.php             ← Module 3 · maps domain errors → RFC 7807
```

> **The diagnostic test in action:** everything under `src/Domain/Bond/` survives the deletion of Slim, Twig, Pixie, and Apache. Everything under `src/Infrastructure/` and `src/Http/` does not. That boundary *is* the course.

---

## 📁 Course Folder Structure

```
courses/domain-architecture/
├── README.md                               ← You are here (Master Roadmap)
├── module-1-advanced-domain-modeling/
│   ├── README.md                           ← Rich models, value objects, identity
│   ├── lesson-1.0-diagnostic-and-anemic-trap/
│   ├── lesson-1.1-rich-vs-anemic/
│   ├── lesson-1.2-value-objects/
│   └── lesson-1.3-entities-and-identity/
├── module-2-ddd-tactical-patterns/
│   ├── README.md                           ← Aggregates, pure repositories, domain services
│   ├── lesson-2.0-layering-and-the-dependency-rule/
│   ├── lesson-2.1-aggregates-and-boundaries/
│   ├── lesson-2.2-repositories-pure-domain/
│   └── lesson-2.3-domain-services/
└── module-3-domain-exception-trees/
    ├── README.md                           ← Typed errors, hierarchy, API mapping
    ├── lesson-3.0-why-generic-exceptions-leak/
    ├── lesson-3.1-infrastructure-vs-domain-exceptions/
    ├── lesson-3.2-hierarchical-exception-design/
    └── lesson-3.3-rendering-and-api-mapping/
```

---

## 🗺️ Course Roadmap

```
[Module 1: Advanced Domain Modeling]
   Build the nouns: Money, Percentage, BondApplication identity
         ↓
[Module 2: DDD Tactical Patterns]
   Enforce the boundaries: aggregates, pure repositories, domain services
         ↓
[Module 3: Domain Exception Trees]
   Speak the business language on failure: typed errors → clean API output
```

Each module **physically transforms** the Bond Application in `src/`. By the end you will have refactored the flat-array flow above into a framework-agnostic domain that the Slim Killer HTTP layer merely *adapts to*.

---

## 🆕 PHP 8.5 Features in This Course

The OOP Mastery course *introduced* these features. This course *applies* them to domain modeling.

| Feature | PHP version | Where it appears | Domain purpose |
|---------|-------------|------------------|----------------|
| `readonly` classes | 8.2 | Lessons 1.2, 1.3 | Immutable Value Objects by default |
| Asymmetric visibility (`public private(set)`) | 8.4 | Lessons 1.1, 2.1 | Protect aggregate state from outside mutation |
| **`clone with` syntax** | **8.5** | **Lessons 1.2, 2.1** | Concise immutable "wither" transitions (`approve()`, `withAmount()`) |
| **`#[NoDiscard]` attribute** | **8.5** | **Lessons 1.2, 3.2** | Force callers to use a returned VO / new aggregate state |
| Backed enums | 8.1 | Lesson 1.2 | `ApplicationStatus`, `Province`, `Currency` — no magic strings |
| `never` return type | 8.1 | Lessons 1.2, 3.2 | Guard clauses that throw typed domain exceptions |
| First-class callable syntax | 8.1 | Lesson 2.3 | Composing domain-service policies |
| Property hooks (`get`/`set`) | 8.4 | Lesson 1.1 (callout) | Validation at the data-entry point of rich entities |
| `#[Override]` | 8.5 | Lesson 3.2 (callout) | Verifying exception-tree method overrides |

> ⚠️ Every example targets **PHP 8.5**. Activate it with `herd use 8.5` (Windows/macOS) or `lerd init` (Linux). `clone with`, `#[NoDiscard]`, and `#[Override]` on properties will **parse-error** on older runtimes.

---

## 🏗️ The Golden Rules of Domain Architecture

These extend the OOP Mastery "Golden Rules." Keep them visible as you work.

### Rule A — The Domain Layer Imports Nothing From Below
A file in `src/Domain/` may **never** `use` Slim, Twig, Pixie, PDO, PSR-7, or the container. Dependencies point **inward** (the Dependency Rule). If your `BondApplication` imports `Pixie\QueryBuilder`, it is no longer a domain object — it is a database script wearing a noun's name.

### Rule B — Make Illegal States Unrepresentable
A bond amount of `-50000`, an interest rate of `850%`, or `email = "not-an-email"` must be **impossible to construct**, not "validated later." Value Objects validate in their constructor and throw before an invalid instance can exist. (Type System as Security Layer, applied.)

### Rule C — The Aggregate Root Is the Only Door
External code never mutates an entity *inside* an aggregate directly. You don't reach into `BondApplication` to flip a child `IncomeSource`; you call a method **on the root** that enforces the consistency boundary. State is `private(set)`.

### Rule D — Repositories Trade in Aggregates, Not Rows
A domain repository **interface** accepts and returns `BondApplication` objects — never arrays, never result sets, never `bond_amount` floats. SQL exists only in the `Infrastructure` *implementation* of that interface.

### Rule E — Exceptions Speak Business, Then Get Translated
The domain throws `ApplicantHasInsufficientIncomeException`, not `\RuntimeException("err 42")`. The *HTTP layer* — and only the HTTP layer — translates that into a status code and a safe, leak-free payload.

| Rule | Module 1 | Module 2 | Module 3 |
|------|----------|----------|----------|
| A — Domain imports nothing below | ⭐ | ⭐ | ✓ |
| B — Illegal states unrepresentable | ⭐ | ✓ | ✓ |
| C — Aggregate root is the only door | | ⭐ | ✓ |
| D — Repositories trade in aggregates | | ⭐ | |
| E — Exceptions speak business, then translate | | | ⭐ |

⭐ = primary coverage · ✓ = reinforced

---

## Module 1 — Advanced Domain Modeling
> **Folder:** `module-1-advanced-domain-modeling/`
> See `module-1-advanced-domain-modeling/README.md` for the full lesson breakdown.

Shift from technical classes to **business expressions**. Kill the flat `array $data`. Lock business invariants into the type system so an invalid Bond Application cannot be built.

### High-level checklist
- [ ] Lesson 1.0 — The Diagnostic Question & The Anemic Trap ⭐ Start here
- [ ] Lesson 1.1 — Rich vs. Anemic Domain Models *(asymmetric visibility, property hooks)*
- [ ] Lesson 1.2 — Value Objects & Self-Validation *(`readonly`, `clone with`, `#[NoDiscard]`)*
- [ ] Lesson 1.3 — Entities & Domain Identity *(`ApplicationId`, identity vs equality)*
- [ ] **Code Challenge:** Build the immutable, self-validating `Money` + `Percentage` + `LoanToValueRatio` value-object set
- [ ] **Bond Milestone:** `bond_amount` is no longer a `float` anywhere in the domain

---

## Module 2 — DDD Tactical Patterns
> **Folder:** `module-2-ddd-tactical-patterns/`
> See `module-2-ddd-tactical-patterns/README.md` for the full lesson breakdown.

Cluster the model into a consistency boundary, isolate the database behind a **pure interface**, and place multi-entity logic in a domain service.

### High-level checklist
- [ ] Lesson 2.0 — Layering & The Dependency Rule ⭐ Start here
- [ ] Lesson 2.1 — Aggregates & Consistency Boundaries *(`BondApplication` root + `IncomeSource` line items)*
- [ ] Lesson 2.2 — Pure Domain Repositories *(`BondApplicationRepository` interface vs `PixieBondApplicationRepository`)*
- [ ] Lesson 2.3 — Domain Services *(`AffordabilityService` spanning applicant income + requested amount)*
- [ ] **Code Challenge:** Build the `BondApplication` aggregate with a strict lifecycle, persisted via an in-memory pure repository
- [ ] **Bond Milestone:** `ApplicationRepository` becomes an interface; Pixie SQL lives only in `Infrastructure`

---

## Module 3 — Domain Exception Trees
> **Folder:** `module-3-domain-exception-trees/`
> See `module-3-domain-exception-trees/README.md` for the full lesson breakdown.

Stop treating exceptions as strings. Build a typed hierarchy that speaks the bond-origination business, then map it cleanly to HTTP at the framework boundary — with zero secret leakage.

### High-level checklist
- [ ] Lesson 3.0 — Why Generic Exceptions Leak ⭐ Start here
- [ ] Lesson 3.1 — Infrastructure vs. Domain Exceptions *(`PDOException` vs `ApplicantHasInsufficientIncomeException`)*
- [ ] Lesson 3.2 — Hierarchical Exception Design *(`DomainException` → `BondApplicationException` → leaf errors)*
- [ ] Lesson 3.3 — Rendering & API Boundary Mapping *(Slim error middleware → RFC 7807 Problem Details)*
- [ ] **Code Challenge:** Build the bond exception tree + a translation layer mapping each leaf to a precise API payload
- [ ] **Bond Milestone:** An over-leveraged application returns a clean `422` business message — never a stack trace

---

## 🧭 Bond Application Milestone Map

How the **same** business problem evolves across the three modules:

| Concern | Today (anemic) | After Module 1 | After Module 2 | After Module 3 |
|---------|----------------|----------------|----------------|----------------|
| Bond amount | `(float) $data['bond_amount']` | `Money` value object (cents + `Currency`) | Owned & guarded by the aggregate root | — |
| Interest / LTV | unmodelled | `Percentage` + `LoanToValueRatio` VOs | Enforced inside `AffordabilityService` | — |
| Applicant | array keys | `ApplicantName` + `EmailAddress` VOs | `Applicant` entity inside the aggregate | — |
| The application | flat DB row | identity via `ApplicationId` | `BondApplication` aggregate root + lifecycle | — |
| Persistence | `ApplicationRepository` (concrete Pixie) | — | `BondApplicationRepository` **interface** + Pixie impl | — |
| Affordability rule | none / implicit | — | `AffordabilityService` | throws `ApplicantHasInsufficientIncomeException` |
| Failure output | `false` / generic `500` | — | typed exception thrown | RFC 7807 `422` payload, no leak |

---

## 📊 Domain Architecture Completion Table

| Module | Lessons | Code Challenges | Quizzes | Status |
|--------|---------|-----------------|---------|--------|
| 1 — Advanced Domain Modeling | 4 (1.0–1.3) | 1 | 4 | `[ ] Not started` |
| 2 — DDD Tactical Patterns | 4 (2.0–2.3) | 1 | 4 | `[ ] Not started` |
| 3 — Domain Exception Trees | 4 (3.0–3.3) | 1 | 4 | `[ ] Not started` |

---

## 🛠️ Target Environment

| Tool | Version / Note |
|------|----------------|
| PHP | **8.5** (`herd use 8.5` / `lerd init`) |
| Framework | Slim Killer (Slim 4 + PHP-DI + Twig + Pixie) — present, but treated as a *removable detail* |
| Domain execution | Pure PHP; runnable and testable with **zero** framework loaded |
| Persistence | SQLite by default (`database/database.sqlite`); driver-agnostic via repository interface |
| CLI | `php hammer` (scaffolding, migrations) |
| Core philosophy | **Zero framework bloat inside `src/Domain/`** |

> **Reality check on this repo:** Slim Killer currently runs under XAMPP and the Bond flow has a half-finished namespace migration (`App\Actions\SubmitApplicationAction`). Treat that as the course's starting wreckage — Module 2 is where we relocate the domain into `App\Domain\Bond\` properly and make PSR-4 honest again.

---

## 📖 Reference

- [Domain-Driven Design Reference (Evans)](https://www.domainlanguage.com/ddd/reference/)
- [RFC 7807 — Problem Details for HTTP APIs](https://www.rfc-editor.org/rfc/rfc7807)
- [PHP 8.5 Migration Guide](https://www.php.net/manual/en/migration85.php)
- [Slim PHP — Error Handling](https://www.slimframework.com/docs/v4/middleware/error-handling.html)
- [PHP-DI Documentation](https://php-di.org/)
- [Pixie Query Builder](https://github.com/usmanhalalit/pixie)
- [PSR-11 Container Interface](https://www.php-fig.org/psr/psr-11/)

---

## 🚀 Career Transformation

This course turns the *software engineer* minted by the OOP Mastery course into an **Enterprise Software Architect** who can model chaotic, high-stakes business rules into clean code. The Bond Application is your proof: by the end you can pull the entire `src/Domain/Bond/` layer out, test it in pure isolation, and migrate it across frameworks without breaking a single business rule.

**Industries that hunt for this skillset:** FinTech & financial planning (strict calculation invariants, compliance, multi-currency), PropTech & real-estate origination (legal/statutory compliance boundaries), and logistics/workshop systems (aggregate integrity across many operations).

---

*Begin with **Module 1 → Lesson 1.0**. Do not write a single line of the new domain until you can answer the Diagnostic Architectural Question for every file you plan to create.*
