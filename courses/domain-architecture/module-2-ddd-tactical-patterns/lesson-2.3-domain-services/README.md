# Lesson 2.3 — Domain Services
> **Module 2 — DDD Tactical Patterns**
> **Folder:** `lesson-2.3-domain-services/`

> **When the logic belongs to no single entity, it gets its own stateless home.** Lessons 2.1–2.2 gave us a guarded aggregate and a pure repository. This lesson handles the rule that doesn't fit *inside* any one object — and produces the typed exception Module 3 will turn into an HTTP response.

---

## Why this lesson exists

The headline bond rule is affordability: **the monthly instalment may not exceed 30% of the applicant's monthly income.** Where should that live?

- Not on `Money` or `Percentage` — they don't know about applications or lending policy.
- Not on `IncomeSource` — it's one line item; the rule needs the *total*.
- Not even cleanly on `BondApplication` — the rule also needs the interest rate, the term, and a *policy threshold* that the bank sets and changes. Forcing all that into the aggregate would bloat it with lending-product concerns it shouldn't own.

When a rule spans multiple objects and belongs to none, it gets a **domain service**: a stateless object that takes its inputs as parameters and returns a result (or throws a typed exception). That is `AffordabilityService`.

By the end of this lesson you will be able to:

1. Recognise when a rule needs a **domain service** rather than an entity method.
2. Implement a service that is **stateless** — inputs in, result out.
3. Compose a business **policy** as an injectable callable.
4. Return a **typed result** *or* throw a **typed domain exception** — the bridge to Module 3.

> **Golden Rules in focus:** prequel **Rule 5** (objects either hold state or do work — services do work, statelessly) and the **bridge to Module 3** (typed exceptions).

---

## Domain service vs entity method vs application service

| Put the logic in… | …when |
|---|---|
| an **entity method** (`$app->estimatedMonthlyInstalment()`) | it operates only on that entity's own data |
| a **domain service** (`AffordabilityService::assess()`) | it spans multiple objects + a policy, and belongs to none of them |
| an **application service** (a controller/handler) | it coordinates infrastructure — load via repository, call the domain, save, respond |

Notice the split we actually made: the instalment *calculation* is a pure function of the application's own requested amount and the lending terms, so it lives **on the aggregate** (`estimatedMonthlyInstalment`). The affordability *decision* needs income + instalment + policy, so it lives **in the service**. Getting that line right is the skill this lesson teaches.

---

## The service

[`src/Domain/Service/AffordabilityService.php`](src/Domain/Service/AffordabilityService.php) is stateless: the only thing it stores is the **policy** (configuration injected once). Every assessment receives all its inputs as parameters.

```php
public function assess(BondApplication $application, Percentage $annualRate, int $termMonths): AffordabilityAssessment
{
    $income        = $application->totalMonthlyIncome();                       // from the aggregate's line items
    $instalment    = $application->estimatedMonthlyInstalment($annualRate, $termMonths); // from amount + terms
    $maxInstalment = ($this->maxInstalmentPolicy)($income);                    // from the injected policy

    return new AffordabilityAssessment($income, $instalment, $maxInstalment);
}
```

It offers **two outcome shapes**:

- `assess()` returns a typed **result** ([`AffordabilityAssessment`](src/Domain/Service/AffordabilityAssessment.php)) — figures + an `isAffordable()` verdict, leaving the caller to decide what to do.
- `guardAffordable()` throws a typed **exception** ([`ApplicantHasInsufficientIncomeException`](src/Domain/Exception/ApplicantHasInsufficientIncomeException.php)) when the rule is violated — the path that bridges to Module 3.

### The policy is a composable callable
The 30% threshold is not hard-coded into the decision. It is a callable `(Money $income): Money` returning the maximum allowable instalment, injected at construction:

```php
public function __construct(?callable $maxInstalmentPolicy = null)
{
    $this->maxInstalmentPolicy = $maxInstalmentPolicy !== null
        ? Closure::fromCallable($maxInstalmentPolicy)
        : self::standardPolicy(...);   // PHP 8.1 first-class callable syntax
}
```

Swap the callable and you change the lending rule without touching the service.

---

## 💻 Example 01 — A rule that belongs to no single object

```bash
cd courses/domain-architecture/module-2-ddd-tactical-patterns/lesson-2.3-domain-services
php examples/01-why-a-domain-service.php
```

```
=== Example 01 — Why a Domain Service ===

Applicant A:
  bond requested:    R1,250,000.00 over 240 months @ 11.5%
  monthly income:    R45,500.00
  monthly instalment:R13,330.38
  max @ 30% income:  R13,650.00
  affordable?        YES

Applicant B:
  bond requested:    R1,250,000.00 over 240 months @ 11.5%
  monthly income:    R30,000.00
  monthly instalment:R13,330.38
  max @ 30% income:  R9,000.00
  affordable?        NO

The verdict needs INCOME (the aggregate's line items) + INSTALMENT (amount + terms)
+ a POLICY (30%). No single entity owns all three — so the rule lives in a service.
```

Same bond, same instalment — only the **income** differs, and with it the verdict. The service is the one place that brings all three inputs together.

---

## 💻 Example 02 — Composable policies & the typed exception

```bash
php examples/02-composable-policies.php
```

```
=== Example 02 — Composable Policies ===

  standard 30%       max=R9,000.00    instalment=R13,330.38   -> declined
  generous 45%       max=R13,500.00   instalment=R13,330.38   -> AFFORDABLE
  flat R15,000 cap   max=R15,000.00   instalment=R13,330.38   -> AFFORDABLE

Same applicant, same maths — only the injected POLICY changes the verdict.

guardAffordable() under the standard policy:
  threw ApplicantHasInsufficientIncomeException
  message: Applicant income (R30,000.00/mo) does not support the required instalment (R13,330.38/mo).
  carries context -> income=R30,000.00 instalment=R13,330.38
  (Module 3 maps THIS typed exception to a clean 422 HTTP response.)
```

The same applicant flips from *declined* to *affordable* purely by swapping the injected policy callable (first-class callable, arrow function, or any `callable`). And `guardAffordable()` throws a **named** exception carrying **structured context** (income, instalment) — not a generic error and not a bare `false`. That typed exception is precisely what Module 3 catches and renders.

---

## 🏗️ Code Challenge — Implement the domain service

Open [`challenge/AffordabilityService.php`](challenge/AffordabilityService.php). The policy plumbing is done; implement the two methods:

1. `assess()` — gather income, instalment, and the policy's max, and return an `AffordabilityAssessment`.
2. `guardAffordable()` — run `assess()`; if not affordable, throw `ApplicantHasInsufficientIncomeException` with the application id, income, and required instalment.

```bash
php challenge/verify.php              # checks YOUR implementation
php challenge/verify.php --solution   # the reference solution (always green)
```

A correct implementation reaches all green:

```
Verifying AffordabilityService (solution)

  [PASS] assess() reports the applicant total income
  [PASS] assess() max instalment uses the policy (30% of income)
  [PASS] comfortable income is affordable
  [PASS] thin income is NOT affordable
  [PASS] guardAffordable() throws the typed exception on thin income
  [PASS] guardAffordable() passes silently on comfortable income
  [PASS] a looser policy flips the thin applicant to affordable (composability)
  [PASS] the thrown exception carries income + instalment context

--------------------------------------------------------
ALL 8 CHECKS PASSED ✅
```

Reference: [`challenge/solution/AffordabilityService.php`](challenge/solution/AffordabilityService.php).

---

## 📂 Files in this lesson

```
lesson-2.3-domain-services/
├── README.md                          ← You are here
├── autoload.php                       ← tiny PSR-4 autoloader (Bond\ -> src/)
├── src/
│   ├── Domain/
│   │   ├── ValueObject/{Currency,Money,Percentage,ApplicationId}.php
│   │   ├── Model/{ApplicationStatus,IncomeSource,BondApplication}.php   ← + estimatedMonthlyInstalment()
│   │   ├── Exception/ApplicantHasInsufficientIncomeException.php        ← typed (bridge to Module 3)
│   │   └── Service/
│   │       ├── AffordabilityAssessment.php   ← typed RESULT
│   │       └── AffordabilityService.php       ← the stateless domain service
├── examples/
│   ├── 01-why-a-domain-service.php    ← the rule spans income + instalment + policy
│   └── 02-composable-policies.php     ← swap the policy callable; the typed exception path
└── challenge/
    ├── AffordabilityService.php       ← implement assess() + guardAffordable()
    ├── solution/AffordabilityService.php
    └── verify.php                     ← behaviour-based self-checker
```

> No `php85-preview/`: the service relies on first-class callable syntax, which is PHP 8.1 and runs on 8.3. The pattern (stateless service + injected policy) is version-agnostic.

---

## 🧠 Quiz — Domain services vs entity behaviour

1. Give the test for "domain service vs entity method." Apply it to: total monthly income, the amortised instalment, and the affordability decision — which goes where, and why?
2. In what sense is `AffordabilityService` "stateless" even though it has a constructor and a property?
3. Why is the 30% threshold modelled as an injected callable rather than a constant inside the method?
4. `assess()` returns a result; `guardAffordable()` throws. When would a caller want each, and which one does the HTTP layer rely on in Module 3?
5. Why is throwing `ApplicantHasInsufficientIncomeException` (carrying income + instalment) better than returning `false` or throwing `\RuntimeException('declined')`?

---

## ✅ Lesson 2.3 checklist

- [ ] Recognise when a rule needs a domain service vs an entity method
- [ ] Understand why the instalment calc lives on the aggregate but the decision lives in the service
- [ ] Run both examples; watch a policy swap flip the verdict
- [ ] Implement the service as stateless (inputs in, result out)
- [ ] Compose at least one policy as a callable
- [ ] Have the service throw the typed `ApplicantHasInsufficientIncomeException`
- [ ] **Code Challenge:** implement `assess()` + `guardAffordable()` until `php challenge/verify.php` is all green
- [ ] Answer the five quiz questions

---

## 🎯 Module 2 complete

The Bond domain is now a proper tactical-DDD model:

- **2.0** — the Dependency Rule, with a live audit of the repo.
- **2.1** — the `BondApplication` aggregate root guarding its `IncomeSource` line items.
- **2.2** — a pure `BondApplicationRepository` interface, Pixie/PDO exiled to infrastructure.
- **2.3** — the stateless `AffordabilityService` and the first **typed domain exception**.

> **Next: Module 3 — Domain Exception Trees.** That lone `ApplicantHasInsufficientIncomeException` becomes the leaf of a deliberate hierarchy (`DomainException → BondApplicationException → …`), and Slim Killer's error middleware maps it to a clean **RFC 7807** `422` response — no stack traces, no leaks.
