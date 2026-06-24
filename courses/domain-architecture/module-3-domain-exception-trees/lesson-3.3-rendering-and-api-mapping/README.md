# Lesson 3.3 — Rendering & API Boundary Mapping
> **Module 3 — Domain Exception Trees** · 🏁 Course finale
> **Folder:** `lesson-3.3-rendering-and-api-mapping/`

> **Catch the domain's meaning at the edge. Translate it into a contract the client trusts.** Everything converges here: the tree from 3.2 meets a Slim error handler that maps each exception to an HTTP status and an RFC 7807 body — safe, specific, and leak-free.

---

## Why this lesson exists

You have a typed exception tree (3.2) and a clean split from infrastructure failures (3.1). The last piece is the **boundary translation**: the one place that turns a thrown exception into an HTTP response. Get it right and the domain stays pure (it never mentions HTTP), the client gets precise, machine-readable errors, and your stack traces never leak.

By the end of this lesson you will be able to:

1. Build a Slim error handler that renders domain exceptions at the HTTP boundary.
2. Map exception **type → status** in a single table (422 / 409 / 404 / 500).
3. Emit **RFC 7807 Problem Details** with safe `context`, never internals.
4. Suppress stack traces in production (`APP_DEBUG`) while logging full detail server-side.
5. Wire it into Slim Killer's `addErrorMiddleware(...)`.

> **Golden Rule in focus:** **Rule E** — the domain raises meaning; the **edge** decides what the client sees.

---

## RFC 7807 Problem Details

RFC 7807 is a tiny standard for HTTP error bodies, served as `application/problem+json`:

| Member | Meaning |
|--------|---------|
| `type` | a URI reference identifying the problem kind (we use `/problems/<slug>`) |
| `title` | a short, human summary of the problem kind |
| `status` | the HTTP status code |
| `detail` | a human explanation specific to this occurrence (our safe domain message) |
| *extension members* | anything else safe — we add `context` (domain) or `reference` (server errors) |

Using a standard means clients can parse errors uniformly instead of screen-scraping ad-hoc JSON.

---

## The translation layer (one place)

[`ProblemDetailsMapper`](src/Http/Problem/ProblemDetailsMapper.php) is the entire policy, and it is the **only** place that knows the type→status table:

```php
$status = match (true) {
    $e instanceof ApplicationNotFoundException         => 404,
    $e instanceof ApplicationAlreadySubmittedException => 409,
    $e instanceof BondApplicationException             => 422, // income, over-leverage, future leaves
    $e instanceof DomainException                      => 422, // other contexts' business failures
    default                                             => 500, // infrastructure / unexpected
};
```

- **Domain failures** build a safe body from the exception's message and (for bond leaves) its `context()`. These are safe because the domain *wrote them to be shown*.
- **Everything else** gets a generic `500` body with a `reference` and **no** internals — unless `APP_DEBUG` is on, which adds a `debug` member for developers only.

Adding a new domain error is a one-line change here — or **zero** lines, if it already falls under `BondApplicationException → 422`.

---

## 💻 Example 01 — Type → status → body

```bash
cd courses/domain-architecture/module-3-domain-exception-trees/lesson-3.3-rendering-and-api-mapping
php examples/01-problem-details.php
```

```
ApplicantHasInsufficientIncomeException -> HTTP 422
{
    "type": "/problems/applicant-has-insufficient-income",
    "title": "Bond application rejected",
    "status": 422,
    "detail": "Applicant income does not support the required instalment.",
    "context": {
        "application_id": "111...",
        "monthly_income_cents": 3000000,
        "required_instalment_cents": 1333038
    }
}

ApplicationAlreadySubmittedException -> HTTP 409   (conflict)
ApplicationNotFoundException        -> HTTP 404
```

And the infrastructure failure, with a secret in its message, stays generic:

```
Unexpected RuntimeException (APP_DEBUG=false) -> generic, no leak:
{
    "type": "/problems/internal-error",
    "title": "Something went wrong",
    "status": 500,
    "reference": "err_920fb165"
}
```

The DSN and password in the exception message **never reach the body** — only a reference does. With `APP_DEBUG=true`, a `debug` member is added for developers (never in production).

---

## 💻 Example 02 — End to end through REAL Slim (the capstone)

A genuine Slim 4 pipeline — `addErrorMiddleware` + our handler — driven by a synthetic request (no socket). This is the exact wiring you add to `public/index.php`:

```bash
php examples/02-through-real-slim.php
```

```
POST /apply
  -> 422 ; Content-Type: application/problem+json
  body: { "type": "/problems/applicant-has-insufficient-income", "title": "Bond application rejected",
          "status": 422, "detail": "Applicant income does not support the required instalment.",
          "context": { "application_id": "111...", "monthly_income_cents": 3000000, "required_instalment_cents": 1333038 } }

GET /boom
  -> 500 ; Content-Type: application/problem+json
  body: { "type": "/problems/internal-error", "title": "Something went wrong", "status": 500, "reference": "err_fb2667be" }

Server-side log (full detail, never sent to the client):
  • [Bond\Domain\Exception\ApplicantHasInsufficientIncomeException] Applicant income does not support ... in ...:50
  • [RuntimeException] SQLSTATE[HY000] connect failed: host=10.0.0.5 password=hunter2 in ...:53
```

The over-leveraged `POST /apply` returns a clean, specific `422` a loan-officer UI can display verbatim. The `GET /boom` infrastructure failure returns a generic `500` + reference — and the DSN/password live **only** in the server log, exactly as `addErrorMiddleware(false, ...)` (production posture) intends.

---

## Wiring into Slim Killer

The handler matches Slim's error-handler signature, so wiring it into `public/index.php` is a few lines:

```php
use Bond\Http\Slim\DomainErrorHandler;            // (App\Http\... in the real app)

// public/index.php — after $app is built:
$debug = getenv('APP_DEBUG') === 'true';
$errorMiddleware = $app->addErrorMiddleware($debug, true, true);

$handler = new DomainErrorHandler($app->getResponseFactory(), $debug);
$errorMiddleware->setDefaultErrorHandler($handler);   // one handler; the mapper decides the rest
```

The domain throws; Slim's middleware catches; the handler logs and renders. The domain layer never imports a single HTTP class — Rule E, fully realised.

---

## 🏗️ Code Challenge — Build the engine

Open [`challenge/ProblemDetailsMapper.php`](challenge/ProblemDetailsMapper.php) and implement `map()`:

1. Type → status via one `match` (404 / 409 / 422 / 422 / 500).
2. Domain failures: `type`, `title`, `status`, `detail` (the message), plus `context` for bond leaves.
3. Server failures: generic body + `reference`, **no** `detail`/`context`, and a `debug` member only when `$debug` is true.

```bash
php challenge/verify.php              # checks YOUR implementation
php challenge/verify.php --solution   # the reference solution (always green)
```

A correct engine passes all seven — including the leak check:

```
Verifying ProblemDetailsMapper (solution)

  [PASS] insufficient income -> 422 with type, detail and context
  [PASS] over-leverage -> 422 with context
  [PASS] already submitted -> 409
  [PASS] not found -> 404
  [PASS] unexpected throwable -> 500 generic with a reference
  [PASS] 500 body LEAKS NOTHING (no detail/context, no secret in the body)
  [PASS] 500 includes a debug block ONLY when debug=true

------------------------------------------------------------
ALL 7 CHECKS PASSED ✅
```

Reference: [`challenge/solution/ProblemDetailsMapper.php`](challenge/solution/ProblemDetailsMapper.php).

---

## 📂 Files in this lesson

```
lesson-3.3-rendering-and-api-mapping/
├── README.md                          ← You are here
├── autoload.php                       ← tiny PSR-4 autoloader (Bond\ -> src/)
├── src/
│   ├── Shared/Exception/DomainException.php
│   ├── Domain/Exception/                              ← the tree from 3.2 + ApplicationNotFound (404)
│   │   ├── BondApplicationException.php
│   │   ├── ApplicantHasInsufficientIncomeException.php
│   │   ├── BondAmountExceedsPropertyValueException.php
│   │   ├── ApplicationAlreadySubmittedException.php
│   │   └── ApplicationNotFoundException.php
│   └── Http/
│       ├── Problem/
│       │   ├── ProblemDetails.php                     ← RFC 7807 DTO
│       │   └── ProblemDetailsMapper.php               ← the single translation table
│       └── Slim/DomainErrorHandler.php                ← thin Slim adapter (logs + renders)
├── examples/
│   ├── 01-problem-details.php         ← framework-free: type -> status -> body
│   └── 02-through-real-slim.php       ← REAL Slim pipeline, synthetic request (needs vendor/)
└── challenge/
    ├── ProblemDetailsMapper.php       ← build the engine
    ├── solution/ProblemDetailsMapper.php
    └── verify.php                     ← behaviour-based self-checker (incl. a leak check)
```

> `examples/02` requires the project's Composer dependencies (Slim). If `vendor/` is missing it prints a hint and exits cleanly. Everything else — and the whole challenge — is framework-free.

---

## 🧠 Quiz — Boundary translation and RFC 7807

1. Why does the type→status mapping live at the HTTP boundary instead of on the exceptions themselves?
2. What does `application/problem+json` tell a client, and why is a standard shape better than ad-hoc error JSON?
3. Why is it safe to put a domain exception's `getMessage()` in `detail`, but never an infrastructure exception's?
4. `ApplicationAlreadySubmittedException` maps to `409`, not `422`. Why might you choose a different status for that leaf?
5. With `APP_DEBUG=false`, where does the DSN/password from a `PDOException` end up, and where does it not?
6. A new `PropertyInFloodZoneException` bond leaf is added. What, if anything, must change in the mapper for it to return a correct `422` with context?

---

## ✅ Lesson 3.3 checklist

- [ ] Build a Slim error handler for `DomainException`
- [ ] Map each exception type to a status via a single `match`
- [ ] Emit RFC 7807 Problem Details with safe context only
- [ ] Run `examples/02` and verify no stack trace leaks at `APP_DEBUG=false`
- [ ] Confirm full detail is still logged server-side
- [ ] **Code Challenge:** build the mapper until `php challenge/verify.php` is all green (7/7)
- [ ] Answer the six quiz questions

---

## 🎓 Course complete — Advanced Domain Architecture & Tactical DDD

You started with a flat `array $data`, a `(float) bond_amount`, and a `SubmitApplicationAction` that returned `false`. You finish with:

- **Module 1** — rich, self-validating value objects and entities (`Money`, `Percentage`, `ApplicationId`, a guarded `BondApplication`).
- **Module 2** — a `BondApplication` aggregate behind a consistency boundary, a pure `BondApplicationRepository` interface, and a stateless `AffordabilityService`.
- **Module 3** — a typed exception tree (`DomainException → BondApplicationException → leaves`) and an RFC 7807 boundary that turns business failures into precise, leak-free HTTP responses.

> **The final diagnostic check:** delete Slim, Twig, Pixie, and Apache. Everything under `src/Domain/` and `src/Shared/` still compiles, still tests, and still makes perfect sense to a loan officer — while the HTTP layer merely *adapts* to it. That is the whole discipline. You are now architecting enterprise-grade software, not scripting against a framework.

*Re-run the Lesson 2.0 dependency audit one last time: a clean domain is the proof.*
