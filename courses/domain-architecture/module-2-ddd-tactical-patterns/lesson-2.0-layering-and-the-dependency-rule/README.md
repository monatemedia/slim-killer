# Lesson 2.0 — Layering & The Dependency Rule
> **Module 2 — DDD Tactical Patterns** · ⭐ Start here
> **Folder:** `lesson-2.0-layering-and-the-dependency-rule/`

> **Dependencies point inward. The domain knows nothing of the framework.** Module 1 built the nouns (`Money`, `BondApplication`, `ApplicationId`). Before we cluster them into aggregates and exile the database, you need the one rule that governs *where* every class is allowed to point.

---

## Why this lesson exists

You now have rich domain objects, but they are scattered and — in the live repo — sitting in the wrong place with the wrong dependencies. `SubmitApplicationAction` lives under `src/Domain/` yet depends on a concrete repository; both domain files declare namespaces that don't even autoload. Before Module 2 can build the real aggregate and repository, you must be able to answer one question for every file: **which way is this class allowed to depend?**

This lesson gives you the **Dependency Rule** and a runnable tool to enforce it, then plans the namespace move that the rest of Module 2 carries out.

By the end you will be able to:

1. Draw the three layers (`Http`, `Domain`, `Infrastructure`) and the direction dependencies must flow.
2. State the **Dependency Rule** and apply it to any class in the repo.
3. Explain why `src/Domain/` may never `use` Slim, Twig, Pixie, PDO, or PSR-7.
4. Run an audit that finds every Dependency-Rule violation in the live domain.
5. Plan the `App\Domain\Bond\` namespace move that fixes the half-finished DDD migration.

> **Golden Rule in focus:** **Rule A** — the Domain Layer imports nothing from below.

---

## The layered model

```
            ┌─────────────────────────────────────────────┐
            │            HTTP / Presentation               │   src/Http/
            │   Controllers · Twig · PSR-7 · error renderer │
            └───────────────────────┬─────────────────────┘
                                     │ depends on
                                     ▼
            ┌─────────────────────────────────────────────┐
            │                  DOMAIN                       │   src/Domain/
            │  Entities · Value Objects · Domain Services   │   ◄── knows NOTHING
            │  Repository INTERFACES · Domain Exceptions    │       about the layers
            └───────────────────────▲─────────────────────┘       around it
                                     │ depends on (implements its interfaces)
            ┌───────────────────────┴─────────────────────┐
            │              INFRASTRUCTURE                   │   src/Infrastructure/
            │   Pixie/PDO repositories · mailers · caches   │
            └─────────────────────────────────────────────┘
```

Both the outer layers depend on the **Domain** in the centre. The Domain depends on **neither**. This is the "onion"/"hexagonal"/"clean" architecture — the names differ, the rule is identical.

### The Dependency Rule

> **Source-code dependencies may only point *toward* the domain. Nothing in the domain may name anything in an outer layer.**

Concretely, a file in `src/Domain/`:

- ✅ may `use` other domain classes (`Money`, `ApplicationId`, a repository **interface**).
- ✅ may `use` PHP/SPL built-ins (`DomainException`, `DateTimeImmutable`).
- ❌ may **never** `use` `Pixie\…`, `Slim\…`, `Twig\…`, `Psr\Http\…`, `\PDO`, or a concrete `App\Infrastructure\…` class.

The infrastructure depends on the domain by **implementing the domain's interfaces** — the arrow points inward, as in the diagram.

### Why this matters (the diagnostic question, again)
If the domain imported Pixie, then "what is a bond application?" would be answered partly by a SQL library. Delete Pixie and the business rules vanish. By forbidding the import, the domain stays answerable purely in business terms — and stays testable with zero framework loaded (which Module 5 of the prequel relied on).

---

## 💻 Example 01 — The Dependency Rule, demonstrated

Pure PHP, no framework. A domain service depends only on an interface; two different implementations are swapped **at the composition root** without the domain changing.

```bash
cd courses/domain-architecture/module-2-ddd-tactical-patterns/lesson-2.0-layering-and-the-dependency-rule
php examples/01-dependency-direction.php
```

```
=== Example 01 — The Dependency Rule ===

In-memory implementation:
  applications stored:   2

SQL implementation (same domain service, swapped at the root):
  applications stored:   1
  last SQL executed:     INSERT INTO applications (reference) VALUES ('APP-2002')

The domain service never changed. Only the composition root chose a different
implementation. THAT is what 'dependencies point inward' buys you.
```

Read the file top to bottom: the `Bond\Domain` namespace has **zero** framework imports, `Bond\Infrastructure` depends on the domain interface, and the global **composition root** is the only place that names a concrete class — exactly the role `config/services.php` and `public/index.php` play in Slim Killer.

---

## 🔬 Example 02 — Audit the live domain

A real tool that scans the repo's actual `src/Domain/` and reports violations. Run it:

```bash
php examples/02-audit-the-domain.php
```

```
=== Example 02 — Domain Dependency Audit ===
Scanning: C:/xampp/htdocs/slim-killer/src/Domain

  [VIOLATION] Domain/Application/InsultService.php
      - PSR-4 MISMATCH  declared 'App\Services', expected 'App\Domain\Application' (will not autoload)
  [VIOLATION] Domain/Application/SubmitApplicationAction.php
      - PSR-4 MISMATCH  declared 'App\Actions', expected 'App\Domain\Application' (will not autoload)
      - CONCRETE DEPENDENCY  imports 'App\Repositories\ApplicationRepository' (depend on a domain interface instead)

------------------------------------------------------------
0 clean, 2 file(s) violating the Dependency Rule.
Module 2 fixes these by moving the Bond domain into App\Domain\Bond\ and
depending on interfaces, not concrete persistence.
```

The audit confirms the wreckage we are inheriting:

- **Both** `src/Domain/` files declare namespaces (`App\Services`, `App\Actions`) that don't match their path under `src/`, so PSR-4 cannot autoload them — the half-finished DDD migration.
- `SubmitApplicationAction` depends on a **concrete** repository class, not a domain interface — a Dependency-Rule violation even after the namespace is fixed.

> Re-run this audit after each step of Module 2. The goal is to make it print **"Domain is clean."**

---

## 🗺️ Mapping Slim Killer onto the layers

| Directory | Layer | Obeys the rule today? |
|-----------|-------|-----------------------|
| `src/Http/` | HTTP / Presentation | ✅ controllers correctly sit outside the domain |
| `src/Infrastructure/Persistence/` | Infrastructure | ✅ Pixie belongs here |
| `src/Infrastructure/Middleware/` | Infrastructure | ✅ |
| `src/Domain/Application/` | Domain | ❌ wrong namespaces + concrete dependency (see audit) |
| `config/services.php`, `public/index.php` | Composition Root | ✅ the only place concretes are wired |

---

## 🧭 The namespace-move plan (executed across Module 2)

Module 2 relocates the Bond domain into a clean, PSR-4-correct structure. The target:

```
src/Domain/Bond/
├── BondApplication.php                 → namespace App\Domain\Bond            (Lesson 2.1)
├── IncomeSource.php                    → namespace App\Domain\Bond            (Lesson 2.1)
├── ValueObject/Money.php               → namespace App\Domain\Bond\ValueObject (from Module 1)
├── Service/AffordabilityService.php    → namespace App\Domain\Bond\Service    (Lesson 2.3)
└── Repository/BondApplicationRepository.php  → INTERFACE, namespace App\Domain\Bond\Repository (Lesson 2.2)

src/Infrastructure/Persistence/Bond/
└── PixieBondApplicationRepository.php  → implements the interface, namespace App\Infrastructure\Persistence\Bond (Lesson 2.2)
```

The moves this lesson commits you to:

1. Every Bond domain class lives under `App\Domain\Bond\…` with a namespace that matches its path (audit goes green on PSR-4).
2. The domain depends on a **repository interface** it owns, never on a concrete persistence class.
3. The concrete Pixie repository moves to `src/Infrastructure/Persistence/Bond/` and is wired to the interface in `config/services.php`.

---

## How PHP-DI wires the inward dependency

The composition root is where the abstract meets the concrete. In Slim Killer that is `config/services.php`. After Lesson 2.2 it will contain a binding like:

```php
use App\Domain\Bond\Repository\BondApplicationRepository;            // interface (domain)
use App\Infrastructure\Persistence\Bond\PixieBondApplicationRepository; // implementation (infra)

return [
    // When anything asks for the domain INTERFACE, give it the infra IMPLEMENTATION.
    BondApplicationRepository::class => function (ContainerInterface $c) {
        return new PixieBondApplicationRepository($c->get('db'));
    },
];
```

Domain code type-hints `BondApplicationRepository` (the interface). PHP-DI supplies `PixieBondApplicationRepository` at runtime. The arrow points inward, and the domain never learns the implementation's name — the same inversion you saw in Example 01, now done by the container.

---

## ✍️ Do it yourself

1. Run `examples/02-audit-the-domain.php` and copy its findings.
2. Open `src/Domain/Application/SubmitApplicationAction.php`. List every `use` statement and label each: *domain-safe* or *violation*.
3. On paper, draw the three-layer diagram and place these real files on it: `HomeController`, `ApplicationRepository`, `Money` (Module 1), `PixieBondApplicationRepository` (planned), `config/services.php`.
4. Write the target namespace for each Bond class in the move plan, and confirm it matches its path.

---

## 🧠 Quiz — The Dependency Rule and layer direction

1. State the Dependency Rule in one sentence. Which direction may a `src/Domain/` class never point?
2. Why is `use Pixie\QueryBuilder\QueryBuilderHandler;` acceptable in `src/Infrastructure/Persistence/` but a violation in `src/Domain/`?
3. The audit reports a "CONCRETE DEPENDENCY" on `App\Repositories\ApplicationRepository`. Why is depending on a concrete repository a violation even once the namespace is fixed?
4. What is a "composition root", and which two files play that role in Slim Killer?
5. In Example 01, the domain service ran identically against an array and against "SQL". Which design property made that possible, and which Module 1 / OOP-Mastery principle is it?

---

## ✅ Lesson 2.0 checklist

- [ ] Draw the three layers and the inward-pointing dependency arrows
- [ ] Run `examples/01-dependency-direction.php` and trace the three namespaces
- [ ] Run `examples/02-audit-the-domain.php` and record every violation
- [ ] List every framework/concrete `use` statement currently polluting the "domain" files
- [ ] Write out the `App\Domain\Bond\` target namespace for each class in the move plan
- [ ] Explain how `config/services.php` binds a domain interface to an infra implementation
- [ ] Answer the five quiz questions

---

## 📂 Files in this lesson

```
lesson-2.0-layering-and-the-dependency-rule/
├── README.md                          ← You are here
└── examples/
    ├── 01-dependency-direction.php    ← swap implementations without touching the domain
    └── 02-audit-the-domain.php        ← live Dependency-Rule audit of the repo's src/Domain
```

*(No `challenge/` folder: Lesson 2.0 is the conceptual foundation. The hands-on build begins in Lesson 2.1, where `BondApplication` becomes an aggregate root.)*

---

*Next lesson: **Lesson 2.1 — Aggregates & Consistency Boundaries**, where `BondApplication` becomes the aggregate root that owns its `IncomeSource` line items and is the only legal door to changing them.*
