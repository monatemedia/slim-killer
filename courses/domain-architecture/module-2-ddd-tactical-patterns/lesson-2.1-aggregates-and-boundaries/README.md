# Lesson 2.1 — Aggregates & Consistency Boundaries
> **Module 2 — DDD Tactical Patterns**
> **Folder:** `lesson-2.1-aggregates-and-boundaries/`

> **Group what must change together. Guard it behind a single root.** Module 1 gave you isolated objects; Lesson 2.0 gave you the Dependency Rule. Now we cluster those objects into an **aggregate** so the business's invariants are enforced on every change — by one object, in one place.

---

## Why this lesson exists

A bond application is not just a row — it is an application *plus* the applicant's declared income sources, and rules tie them together: you cannot submit with no income; income must be in the bond's currency; nothing may be edited after submission. If any external class could reach in and tweak an income source or flip the status, those rules would be unenforceable.

The **Aggregate** pattern solves this: cluster the related objects, nominate a **root**, and make the root the *only* door through which the cluster can change. Every mutation passes through a method that can enforce the invariants. This is the **consistency boundary**.

By the end of this lesson you will be able to:

1. Identify the **aggregate root** and its boundary for the Bond Application.
2. Model `IncomeSource` as a guarded **line item** inside the aggregate.
3. Enforce invariants **on mutation** (currency match, draft-only, "income required to submit").
4. Protect the internal collection so outside code cannot mutate it.
5. Understand how a whole aggregate is treated as one unit for changes (and, in Module 2.2, for persistence).

> **Golden Rules in focus:** **Rule C** (the aggregate root is the only door) and **Rule B** (illegal states — here, illegal *combinations* — unrepresentable).

---

## Aggregate, root, boundary — the vocabulary

- **Aggregate** — a cluster of objects treated as a single unit for data changes. Here: a `BondApplication` and its `IncomeSource` line items.
- **Aggregate Root** — the one member outside code may reference, and the only entry point for changes. Here: `BondApplication`.
- **Consistency Boundary** — the line around the aggregate inside which all invariants must always hold true. Changes are atomic with respect to it: after any root method returns, the whole aggregate is valid.

The rule of thumb: **outside code may hold a reference to the root, never to anything inside it.** You don't fetch an `IncomeSource` and edit it; you tell the `BondApplication` to add or remove one, and it decides whether that is currently legal.

---

## The line item: `IncomeSource`

[`src/IncomeSource.php`](src/IncomeSource.php) is an **immutable** value-style object (employer + monthly `Money`). Immutability is half the boundary protection: even if a reference leaks, the item cannot be edited. The *set* of income sources can only change through the root.

## The root: `BondApplication`

[`src/BondApplication.php`](src/BondApplication.php) owns the collection privately and exposes intent-revealing doors. The shape of every mutator is **guard, then change**:

```php
public function addIncomeSource(IncomeSource $source): void
{
    $this->guardIsDraft('add income to');                       // invariant: lifecycle

    if (! $source->monthlyAmount()->hasSameCurrencyAs($this->requestedAmount)) {
        throw new DomainException(                              // invariant: currency
            'Income must be declared in the same currency as the requested bond.'
        );
    }

    $this->incomeSources[] = $source;
}

public function submit(): void
{
    $this->guardIsDraft('submit');

    if ($this->incomeSources === []) {                          // invariant: income required
        throw new DomainException('Cannot submit a bond application with no declared income.');
    }

    $this->status = ApplicationStatus::Submitted;
}
```

And the collection is never handed out for mutation:

```php
/** @return list<IncomeSource> */
public function incomeSources(): array
{
    return $this->incomeSources;   // PHP arrays are value types → callers get a COPY
}
```

> We still throw the built-in `\DomainException` as a placeholder — **Module 3** replaces these with typed business exceptions (e.g. `ApplicationAlreadySubmittedException`) that the HTTP layer maps to clean responses.

---

## 💻 Example 01 — The aggregate root as the only door

```bash
cd courses/domain-architecture/module-2-ddd-tactical-patterns/lesson-2.1-aggregates-and-boundaries
php examples/01-the-aggregate-root.php
```

```
=== Example 01 — The Aggregate Root ===

New application: R1,250,000.00 (draft)

Income sources added: 2
Total monthly income: R45,500.00

Rejected (currency):   Income must be declared in the same currency as the requested bond.
Rejected (no income):  Cannot submit a bond application with no declared income.

After submit():        submitted
Rejected (locked):     Cannot add income to an application once it is submitted.
```

Every invariant is enforced by the root: currency match on the way in, "income required" at submission, and a **closed boundary** after submission — the aggregate refuses to change once it has left Draft.

---

## 💻 Example 02 — You cannot reach past the root

```bash
php examples/02-protecting-the-collection.php
```

```
=== Example 02 — Protecting the Collection ===

Income sources on the aggregate: 1

We appended to the RETURNED array. Its length is now: 2
But the aggregate is untouched:                       1

Why? PHP arrays are value types — incomeSources() handed back a COPY.
And each IncomeSource is immutable (readonly), so even the items can't be edited.
The ONLY way to change the set is the root's add/remove methods, which guard invariants.

After removeIncomeSource() via the root: 0
```

Two layers of protection: the collection is returned **by copy** (so appending to it can't grow the aggregate), and each item is **immutable** (so the items themselves can't be edited). The only path to change is the guarded root methods.

> **Aside (PHP specifics):** PHP arrays are copy-on-write value types, which is why returning the array protects it for free. In a language where collections are reference types, you would return a defensive copy or an unmodifiable view to get the same guarantee.

---

## 🆕 PHP 8.4 / 8.5: read-only state & immutable transitions

The runnable aggregate uses private fields + getter methods so it runs on **PHP 8.3 today**. On the target runtime you can sharpen it:

```php
// PHP 8.4 — status is readable as a property everywhere, writable only inside the class.
public private(set) ApplicationStatus $status = ApplicationStatus::Draft;

// PHP 8.5 — model a transition immutably: return a NEW aggregate state.
public function submit(): static
{
    if ($this->incomeSources === []) {
        throw new \DomainException('Cannot submit with no income.');
    }
    return clone $this with ['status' => ApplicationStatus::Submitted];
}
```

The full preview is in [`php85-preview/bond-application-aggregate-php85.php`](php85-preview/bond-application-aggregate-php85.php). **Do not run it on PHP 8.3** — `private(set)` and `clone with` are parse errors there.

---

## 🏗️ Code Challenge — Enforce the consistency boundary

Open [`challenge/BondApplication.php`](challenge/BondApplication.php). The plumbing is done; you implement the three methods that make it a real aggregate root:

1. `addIncomeSource()` — draft-only; reject a mismatched currency; otherwise append.
2. `totalMonthlyIncome()` — sum every source in the app's currency; zero when empty.
3. `submit()` — draft-only; reject when there is no income; otherwise move to Submitted.

```bash
php challenge/verify.php              # checks YOUR implementation
php challenge/verify.php --solution   # the reference solution (always green)
```

A correct implementation reaches all green:

```
Verifying BondApplication aggregate (solution)

  [PASS] Fresh application has zero total income (in app currency)
  [PASS] Total income sums every source
  [PASS] addIncomeSource rejects a mismatched currency (DomainException)
  [PASS] submit() with no income throws DomainException
  [PASS] submit() with income moves to Submitted
  [PASS] addIncomeSource AFTER submit throws DomainException (boundary closed)

--------------------------------------------------------
ALL 6 CHECKS PASSED ✅
```

Reference: [`challenge/solution/BondApplication.php`](challenge/solution/BondApplication.php).

---

## 📂 Files in this lesson

```
lesson-2.1-aggregates-and-boundaries/
├── README.md                              ← You are here
├── src/
│   ├── Currency.php                       ← value object (Module 1)
│   ├── Money.php                          ← value object (Module 1) + hasSameCurrencyAs()
│   ├── ApplicationId.php                  ← identity value object (Module 1)
│   ├── ApplicationStatus.php              ← lifecycle states (enum)
│   ├── IncomeSource.php                   ← immutable line item inside the aggregate
│   └── BondApplication.php                ← the AGGREGATE ROOT
├── examples/
│   ├── 01-the-aggregate-root.php          ← the root as the only door + invariants
│   └── 02-protecting-the-collection.php   ← the boundary cannot be reached past
├── php85-preview/
│   └── bond-application-aggregate-php85.php ← private(set) + clone with (do NOT run on 8.3)
└── challenge/
    ├── BondApplication.php                ← implement the three boundary methods
    ├── solution/BondApplication.php       ← reference solution
    └── verify.php                         ← behaviour-based self-checker
```

---

## 🧠 Quiz — Aggregate boundaries and transactional consistency

1. Define aggregate, aggregate root, and consistency boundary in one sentence each, using the Bond Application.
2. Why is it a rule that outside code may reference the root but never an `IncomeSource` directly?
3. The aggregate enforces "income currency must match the bond currency." Why is `addIncomeSource()` the right place for that check, rather than the controller or the repository?
4. `incomeSources()` returns the array, yet appending to the result does not change the aggregate. Why — and what would you do to get the same protection in a language with reference-type collections?
5. Why does the whole aggregate get saved and loaded as **one unit** (foreshadowing Lesson 2.2), rather than persisting income sources independently?

---

## ✅ Lesson 2.1 checklist

- [ ] Identify the aggregate root and its boundary for the Bond Application
- [ ] Understand `IncomeSource` as an immutable, guarded line item
- [ ] Run both examples; observe invariants enforced and the collection protected
- [ ] Enforce an invariant on mutation (currency, draft-only, income-required)
- [ ] Read the PHP 8.4/8.5 `private(set)` + `clone with` preview
- [ ] **Code Challenge:** implement the three boundary methods until `php challenge/verify.php` is all green
- [ ] Answer the five quiz questions

---

*Next lesson: **Lesson 2.2 — Pure Domain Repositories**, where this whole aggregate is saved and loaded behind an interface the domain owns — and the Pixie/SQL code is exiled to `src/Infrastructure/Persistence/Bond/`.*
