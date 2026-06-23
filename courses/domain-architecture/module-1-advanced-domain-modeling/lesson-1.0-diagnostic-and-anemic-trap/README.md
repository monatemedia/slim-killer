# Lesson 1.0 — The Diagnostic Question & The Anemic Trap
> **Module 1 — Advanced Domain Modeling** · ⭐ Start here
> **Folder:** `lesson-1.0-diagnostic-and-anemic-trap/`

> **Read this lesson and audit the current Bond flow before writing a single new class.** Everything else in this course is built on the one question you learn here.

---

## Why this lesson exists

You cannot build a domain layer until you can *see* that you don't have one. Right now the Bond Application in Slim Killer looks finished — it accepts a form, saves a row, redirects to `/success`. But it has **no domain model at all**: it has infrastructure (a controller, a repository, a database table) wearing a domain's clothes.

This lesson teaches the single tool that lets you tell the difference for the rest of your career: **the Diagnostic Architectural Question.** Then you apply it to the real files in this repo and prove, line by line, that the "domain" folder currently contains no domain.

By the end you will be able to:

1. State the Diagnostic Architectural Question and use it to sort any class into **Domain** or **Infrastructure**.
2. Define an **anemic domain model** and explain why it is an anti-pattern, not a style.
3. Audit Slim Killer's live Bond flow and label every file correctly.
4. Explain why returning `false` (or a raw `array`) from a "domain" method is a design smell.

---

## 👑 The Diagnostic Architectural Question

> **"If I delete my framework, my database driver, and my web server tomorrow morning, does the code inside this file still make perfect sense to a business manager at a bond originator?"**
>
> - **YES** → it belongs in the **Domain Layer** (`src/Domain/`) — Entities, Value Objects, Domain Services, Domain Exceptions, repository *interfaces*.
> - **NO** → it belongs in the **Infrastructure / HTTP Layer** (`src/Infrastructure/`, `src/Http/`) — Controllers, Pixie/PDO drivers, repository *implementations*, error renderers.

Say it out loud before you write any class in this course. It is not a metaphor — it is a literal sorting function. A `Money` object passes (a loan officer understands "one million two hundred thousand rand"). A `PixieBondApplicationRepository` fails (a loan officer has never heard of Pixie, PDO, or a prepared statement — those are *how we keep records*, not *what the business is*).

---

## What "anemic" means

An **anemic domain model** is an object that holds data but contains no behaviour — a bag of public getters and setters that some *other* class operates on. The data and the rules that protect it live in different places, so the rules are easy to forget, bypass, or duplicate.

| | Anemic model | Rich model |
|---|---|---|
| **Where is the data?** | In the object | In the object |
| **Where are the rules?** | In a separate "service" / scattered everywhere | In the object that owns the data |
| **Can you build an invalid one?** | Yes — validation is "someone else's job" | No — the object refuses to exist in an invalid state |
| **Speaks the business?** | No — it speaks `array`, `string`, `float`, `bool` | Yes — it speaks `Money`, `ApplicationId`, `approve()` |

> The trap is that an anemic model *looks* object-oriented — it has classes! — while actually being procedural code with the data smeared across a few structs. Martin Fowler named it an anti-pattern precisely because it throws away the main benefit of objects: **bundling data with the rules that keep it valid.**

---

## 🔬 Audit: the live Bond flow is anemic

Open these four real files and run each through the Diagnostic Question. (They are the current, unmodified Bond Application.)

| File | What it does | Diagnostic Question | Verdict |
|------|--------------|---------------------|---------|
| `src/Http/Application/ProcessApplyController.php` | `$request->getParsedBody()` → hands a raw `array` to the action | "Does a loan officer know what `getParsedBody()` is?" → **NO** | Infrastructure (correctly — it's an HTTP adapter) |
| `src/Domain/Application/SubmitApplicationAction.php` | `execute(array $data): bool` | "Does `array in, bool out` express any bond rule?" → **NO** | **Mislabelled** — sits in `Domain/` but is anemic glue |
| `src/Infrastructure/Persistence/Application/ApplicationRepository.php` | `create(array $data)`, `(float) $data['bond_amount']` | "Does a loan officer know SQL / Pixie?" → **NO** | Infrastructure (correctly) — but money is a `float` |
| `database/migrations/..._create_applications_table.php` | `bond_amount DECIMAL(15,2)`, flat columns | "Is a table schema a business model?" → **NO** | Infrastructure — and it is currently our *only* "model" |

### The damning conclusion

After the audit, count what's left in the **Domain** column: **nothing.** There is no `Money`, no `BondApplication`, no `ApplicationId`, no business rule that lives in an object. `SubmitApplicationAction` is parked under `src/Domain/` but speaks only `array` and `bool` — and on this branch it even carries the broken legacy `namespace App\Actions`. The database table is doing the job a domain model should do. **That is the anemic trap.**

---

## 💻 Run the example

The example is a faithful, self-contained miniature of the flow above so you can watch the diseases without a database.

```bash
cd courses/domain-architecture/module-1-advanced-domain-modeling/lesson-1.0-diagnostic-and-anemic-trap
php examples/01-anemic-bond-flow.php
```

Expected output:

```
=== Example 01 — The Anemic Bond Flow ===

A. Normal application saved?            true
B. Garbage application ALSO saved?      true (!!)
C. If it had failed, the caller gets:   only `false` — no reason, no rule name.
```

Three diseases, all tolerated by the runtime:

- **A — No business vocabulary.** The "domain" speaks `array` and `bool`. Nothing in a signature mentions a bond, an applicant, or money.
- **B — Illegal states flow straight through.** An empty name, a non-email, and a **negative** R5,000,000 bond all save successfully. There is no door that could have stopped them.
- **C — Failure throws away its reason.** `false` tells the caller *that* something failed, never *what rule* failed — so the UI can't tell the applicant anything useful. (Module 3 fixes this with typed exceptions.)

---

## ✍️ Do the audit yourself

1. Open each of the four files in the audit table.
2. For every method signature, write the Diagnostic Question's answer in the margin: Domain or Infrastructure.
3. Find the line `(float) $data['bond_amount']` in `ApplicationRepository`. Write one sentence on why representing money as a `float` will eventually lose a cent (you proved it in `examples/01-primitive-obsession.php` of Lesson 1.2 — `0.1 + 0.2 !== 0.3`).
4. Decide where a rule like *"a bond amount must be positive"* could live **today**. Discover there is no good home for it — which is exactly the gap Lessons 1.1–1.3 fill.

---

## 🧠 Quiz — Spotting anemic models

1. A class has only `public` properties plus `getX()`/`setX()` for each, and all the rules live in a `FooService`. Domain or anemic? Why?
2. Apply the Diagnostic Question to `Slim\Views\Twig`. Which layer, and how do you know?
3. Why is `execute(array $data): bool` unable to express the rule "the applicant's income must support the instalment"?
4. The `applications` table has a `bond_amount DECIMAL(15,2)` column. Why is a *database column type* not a substitute for a `Money` value object?
5. True or false: putting a class inside `src/Domain/` makes it a domain object. (Defend your answer using `SubmitApplicationAction`.)

---

## ✅ Lesson 1.0 checklist

- [ ] Read the full lesson and the master `README.md` Diagnostic Question section
- [ ] Run `examples/01-anemic-bond-flow.php` and observe diseases A, B, and C
- [ ] Open all four live files and classify each method as Domain or Infrastructure
- [ ] Write, in one sentence, why `(float) $data['bond_amount']` violates **Rule B** (illegal states unrepresentable)
- [ ] Confirm for yourself that the Domain column of the audit is currently **empty**
- [ ] Answer the five quiz questions from memory

---

*Next lesson: **Lesson 1.1 — Rich vs. Anemic Domain Models**, where we start moving rules INTO the objects that own the data — then **Lesson 1.2** builds the first real domain objects: `Money` and `Percentage`.*
