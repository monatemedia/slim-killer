# Lesson 3.0 — Why Generic Exceptions Leak
> **Module 3 — Domain Exception Trees** · ⭐ Start here
> **Folder:** `lesson-3.0-why-generic-exceptions-leak/`

> **A naked stack trace is a security incident.** Module 2 produced our first typed exception (`ApplicantHasInsufficientIncomeException`). Before we build the rest of the tree, you need to feel — concretely — what goes wrong when exceptions are *generic*, *leaky*, or replaced by a bare `false`.

---

## Why this lesson exists

Right now the Bond Application reports failure in the two worst possible ways:

- **Infrastructure failures leak.** `public/index.php` runs `addErrorMiddleware((getenv('APP_DEBUG') === 'true'), …)`, and `.env` ships `APP_DEBUG=true`. So any uncaught exception in the `/apply` flow renders its full type, message, file path, and stack trace straight to the browser.
- **Business failures vanish.** `SubmitApplicationAction::execute(): bool` returns `false` when something goes wrong — discarding *why* it failed, so the UI can tell the applicant nothing useful.

This lesson reproduces both, names the damage, and sets the goal for the module: **the domain raises meaning; the edge decides what the outside world sees.**

By the end you will be able to:

1. Reproduce a leaking error in the Bond flow and **inventory every internal detail** it exposes.
2. Explain why a generic `\Exception`/`\RuntimeException` gives you *no type to catch, no business meaning, and an unsafe message*.
3. Explain why returning `false` is just as bad — it throws away the reason a typed exception would preserve.

> **Golden Rule in focus:** **Rule E** — exceptions speak business, then get translated *at the boundary*.

---

## The boundary principle

```
   DOMAIN  ── raises ──▶  a meaningful failure (a typed exception)
                                   │
                                   ▼
   EDGE (HTTP layer) ── decides ──▶ what the client sees (status + safe payload)
                                   └─ logs the full detail server-side
```

Two different jobs: the **domain** says *what* went wrong in business terms; the **edge** decides *how much* of that the outside world is allowed to see. Generic, leaky exceptions collapse those jobs together — the raw failure becomes the response, and your internals become public.

---

## 💻 Example 01 — Reproduce the leak

A real `PDOException` (insert into a table that was never migrated), rendered the way Slim Killer renders it with `APP_DEBUG=true`:

```bash
cd courses/domain-architecture/module-3-domain-exception-trees/lesson-3.0-why-generic-exceptions-leak
php examples/01-the-leak.php
```

```
HTTP/1.1 500 Internal Server Error      <-- what the browser receives
Content-Type: text/html

Type:    RuntimeException
Message: Database write failed running [INSERT INTO applications (first_name, last_name, email, bond_amount)
            VALUES (:first, :last, :email, :amount)]: SQLSTATE[HY000]: General error: 1 no such table: applications
File:    C:\xampp\htdocs\slim-killer\...\examples\01-the-leak.php:41
Trace:
#0 ...\examples\01-the-leak.php(57): persistBondApplication()
#1 {main}

What an attacker just learned from that response:
  • Absolute server path:  C:\xampp\htdocs\slim-killer\...\examples\01-the-leak.php
    → reveals the OS, the web root, and the username in the path.
  • The database schema:   table 'applications' with columns first_name, last_name, email, bond_amount
  • The storage engine:    a SQL database (SQLSTATE codes)
  • Internal call stack:   class + method names from the trace above
```

### The leak inventory

| Leaked | From | Why it's dangerous |
|--------|------|--------------------|
| Absolute filesystem path | `$e->getFile()` | Reveals OS, web-root layout, and the account name in the path |
| Full SQL + schema | the embedded query | Hands an attacker your table and column names for crafting injection |
| Storage engine | `SQLSTATE[...]` | Narrows the attack surface to a known database |
| Internal call graph | the stack trace | Maps your classes and methods |

> **The anti-pattern that made it worse:** the code "helpfully" embedded the failing SQL into the exception message (`"...failed running [INSERT ...]: ..."`). Developers do this constantly — and it means the query leaks anywhere the message is shown. This is exactly an OWASP *information disclosure* / improper-error-handling finding.

What the visitor *should* have seen is a generic `500` with a reference id, while the full detail is logged server-side under that id — never rendered. **(That mapping is Lesson 3.3. Distinguishing this infrastructure failure from a business one is Lesson 3.1.)**

---

## 💻 Example 02 — `false` vs generic vs typed

The *same* business decision — an over-leveraged applicant is declined — reported three ways. Watch how much the presentation layer can tell the applicant in each:

```bash
php examples/02-false-loses-the-reason.php
```

```
A) return false:
   The UI knows only: "Your application could not be processed."
   It cannot say WHY, cannot guide the applicant, cannot branch on the reason.

B) throw generic RuntimeException:
   Caught \Throwable — but is this a business decline or a real bug? Unknown.
   The message ('error') is useless to show, and unsafe in general.

C) throw ApplicantHasInsufficientIncomeException:
   Caught the EXACT type — definitely a business decline, not a crash.
   Safe message:   Applicant income does not support the required instalment.
   With context:   income={R30,000.00}, instalment={R13,330.38}
   The UI can say: "Based on a monthly income of R30,000.00, the instalment of R13,330.38 is too high."
```

| Strategy | Type to catch? | Reason preserved? | Safe to show? | Structured data? |
|----------|:--------------:|:-----------------:|:-------------:|:----------------:|
| `return false` | ✗ (it's a bool) | ✗ thrown away | n/a | ✗ |
| `throw new RuntimeException('error')` | ✗ (too generic) | ~ (a vague string) | ✗ | ✗ |
| `throw ApplicantHasInsufficientIncomeException` | ✓ exact type | ✓ | ✓ | ✓ (income, instalment) |

Only the typed exception lets the edge **branch on the failure** (business decline → `422`; bug → `500`), show a **safe, specific** message, and use the **context** to guide the applicant.

---

## ✍️ Do it yourself

1. Run `examples/01-the-leak.php`. Copy the rendered response and circle every piece of information you would not want a stranger to have.
2. Open the real `public/index.php` and find the `addErrorMiddleware(...)` line. Note the first argument is driven by `APP_DEBUG`. What does flipping it to `false` change — and what does it *not* fix?
3. Open `src/Domain/Application/SubmitApplicationAction.php`. Its `execute()` returns `bool`. Write one sentence describing what the `/apply` controller can — and cannot — tell the applicant when it gets `false`.
4. List three distinct business failures a bond application could have (e.g. insufficient income, bond exceeds property value, already submitted). Note that `false` cannot tell them apart, but three exception *types* can.

---

## 🧠 Quiz — The cost of generic exceptions

1. Name four distinct pieces of internal information leaked by the Example 01 response, and the security risk of each.
2. `APP_DEBUG=false` hides the stack trace. Why is that necessary but **not sufficient** for good error handling?
3. Why can't a caller of `execute(): bool` distinguish "insufficient income" from "database was down"? What does that prevent the UI from doing?
4. What three things does a typed domain exception give a caller that `throw new \Exception('error')` does not?
5. State the boundary principle in one sentence: who raises meaning, and who decides what the client sees?

---

## ✅ Lesson 3.0 checklist

- [ ] Run `examples/01-the-leak.php` and reproduce the leaking error
- [ ] List every internal detail exposed in the rendered trace
- [ ] Find the `APP_DEBUG`-driven `addErrorMiddleware(...)` call in `public/index.php`
- [ ] Run `examples/02-false-loses-the-reason.php` and compare the three strategies
- [ ] Explain why `return false` loses information a typed exception preserves
- [ ] Answer the five quiz questions

---

## 📂 Files in this lesson

```
lesson-3.0-why-generic-exceptions-leak/
├── README.md                          ← You are here
└── examples/
    ├── 01-the-leak.php                ← a real PDOException rendered as APP_DEBUG=true would
    └── 02-false-loses-the-reason.php  ← false vs generic vs typed, side by side
```

*(No `challenge/`: Lesson 3.0 is the diagnostic foundation. The build begins in Lesson 3.1, separating infrastructure failures from domain failures.)*

---

*Next lesson: **Lesson 3.1 — Infrastructure vs. Domain Exceptions**, where we sort failures into two streams — a dropped database connection (`PDOException` → log + generic `500`) versus a broken business rule (`ApplicantHasInsufficientIncomeException` → a precise client response) — and make sure infrastructure exception types never escape into the domain.*
