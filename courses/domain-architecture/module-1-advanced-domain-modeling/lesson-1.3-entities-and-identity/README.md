# Lesson 1.3 — Entities & Domain Identity
> **Module 1 — Advanced Domain Modeling**
> **Folder:** `lesson-1.3-entities-and-identity/`

> **Some objects are defined by *who* they are, not *what* they contain.** Lesson 1.2 built objects with no identity (value objects). This lesson builds their mirror image — objects whose identity is the whole point.

---

## Why this lesson exists

A `Money` of R50,000 is interchangeable with any other R50,000 — it has no identity, and that is correct (Lesson 1.2). But a **Bond Application** is not interchangeable: two applications with byte-for-byte identical details are still two *different* applications from two *different* moments, and confusing them would be a serious bug. An application also *changes* over its life (Draft → Submitted → Approved) while remaining "the same application."

That combination — **mutable attributes, stable identity** — is exactly what an **Entity** is. This lesson teaches you to recognise entities, model identity explicitly with an `ApplicationId`, and compare entities by identity instead of by value.

By the end you will be able to:

1. State the **Entity vs Value Object** distinction and classify a concept correctly.
2. Model identity as its own value object (`ApplicationId`) instead of leaking a database auto-increment into the domain.
3. Implement **identity-based equality** so an entity stays "itself" across attribute changes and reloads.
4. Decide *where* identity is generated — and why minting it in the domain matters.

> **Golden Rule in focus:** **Rule A** — the domain owns its concepts (including identity), and imports nothing from the database to get them.

---

## Entity vs Value Object

| | Value Object (Lesson 1.2) | Entity (this lesson) |
|---|---|---|
| Defined by | its attributes (value) | its identity |
| Equality | structural — same value ⇒ equal | identity — same id ⇒ equal |
| Mutable? | no (immutable) | yes — attributes change over its life |
| Example | `Money`, `Percentage` | `BondApplication`, `Applicant` |
| Question you ask | "is it equal?" | "is it the same one?" |

The decisive test: **does this thing have continuity?** If you care that it is "the same one" tomorrow even after its details change, it is an entity and needs an identity. If you only care about its value right now, it is a value object.

---

## Identity as a value object: `ApplicationId`

A subtle but important move: the identity itself is modelled as a small value object ([`src/ApplicationId.php`](src/ApplicationId.php)), not as a raw `int` from the database.

```php
final readonly class ApplicationId
{
    public function __construct(public string $value) { /* validates it is a UUID */ }
    public static function generate(): self { /* mints a UUID v4 in the domain */ }
    public function equals(self $other): bool { return $this->value === $other->value; }
}
```

Why not just use the table's auto-increment `id`?

- **Rule A (the Dependency Rule).** A database-generated id only exists *after* you save. The domain would be unable to give an application an identity until infrastructure granted it one — backwards. Minting a UUID in `generate()` means a `BondApplication` has identity from the instant it is created, with no database round-trip.
- **It keeps the domain pure.** `ApplicationId` is a value object the business understands ("application reference"); `AUTO_INCREMENT` is a storage mechanism it does not.

> This previews **Module 2**: when the repository loads an application back from storage, it *reconstitutes* the same `ApplicationId` — identity survives the trip to the database and back.

---

## Identity-based equality

The entity ([`src/BondApplication.php`](src/BondApplication.php)) compares by identity and **ignores attributes**:

```php
public function equals(self $other): bool
{
    return $this->id->equals($other->id);   // NOT a comparison of status, amount, etc.
}
```

That single line is what makes an Approved application still "the same application" it was as a Draft.

---

## 💻 Run the examples

```bash
cd courses/domain-architecture/module-1-advanced-domain-modeling/lesson-1.3-entities-and-identity
php examples/01-identity-vs-value.php
```

```
=== Example 01 — Identity vs Value ===

ApplicationId (value object):
  same string -> equals():   true
  same string -> ===:        false (different objects — we do not care)

BondApplication (entity):
  app1 status:               draft
  app2 status:               draft  (identical attributes)
  app1->equals(app2):        false (different identities -> different applications)
  app1 id:                   57f906a5-de4e-4984-bca3-eeeb7b9c5479
  app2 id:                   a1447908-ccce-424d-abb8-5bae6cea151d

RULE: ask "equal value?" of a value object; ask "same identity?" of an entity.
```

> Two applications with identical attributes are **not equal** — different identities make them different things. (Your UUIDs will differ each run; they are minted fresh.)

```bash
php examples/02-identity-persists.php
```

```
=== Example 02 — Identity Persists ===

Start:               status=draft  id=91a937f3-76cb-4857-a43d-14f12e9fbb58
After approve():     status=approved  id=91a937f3-76cb-4857-a43d-14f12e9fbb58
Identity unchanged:  true

Reloaded copy (new object, same id):
  app->equals(reloaded):   true (recognised as the same application)
  app === reloaded:        false (different PHP objects — identity equality still holds)
```

This is the heart of the lesson: the status changed and the object was even reconstructed as a brand-new PHP instance, yet `equals()` still recognises it as the same application — because identity, not attributes or object reference, defines it.

---

## 🏗️ Code Challenge — Implement identity-based equality

Open [`challenge/BondApplication.php`](challenge/BondApplication.php). The entity is complete except for `equals()`. Implement it so that:

1. It compares by `ApplicationId` — never by status or any other attribute.
2. Two separately started applications are never equal.
3. An application equals a reconstructed copy carrying the **same** id (the "reloaded from storage" case).
4. Changing an attribute (`approve()`) does not change identity equality.

```bash
php challenge/verify.php              # checks YOUR implementation
php challenge/verify.php --solution   # the reference solution (always green)
```

A correct implementation goes fully green:

```
Verifying BondApplication identity (solution)

  [PASS] Two separately started applications are NOT equal
  [PASS] An application equals itself
  [PASS] Equals a reconstructed copy with the SAME id
  [PASS] Identity equality ignores attribute (status) changes
  [PASS] Different ids are never equal even with identical status

------------------------------------------------
ALL 5 CHECKS PASSED ✅
```

Reference: [`challenge/solution/BondApplication.php`](challenge/solution/BondApplication.php). The whole solution is one line — `return $this->id->equals($other->id);` — because the hard work was already done by the `ApplicationId` value object. Small, correct objects compound.

---

## 📂 Files in this lesson

```
lesson-1.3-entities-and-identity/
├── README.md                          ← You are here
├── src/
│   ├── ApplicationId.php              ← identity AS a value object (UUID v4)
│   ├── ApplicationStatus.php          ← lifecycle states (enum)
│   └── BondApplication.php            ← the entity (identity-based equality)
├── examples/
│   ├── 01-identity-vs-value.php       ← entity identity vs VO value equality
│   └── 02-identity-persists.php       ← identity survives changes & reloads
└── challenge/
    ├── BondApplication.php            ← implement equals()
    ├── solution/BondApplication.php   ← reference solution
    └── verify.php                     ← behaviour-based self-checker
```

---

## 🧠 Quiz — Identity vs structural equality

1. Give the one-question test that tells you whether a concept is an entity or a value object. Apply it to: `EmailAddress`, `Applicant`, `Money`, `BondApplication`.
2. Why does the domain mint its own `ApplicationId` (a UUID) instead of using the database's `AUTO_INCREMENT` id? Tie your answer to the Dependency Rule.
3. Why is `ApplicationId` itself a *value object* even though it represents an entity's identity?
4. `app->equals(reloaded)` is `true` while `app === reloaded` is `false`. Explain why both are correct and why we rely on `equals()`, not `===`.
5. If `equals()` compared status as well as id, what bug would appear the moment an application is approved?

---

## ✅ Lesson 1.3 checklist

- [ ] Articulate the Entity vs Value Object distinction with a bond example
- [ ] Understand `ApplicationId` as identity modelled as a value object
- [ ] Run both examples; confirm identity survives an attribute change and a reload
- [ ] Decide where IDs are generated (domain vs infrastructure) and why
- [ ] **Code Challenge:** implement identity-based `equals()` until `php challenge/verify.php` is all green
- [ ] Answer the five quiz questions

---

## 🎯 Module 1 complete

With Lessons 1.0–1.3 done, the Bond Application now has real domain building blocks:

- **1.0** — you can tell domain from infrastructure and spot anemia.
- **1.1** — a rich `BondApplication` that guards its own lifecycle.
- **1.2** — self-validating `Money` and `Percentage` value objects (no more `float`).
- **1.3** — a stable `ApplicationId` identity that survives change and persistence.

> **Next: Module 2 — DDD Tactical Patterns.** These scattered objects get clustered into a single **`BondApplication` aggregate** with a strict consistency boundary, the database is exiled behind a **pure repository interface**, and multi-entity logic moves into the **`AffordabilityService`**.
