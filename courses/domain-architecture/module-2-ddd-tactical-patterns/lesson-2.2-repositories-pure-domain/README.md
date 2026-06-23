# Lesson 2.2 — Pure Domain Repositories
> **Module 2 — DDD Tactical Patterns**
> **Folder:** `lesson-2.2-repositories-pure-domain/`

> **The domain asks for aggregates. It never knows there is a database.** Lesson 2.1 built the `BondApplication` aggregate. Now we give it somewhere to live — behind an interface the domain owns, with all the SQL exiled to infrastructure.

---

## Why this lesson exists

The live `ApplicationRepository::create(array $data)` takes an array of columns, casts `(float) $data['bond_amount']`, and runs Pixie. It is persistence *and* a leak: the database's shape (columns, floats) reaches up into code that calls it. The Lesson 2.0 audit flagged exactly this — domain code depending on a concrete persistence class.

The **Repository** pattern closes the leak. The domain declares an *interface* that speaks only in aggregates; infrastructure provides an *implementation* that does the dirty work of mapping aggregates to rows. The domain depends on the interface and never learns the implementation's name.

By the end of this lesson you will be able to:

1. Define a **pure** repository interface that trades in aggregates, not rows (Rule D).
2. Implement that interface against a real database, keeping all SQL/mapping inside it.
3. Map an aggregate ↔ rows — including the `Money` ↔ `DECIMAL` conversion — in **one** place.
4. Write an in-memory fake that honours the same contract for fast tests.
5. Wire the interface to an implementation at the composition root (`config/services.php`).

> **Golden Rules in focus:** **Rule D** (repositories trade in aggregates) and **Rule A** (the domain imports nothing below).

---

## The pure interface

[`src/Domain/Repository/BondApplicationRepository.php`](src/Domain/Repository/BondApplicationRepository.php) is the whole idea in a few lines:

```php
namespace Bond\Domain\Repository;

interface BondApplicationRepository
{
    public function save(BondApplication $application): void;     // aggregate in
    public function ofId(ApplicationId $id): ?BondApplication;    // aggregate out
}
```

Read what is **absent**: no `save(array $data)`, no `bond_amount`, no `QueryBuilderHandler`, no SQL. To the domain it looks like an in-memory collection of applications that happens to survive between requests. That is Rule D — and because it lives in the domain and imports nothing below, it satisfies the Dependency Rule from Lesson 2.0.

> The interface uses `ofId()` rather than `findById()` to keep the *collection* metaphor: "give me the application **of** this id", as if from a set you already hold. Naming is part of keeping the domain ignorant of databases.

---

## Two implementations, one contract

Both live under `src/Infrastructure/Persistence/` — the outer layer, where framework and driver knowledge is allowed.

**The fake** — [`InMemoryBondApplicationRepository`](src/Infrastructure/Persistence/InMemoryBondApplicationRepository.php): an array. This is the double you unit-test domain services against (prequel Module 5). It **clones on save and on load** to mimic a real database's snapshot semantics — without that, a test passing here would fail against the real DB.

**The real one** — [`SqliteBondApplicationRepository`](src/Infrastructure/Persistence/SqliteBondApplicationRepository.php): raw PDO + SQLite. This is the only file in the lesson that knows about tables, columns, SQL, transactions, and the `Money` ↔ `DECIMAL` conversion. Note the mapping responsibility in each direction:

```php
// save(): aggregate -> rows.  Money cents become a DECIMAL string.
':amount' => $application->requestedAmount()->toDecimalString(),   // 200000000 cents -> "2000000.00"

// ofId(): rows -> aggregate.  reconstitute() rebuilds the exact stored state.
return BondApplication::reconstitute($id, $this->decimalToMoney($row['bond_amount'], $currency), $status, $sources);
```

> **In the real Slim Killer app** this class is `PixieBondApplicationRepository` in `src/Infrastructure/Persistence/Bond/`, using the injected Pixie handle instead of raw PDO. We use PDO here so the lesson runs with **zero framework loaded** — but the job is identical.

### Reconstitution — the one factory infrastructure may call
An aggregate's normal life starts with `BondApplication::start()` and proceeds through `submit()`. But a repository loading a *submitted* application can't replay that workflow — it must rebuild the object directly in its stored state. That is `BondApplication::reconstitute(...)`: it trusts storage and bypasses the lifecycle guards on purpose. Application code uses `start()`; only infrastructure uses `reconstitute()`.

---

## 💻 Example 01 — Save and reconstitute through the interface

```bash
cd courses/domain-architecture/module-2-ddd-tactical-patterns/lesson-2.2-repositories-pure-domain
php examples/01-repository-roundtrip.php
```

```
=== Example 01 — Repository Round-Trip ===

Saved application id: 23f492bf-943e-4b1c-8fcf-b65b655aea6e

Reconstituted from the repository:
  requested amount:   R1,250,000.00
  status:             submitted
  income sources:     2
  total income:       R45,500.00

ofId(unknown):        null
```

The persisting code depends only on `BondApplicationRepository` — it hands over an aggregate and gets an aggregate back. (The id is a freshly minted UUID, so yours will differ.)

---

## 💻 Example 02 — One contract, two backends

The same scenario runs against the array fake **and** a real SQLite database, with no change to the scenario code:

```bash
php examples/02-same-contract-two-backends.php
```

```
=== Example 02 — Same Contract, Two Backends ===

In-memory backend:  amount=R2,000,000.00 status=submitted income=1 total=R72,000.00
SQLite backend:     amount=R2,000,000.00 status=submitted income=1 total=R72,000.00

Raw applications row in SQLite: {"bond_amount":2000000,"currency":"ZAR","status":"submitted"}
We saved Money via toDecimalString() ('2000000.00'); SQLite's NUMERIC affinity
stored it as 2000000. The repository converts both ways — the domain never knows.
```

That raw row is a real teaching moment: we wrote the DECIMAL string `"2000000.00"`, but SQLite's NUMERIC affinity stored `2000000`, and MySQL's `DECIMAL` would have returned the string back. **Normalising that storage quirk is the repository's job** — which is precisely why the domain must never see it.

---

## Wiring it at the composition root

The domain type-hints the interface; the container supplies the implementation. In Slim Killer that binding lives in `config/services.php`. Today it wires the concrete legacy repository:

```php
// config/services.php — BEFORE
ApplicationRepository::class => function (ContainerInterface $c) {
    return new ApplicationRepository($c->get('db'));
},
```

After this lesson it binds the **interface** to the Pixie implementation:

```php
// config/services.php — AFTER
use App\Domain\Bond\Repository\BondApplicationRepository;
use App\Infrastructure\Persistence\Bond\PixieBondApplicationRepository;

BondApplicationRepository::class => function (ContainerInterface $c) {
    return new PixieBondApplicationRepository($c->get('db'));
},
```

Now any domain service that type-hints `BondApplicationRepository` receives the Pixie implementation in production — and you pass `InMemoryBondApplicationRepository` directly in a unit test. Same contract, swapped at the edge. (Re-run the Lesson 2.0 audit after this and the concrete-dependency violation is gone.)

---

## 🏗️ Code Challenge — Implement the fake against the contract

Open [`challenge/InMemoryBondApplicationRepository.php`](challenge/InMemoryBondApplicationRepository.php) and implement `save()` and `ofId()` so they honour the repository **contract** — including snapshot semantics (clone in, clone out).

```bash
php challenge/verify.php              # checks YOUR fake (and the SQLite parity)
php challenge/verify.php --solution   # the reference solution (always green)
```

The verifier runs the *same* behavioural contract against your fake **and** against the real `SqliteBondApplicationRepository`. With the stub, your fake fails while the SQLite parity already passes — showing you the target:

```
Contract against your InMemoryBondApplicationRepository:
  [FAIL] ofId(unknown) returns null  [threw: TODO: implement ofId()]
  ...
Contract against SqliteBondApplicationRepository (reference parity):
  [PASS] ofId(unknown) returns null
  [PASS] save() then ofId() returns the same identity
  [PASS] round trip preserves amount, status and income
  [PASS] snapshot: mutating the saved aggregate does not change stored state
```

A correct fake makes all **8** checks (4 yours + 4 parity) pass:

```
------------------------------------------------------------
ALL 8 CONTRACT CHECKS PASSED ✅
```

This is the lesson's deepest point: a good test double and the real database are **interchangeable behind the interface**, so a contract that passes against one should pass against both. Reference: [`challenge/solution/InMemoryBondApplicationRepository.php`](challenge/solution/InMemoryBondApplicationRepository.php).

---

## 📂 Files in this lesson

```
lesson-2.2-repositories-pure-domain/
├── README.md                          ← You are here
├── autoload.php                       ← tiny PSR-4 autoloader (Bond\ -> src/), mirrors composer
├── src/
│   ├── Domain/
│   │   ├── ValueObject/{Currency,Money,ApplicationId}.php
│   │   ├── Model/{ApplicationStatus,IncomeSource,BondApplication}.php
│   │   └── Repository/BondApplicationRepository.php       ← the PURE interface
│   └── Infrastructure/
│       └── Persistence/
│           ├── InMemoryBondApplicationRepository.php      ← the fake (snapshot semantics)
│           └── SqliteBondApplicationRepository.php        ← real PDO/SQLite (maps aggregate <-> rows)
├── examples/
│   ├── 01-repository-roundtrip.php    ← save + reconstitute through the interface
│   └── 02-same-contract-two-backends.php ← array vs real SQLite, identical scenario
└── challenge/
    ├── InMemoryBondApplicationRepository.php       ← implement save() + ofId()
    ├── solution/InMemoryBondApplicationRepository.php
    └── verify.php                     ← runs the repository CONTRACT against your fake + SQLite parity
```

> The lesson is laid out as `src/Domain/` and `src/Infrastructure/` so you can *see* the boundary the Dependency Rule draws. There is no `php85-preview/`: the repository pattern is about interfaces and the DIP, which are version-agnostic — no PHP 8.5-only syntax is needed.

---

## 🧠 Quiz — Repository purity and the DIP

1. State Rule D. What two things may cross a repository interface, and what must never?
2. Why is `ofId(ApplicationId): ?BondApplication` better for the domain than `find(int $id): array`?
3. The `Money` ↔ `DECIMAL` conversion happens inside `SqliteBondApplicationRepository`. Why is that the correct home for it, and what breaks if a controller does it instead?
4. Why must the in-memory fake `clone` on save? Give the test that would wrongly pass without it.
5. `BondApplication::reconstitute()` bypasses the lifecycle guards that `start()`/`submit()` enforce. Why is that correct for a repository, and dangerous if application code called it?
6. In `config/services.php`, the binding maps an interface to a concrete class. Which SOLID principle is that, and how does it let a unit test avoid the database entirely?

---

## ✅ Lesson 2.2 checklist

- [ ] Define the pure `BondApplicationRepository` interface (aggregates only)
- [ ] Understand `SqliteBondApplicationRepository` mapping aggregate ↔ rows in one place
- [ ] Run both examples; confirm the array and SQLite backends behave identically
- [ ] Explain the `Money` ↔ `DECIMAL` conversion and where it lives
- [ ] Understand reconstitution vs the normal `start()`/`submit()` workflow
- [ ] Wire the interface → implementation in `config/services.php` (on paper or in the app)
- [ ] **Code Challenge:** implement the in-memory fake until `php challenge/verify.php` is all green (8/8)
- [ ] Answer the six quiz questions

---

*Next lesson: **Lesson 2.3 — Domain Services**, where the affordability rule — which spans the applicant's income and the requested amount and belongs to no single entity — gets its own stateless home, `AffordabilityService`, and starts throwing the typed exceptions that Module 3 will map to HTTP responses.*
