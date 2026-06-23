# Module 3 — Domain Exception Trees
> **Advanced Domain Architecture & Tactical DDD**
> **Folder:** `module-3-domain-exception-trees/`

---

## Module Goal

Stop treating exceptions as plain error strings. Build a **typed object hierarchy** that speaks the language of bond origination, then map it at the framework boundary into precise HTTP responses **without leaking stack traces, SQL, or system internals**. This is where the Bond Application stops returning a silent `false` or a generic `500` and starts telling a loan officer exactly which business rule failed.

By the end of this module you will be able to:

1. Separate **infrastructure exceptions** (`PDOException`, connection loss) from **domain exceptions** (business-rule violations).
2. Design an explicit **exception tree** rooted at an abstract `DomainException`.
3. Throw granular, named errors (`ApplicantHasInsufficientIncomeException`) deep in the domain while catching broad groups at the boundary.
4. Map domain exceptions to **RFC 7807 Problem Details** responses inside a Slim error handler — with zero secret leakage.
5. Carry safe, structured context on the exception (which rule, which values) for precise client messaging.

> **Golden Rule in focus:** **Rule E** (exceptions speak business, then get translated).

---

## 🆕 PHP 8.5 Features in This Module

| Feature | Lesson | What it does for the domain |
|---------|--------|-----------------------------|
| `#[NoDiscard]` attribute | **3.2** | Ensure a constructed/validated exception is actually thrown, not dropped |
| `#[Override]` | **3.2** | Verify exception subclasses correctly override context/message hooks |
| `never` return type | **3.2** | Guard helpers that always throw a domain exception |
| Backed enums | **3.3** | Map exception types → HTTP status codes / error slugs cleanly |
| `readonly` classes | **3.1** | Immutable, context-carrying exception payloads |

---

## 📁 Module Structure

```
module-3-domain-exception-trees/
├── README.md                                   ← You are here
├── lesson-3.0-why-generic-exceptions-leak/
├── lesson-3.1-infrastructure-vs-domain-exceptions/
├── lesson-3.2-hierarchical-exception-design/
└── lesson-3.3-rendering-and-api-mapping/
```

---

## Lesson 3.0 — Why Generic Exceptions Leak ⭐ Start here

> **A naked stack trace is a security incident.**

### Topics
- What a generic `\Exception` / `\RuntimeException("error 42")` costs you: no type to catch, no business meaning, no safe message.
- How Slim Killer behaves today: `APP_DEBUG=true` renders full stack traces; a thrown error in the Bond flow leaks file paths, SQL, and class internals to the browser.
- The boundary principle: the domain *raises* meaning; the edge *decides* what the outside world sees.
- Why returning `false` from `SubmitApplicationAction` is just as bad — it discards the *reason* for failure.

### Bond focus
Trigger a failure in the current `/apply` flow and observe what leaks. Establish the goal: an over-leveraged application should produce a clean, intentional `422` — never a framework stack trace.

### Lesson checklist
- [ ] Reproduce a leaking error in the current Bond flow with `APP_DEBUG=true`
- [ ] List every internal detail exposed in the trace
- [ ] Explain why `return false` loses information a typed exception preserves
- [ ] **Quiz:** The cost of generic exceptions

---

## Lesson 3.1 — Infrastructure vs. Domain Exceptions

> **A dropped database connection is not a business rule. Don't model it as one.**

### Topics
- Two fundamentally different failure categories:
  - **Infrastructure**: `PDOException`, network timeouts, disk errors — *technical*, retryable, opaque to the business.
  - **Domain**: `ApplicantHasInsufficientIncomeException` — *business*, meaningful, intentional.
- Where each is caught: infrastructure failures → log + generic `500`; domain failures → translate to a specific client response.
- Never letting infrastructure exception types escape the infrastructure layer into the domain.
- Wrapping/translating at the persistence boundary so the domain only ever sees domain concepts.

### PHP 8.5 in practice

```php
// Infrastructure catches the technical failure; the domain never sees PDO
public function ofId(ApplicationId $id): ?BondApplication
{
    try {
        $row = $this->db->table('applications')->find($id->value);
    } catch (\PDOException $e) {
        // log it; re-raise as an infrastructure-level concern, NOT a domain one
        throw new PersistenceUnavailableException(previous: $e);
    }
    return $row ? $this->toAggregate($row) : null;
}
```

### Bond focus
Classify every failure the Bond Application can hit. "Database is down" and "applicant earns too little" must travel different paths and surface differently to the client.

### Lesson checklist
- [ ] Categorise five Bond failure scenarios as infrastructure or domain
- [ ] Wrap a `PDOException` at the persistence boundary
- [ ] Confirm no infrastructure exception type is imported in `src/Domain/`
- [ ] **Quiz:** Infrastructure vs domain failure handling

---

## Lesson 3.2 — Hierarchical Exception Design

> **Throw narrow. Catch wide.**

### Topics
- The exception tree: an abstract root → bounded-context node → concrete leaf errors.
- `abstract class DomainException extends \Exception` (in `src/Shared/Exception/`).
- `abstract class BondApplicationException extends DomainException` — the bounded-context node.
- Concrete leaves: `ApplicantHasInsufficientIncomeException`, `BondAmountExceedsPropertyValueException`, `ApplicationAlreadySubmittedException`.
- Carrying **safe structured context** (which rule, which values) without leaking internals.
- Catching at the right altitude: a handler can `catch (BondApplicationException)` for the whole context, or a specific leaf for special handling.

### PHP 8.5 in practice

```php
// src/Shared/Exception/DomainException.php
abstract class DomainException extends \Exception {}

// src/Domain/Bond/Exception/BondApplicationException.php
abstract class BondApplicationException extends DomainException
{
    /** Safe, client-facing context — never raw internals. */
    abstract public function context(): array;
}

// A concrete, business-speaking leaf
final class ApplicantHasInsufficientIncomeException extends BondApplicationException
{
    public function __construct(
        private readonly ApplicationId $id,
        private readonly Money $monthlyIncome,
        private readonly Money $requiredInstalment,
    ) {
        parent::__construct('Applicant income does not support the requested bond.');
    }

    #[\Override]
    public function context(): array
    {
        return [
            'application_id'     => $this->id->value,
            'monthly_income'     => $this->monthlyIncome->cents,
            'required_instalment'=> $this->requiredInstalment->cents,
        ];
    }
}
```

### Bond focus
Build the full bond exception tree. `AffordabilityService` from Module 2 now throws `ApplicantHasInsufficientIncomeException` with the actual income and instalment figures attached as safe context.

### Lesson checklist
- [ ] Implement the abstract `DomainException` root in `src/Shared/Exception/`
- [ ] Implement the `BondApplicationException` bounded-context node
- [ ] Implement three concrete leaf exceptions
- [ ] Attach safe structured context (no stack traces, no SQL)
- [ ] Use `#[Override]` on the context hook and `never` on a guard helper
- [ ] **Quiz:** Throw-narrow / catch-wide and tree depth

---

## Lesson 3.3 — Rendering & API Boundary Mapping

> **Catch the domain's meaning at the edge. Translate it into a contract the client trusts.**

### Topics
- Catching domain exceptions at the **HTTP boundary** — a Slim custom error handler / middleware, not in the domain.
- Mapping exception type → HTTP status: business-rule violation → `422 Unprocessable Entity`; not-found aggregate → `404`; infrastructure failure → generic `500`.
- Emitting **RFC 7807 Problem Details** (`type`, `title`, `status`, `detail`, plus safe `context`).
- Suppressing stack traces in production (`APP_DEBUG`) while still logging the full detail server-side.
- Keeping the translation table in one place (an enum or map), so adding a new domain error is a one-line registration.

### PHP 8.5 in practice

```php
// src/Http/ErrorHandler/DomainExceptionRenderer.php (registered on Slim's error middleware)
final class DomainExceptionRenderer
{
    public function __invoke(Request $request, \Throwable $e, bool $displayErrorDetails): Response
    {
        [$status, $title] = match (true) {
            $e instanceof ApplicantHasInsufficientIncomeException => [422, 'Affordability check failed'],
            $e instanceof BondApplicationException                => [422, 'Bond application rejected'],
            default                                               => [500, 'Internal error'],
        };

        $body = ['type' => '/errors/' . (new \ReflectionClass($e))->getShortName(),
                 'title' => $title, 'status' => $status];

        if ($e instanceof BondApplicationException) {
            $body['context'] = $e->context(); // safe fields only — no trace, no SQL
        }
        // ... write RFC 7807 JSON, log full detail server-side ...
    }
}
```

### Bond focus
Wire the renderer into Slim Killer's `addErrorMiddleware(...)` in `public/index.php`. Now a `POST /apply` from an over-leveraged applicant returns a clean `422` Problem Details payload that the loan officer's UI can display verbatim — while the full diagnostic is logged, not leaked.

### Lesson checklist
- [ ] Build a Slim custom error handler for `DomainException`
- [ ] Map each exception type to a status code via a single `match`/enum
- [ ] Emit RFC 7807 Problem Details with safe `context` only
- [ ] Verify no stack trace leaks when `APP_DEBUG=false`
- [ ] Confirm full detail is still logged server-side
- [ ] **Code Challenge:** Complete bond exception engine + translation layer mapping every leaf to a precise API payload, wired into Slim Killer's error middleware
- [ ] **Quiz:** Boundary translation and RFC 7807

---

## ✅ Module 3 Completion Checklist

- [ ] Lesson 3.0 — Why generic exceptions leak (leak reproduced and understood)
- [ ] Lesson 3.1 — Infrastructure vs domain exceptions (categories separated)
- [ ] Lesson 3.2 — Hierarchical design (`DomainException` → `BondApplicationException` → leaves)
- [ ] Lesson 3.3 — Rendering & API mapping (RFC 7807 via Slim middleware)
- [ ] **Code Challenge complete:** full exception engine + translation layer
- [ ] **Bond Milestone reached:** over-leveraged application returns a clean `422` business message — never a stack trace

---

## 🎓 Course Completion

With Module 3 finished, the Bond Application is fully transformed:

- **Module 1** gave it rich, self-validating nouns (`Money`, `Percentage`, `ApplicationId`).
- **Module 2** gave it a guarded `BondApplication` aggregate, a pure repository interface, and the `AffordabilityService`.
- **Module 3** gave it a typed exception tree that speaks the business and a boundary that translates failures into safe API responses.

> **Final diagnostic check:** delete Slim, Twig, Pixie, and Apache. Everything in `src/Domain/Bond/` and `src/Shared/` still compiles, still tests, and still makes perfect sense to a loan officer. You are now architecting enterprise-grade software — not scripting against a framework.
