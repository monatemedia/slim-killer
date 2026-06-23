# Lesson 3.1 — Infrastructure vs. Domain Exceptions
> **Module 3 — Domain Exception Trees**
> **Folder:** `lesson-3.1-infrastructure-vs-domain-exceptions/`

> **A dropped database connection is not a business rule. Don't model it as one.** Lesson 3.0 showed two failures — a leaking `PDOException` and a lost business reason. This lesson sorts every failure into one of two streams and makes sure they never cross.

---

## Why this lesson exists

In Lesson 3.0 you saw a `PDOException` leak its stack trace and a business decline disappear into a `false`. Those are the two failure *categories*, and they need opposite handling:

- **Infrastructure failure** — `PDOException`, a network timeout, a full disk. Technical, often retryable, **opaque to the business**, and unsafe to show. → log it, return a generic `500`.
- **Domain failure** — `ApplicantHasInsufficientIncomeException`. A business rule said no. Meaningful, intentional, **safe to explain**. → translate it to a specific client response (a `422`).

The discipline that keeps them separate: **infrastructure exception types must never escape the infrastructure layer.** A `PDOException` is wrapped at the persistence boundary into a clean infrastructure type, so the domain and the edge only ever deal with concepts they understand.

By the end of this lesson you will be able to:

1. Categorise any Bond failure as infrastructure or domain.
2. Wrap a `PDOException` at the persistence boundary so it cannot leak upward.
3. Route the two categories to different HTTP outcomes from a single edge.
4. Confirm no infrastructure exception type is imported into `src/Domain/`.

> **Golden Rule in focus:** **Rule E** (exceptions speak business, then get translated) and **Rule A** (the domain imports nothing below — including `PDOException`).

---

## Classify the failures

The Bond Application can fail in many ways. Sorting them is the first skill:

| Scenario | Category | Surfaces to the client as |
|----------|----------|---------------------------|
| Applicant income too low for the instalment | **Domain** | `422` + specific reason |
| Bond amount exceeds the property value | **Domain** | `422` + specific reason |
| Application already submitted | **Domain** | `422` (or `409`) + specific reason |
| Database connection dropped / table missing | **Infrastructure** | `500` + log reference |
| Mail server timeout sending the confirmation | **Infrastructure** | `500` + log reference (or retry) |

The test: **could a loan officer have caused this, and would they understand it?** If yes, it's a domain failure they're entitled to a clear answer about. If it's the machinery breaking, it's infrastructure — log it and apologise generically.

---

## The two base types

This lesson introduces the dividing line in code:

- [`Bond\Domain\Exception\DomainException`](src/Domain/Exception/DomainException.php) — an **abstract base** marking a failure as a business failure. The edge catches this. `ApplicantHasInsufficientIncomeException` extends it. *(Lesson 3.2 grows this into a full tree.)*
- [`Bond\Infrastructure\Exception\PersistenceException`](src/Infrastructure/Exception/PersistenceException.php) — an infrastructure failure. Crucially it does **not** extend `DomainException`, so it can never be routed as a business rule. It wraps the original `PDOException` as `$previous`.

---

## Wrapping at the persistence boundary

[`SafeBondApplicationStore`](src/Infrastructure/Persistence/SafeBondApplicationStore.php) is the only place that touches PDO — and the only place responsible for not letting PDO leak:

```php
public function save(string $applicationId): void
{
    try {
        $stmt = $this->pdo->prepare('INSERT INTO applications (id) VALUES (:id)');
        $stmt->execute([':id' => $applicationId]);
    } catch (PDOException $e) {
        // Translate the driver failure into a clean infrastructure concept.
        throw new PersistenceException('Could not persist the bond application.', previous: $e);
    }
}
```

Above this boundary, no one needs to know that PDO — or even "SQL" — exists. The original exception is preserved as `$previous` purely so the *full* technical detail can be logged server-side.

---

## 💻 Example 01 — Two categories, two destinations

```bash
cd courses/domain-architecture/module-3-domain-exception-trees/lesson-3.1-infrastructure-vs-domain-exceptions
php examples/01-two-failure-categories.php
```

```
=== Example 01 — Two Failure Categories ===

Domain failure (insufficient income):
  -> HTTP 422 {"title":"Bond application rejected","detail":"Applicant income does not support the required instalment."}
  The applicant gets a precise, safe reason.

Infrastructure failure (database down):
  -> HTTP 500 {"title":"Something went wrong","reference":"err_85891a"}
  The client gets a generic message + a reference; the SQL/trace is logged, not shown.

Same edge, two categories: 422 (business, explained) vs 500 (technical, hidden).
```

One edge ([`ErrorResponder`](src/Http/ErrorResponder.php)), routing purely on the failure's *category*. The domain failure is explained; the infrastructure failure is hidden behind a reference.

---

## 💻 Example 02 — `PDOException` never escapes

```bash
php examples/02-wrapping-at-the-boundary.php
```

```
=== Example 02 — Wrapping at the Boundary ===

PDOException escaped the store?   no
Caught PersistenceException?      yes
Original kept for server logs?    yes — PDOException via getPrevious()

Classification (what the edge routes on):
  PersistenceException (db down)             INFRA  -> HTTP 500
  ApplicantHasInsufficientIncomeException    DOMAIN -> HTTP 422

Because PersistenceException is NOT a DomainException, it can never be mistaken for a
business rule and rendered to the user as one. The two categories stay separate.
```

Trying to `catch (PDOException)` outside the store catches *nothing* — the boundary guarantees a `PersistenceException` instead, with the driver exception tucked away in `getPrevious()` for logging. And because `PersistenceException` is not a `DomainException`, the type system itself prevents it from ever being rendered to a user as a business reason.

---

## 🏗️ Code Challenge — Wrap the boundary

Open [`challenge/SafeBondApplicationStore.php`](challenge/SafeBondApplicationStore.php) and implement `save()` so a raw `PDOException` can never escape:

1. On success (the table exists), insert and return.
2. On a PDO failure, throw a `PersistenceException`.
3. A raw `PDOException` must **not** escape `save()`.
4. Preserve the original as `$previous`.

```bash
php challenge/verify.php              # checks YOUR implementation
php challenge/verify.php --solution   # the reference solution (always green)
```

The stub passes only the static category check; a correct wrap turns it all green:

```
Verifying SafeBondApplicationStore (solution)

  [PASS] save() succeeds when the table exists
  [PASS] save() throws PersistenceException when the DB fails
  [PASS] a raw PDOException does NOT escape save()
  [PASS] the original PDOException is preserved as $previous
  [PASS] PersistenceException is NOT a DomainException (routes to 500, not 422)

--------------------------------------------------------
ALL 5 CHECKS PASSED ✅
```

Reference: [`challenge/solution/SafeBondApplicationStore.php`](challenge/solution/SafeBondApplicationStore.php).

---

## ✍️ Confirm the domain is clean

The Dependency Rule (Lesson 2.0) forbids the domain from importing infrastructure — and `PDOException` is infrastructure. Re-run the audit from Lesson 2.0 against the real `src/Domain/`, and search your own code:

```bash
# Nothing in the domain should mention PDO, Pixie, or a driver exception:
grep -rn "PDOException\|Pixie\|\\\\PDO" src/Domain/        # expect: no matches
```

If a domain class ever needs to `catch (PDOException)`, that is the signal a persistence call has leaked into the domain — push it down behind a repository/store that wraps it.

---

## 🧠 Quiz — Infrastructure vs domain handling

1. Give the one-question test for sorting a failure into infrastructure vs domain. Apply it to: "card declined by the payment gateway", "applicant under 18", "Redis cache unreachable".
2. Why must `PersistenceException` *not* extend `DomainException`? What bug becomes possible if it does?
3. What is the purpose of passing the `PDOException` as `$previous` when wrapping? Who consumes it?
4. A domain service contains `catch (PDOException $e)`. What rule does that violate, and how do you fix it?
5. Why does the infrastructure failure get a generic `500` while the domain failure gets a specific `422`? What would leaking the infrastructure message risk?

---

## ✅ Lesson 3.1 checklist

- [ ] Categorise the five Bond failure scenarios as infrastructure or domain
- [ ] Understand the two base types (`DomainException` vs `PersistenceException`)
- [ ] Run both examples; confirm the wrap and the two-way routing
- [ ] **Code Challenge:** wrap a `PDOException` until `php challenge/verify.php` is all green
- [ ] Confirm no infrastructure exception type is imported in `src/Domain/`
- [ ] Answer the five quiz questions

---

## 📂 Files in this lesson

```
lesson-3.1-infrastructure-vs-domain-exceptions/
├── README.md                          ← You are here
├── autoload.php                       ← tiny PSR-4 autoloader (Bond\ -> src/)
├── src/
│   ├── Domain/Exception/
│   │   ├── DomainException.php                       ← abstract base (business failures)
│   │   └── ApplicantHasInsufficientIncomeException.php
│   ├── Infrastructure/
│   │   ├── Exception/PersistenceException.php        ← infra failure (NOT a DomainException)
│   │   └── Persistence/SafeBondApplicationStore.php  ← wraps PDOException at the boundary
│   └── Http/ErrorResponder.php                        ← the edge: routes by category
├── examples/
│   ├── 01-two-failure-categories.php  ← 422 (business) vs 500 (technical)
│   └── 02-wrapping-at-the-boundary.php ← PDOException never escapes
└── challenge/
    ├── SafeBondApplicationStore.php   ← implement the wrap
    ├── solution/SafeBondApplicationStore.php
    └── verify.php                     ← behaviour-based self-checker
```

---

*Next lesson: **Lesson 3.2 — Hierarchical Exception Design**, where `DomainException` becomes the root of a deliberate tree — `DomainException → BondApplicationException → ApplicantHasInsufficientIncomeException` and siblings — so the edge can catch a whole bounded context at once or a single leaf for special handling.*
