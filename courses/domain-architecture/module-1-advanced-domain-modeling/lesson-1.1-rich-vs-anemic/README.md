# Lesson 1.1 — Rich vs. Anemic Domain Models
> **Module 1 — Advanced Domain Modeling**
> **Folder:** `lesson-1.1-rich-vs-anemic/`

> **Move the behaviour to the data. The data should defend itself.** Lesson 1.0 diagnosed the disease (anemia); this lesson administers the first dose of the cure — an object that owns its rules.

---

## Why this lesson exists

In Lesson 1.0 you proved the Bond flow has no domain: an `array` of strings flows through a "service" that returns a `bool`, and a negative R5,000,000 bond saves without complaint. The root cause is **anemia** — the data lives in one place and the rules live somewhere else (or nowhere). This lesson fixes the *structure*: we move the rules **into** the object that owns the data, so the rules can no longer be skipped.

Lesson 1.2 then attacks the *primitives* (turning `float` into `Money`). The two together — rich structure + value objects — are what "domain model" means.

By the end of this lesson you will be able to:

1. Define **anemic** vs **rich** and recognise each on sight.
2. Apply **Tell, Don't Ask**: replace setters with intent-revealing methods (`submit()`, not `setStatus()`).
3. Encapsulate state so it is readable but not externally writable.
4. Model a lifecycle as a **guarded state machine** where illegal transitions are unreachable.

> **Golden Rule in focus:** **Rule B** — make illegal states unrepresentable (here, illegal *transitions*).

---

## Anemic vs Rich

An **anemic** object is a data bag: public properties (or getter/setter pairs) and no behaviour. Some external service does the actual work and is responsible — in theory — for keeping the data valid. In practice the rules get skipped, duplicated, or contradicted.

A **rich** object bundles the data with the operations that are allowed on it. You don't *set* its state; you *ask it to do something*, and it decides whether that is currently legal.

```php
// ❌ ANEMIC — the world scribbles directly on the state
$application->setStatus('approved');   // approved straight from draft? sure, why not
$application->setStatus('aproved');    // typo? still a valid string

// ✅ RICH — the object owns the transition and guards it
$application->submit();                // Draft -> Submitted (or throws)
$application->approve();               // Submitted -> Approved (or throws)
```

### Tell, Don't Ask
"Tell, Don't Ask" means: don't *ask* an object for its data, make a decision, and *push the result back*. **Tell** the object what you want and let it decide. `setStatus('approved')` asks the object to be a dumb variable. `approve()` tells it your intent and lets it enforce the rules of being a bond application.

---

## Building the rich `BondApplication`

The reference model is [`src/BondApplication.php`](src/BondApplication.php), with its closed set of states in [`src/ApplicationStatus.php`](src/ApplicationStatus.php). Three ideas:

**1. State is private; transitions are methods.** `$status` is never assignable from outside. The only way to move it is `submit()` / `approve()` / `decline()`, each of which guards first.

**2. Every transition is guarded.** A single helper enforces the precondition, so an illegal move throws instead of silently corrupting state:

```php
private function guardCurrentStatusIs(ApplicationStatus $required, string $action): void
{
    if ($this->status !== $required) {
        throw new DomainException(
            "Only a {$required->value} application can be {$action}; current status is '{$this->status->value}'."
        );
    }
}
```

**3. Invariants are enforced at birth.** The constructor rejects a non-positive requested amount — there is no moment when a `BondApplication` exists in an invalid shape.

> We throw the built-in `\DomainException` here as a placeholder. **Module 3** replaces it with a typed business exception (`ApplicationAlreadySubmittedException`) that the HTTP layer maps to a clean response.

---

## 💻 Run the examples

```bash
cd courses/domain-architecture/module-1-advanced-domain-modeling/lesson-1.1-rich-vs-anemic
php examples/01-anemic-vs-rich.php
```

```
=== Example 01 — Anemic vs Rich ===

ANEMIC model:
  Draft jumped to:        'approved' (never submitted — allowed)
  Typo status accepted:   'aproved'

RICH model:
  Born as:                'draft'
  approve() on draft:     REJECTED — Only a submitted application can be approved; current status is 'draft'.
  Legal submit->approve:  'approved'

The anemic object cannot stop nonsense. The rich object cannot be put into nonsense.
```

```bash
php examples/02-lifecycle-guards.php
```

```
=== Example 02 — Lifecycle Guards ===

Start:               draft
After submit():      submitted
After decline():     declined
Decline reason:      Affordability check failed.

  Rejected — approve a fresh draft: Only a submitted application can be approved; current status is 'draft'.
  Rejected — decline a fresh draft: Only a submitted application can be declined; current status is 'draft'.
  Rejected — submit twice: Only a draft application can be submitted; current status is 'submitted'.
  Rejected — decline with empty reason: A decline reason is required.
```

---

## 🆕 PHP 8.4 / 8.5: asymmetric visibility & property hooks

The runnable model uses a private property plus a `status()` getter so it runs on **PHP 8.3 today**. On the course's target runtime you would delete the getter entirely and let the language enforce encapsulation:

```php
// PHP 8.4 — readable as a property everywhere, writable only INSIDE the class
public private(set) ApplicationStatus $status = ApplicationStatus::Draft;

// PHP 8.4 — a property hook validates at the point of assignment
public private(set) string $applicantEmail {
    set (string $value) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid applicant email: {$value}");
        }
        $this->applicantEmail = $value;
    }
}
```

- **Asymmetric visibility** (`public private(set)`) gives you read-only-from-outside with zero boilerplate — no getter, and external `$app->status = ...` is a fatal error.
- **Property hooks** move validation to the data-entry point, so an invalid value can never be stored in the first place.

The full preview is in [`php85-preview/bond-application-with-hooks.php`](php85-preview/bond-application-with-hooks.php). **Do not run it on PHP 8.3** — `private(set)` and `set { }` are parse errors there. On Herd 8.5: `herd use 8.5 && php php85-preview/bond-application-with-hooks.php`.

---

## 🏗️ Code Challenge — Make the model rich

Open [`challenge/BondApplication.php`](challenge/BondApplication.php). `submit()` is implemented as a worked example; your job is to implement `approve()` and `decline()` so each **guards its transition** instead of blindly mutating state:

- `approve()`: `Submitted → Approved`; throws `\DomainException` from any other status.
- `decline(string $reason)`: `Submitted → Declined`; throws `\DomainException` from a non-submitted status, throws `\InvalidArgumentException` on a blank reason, and stores the reason.

Check your work:

```bash
php challenge/verify.php              # checks YOUR implementation
php challenge/verify.php --solution   # the reference solution (always green)
```

The stub starts at 2/7 (the given pieces) and a correct implementation reaches all green:

```
Verifying BondApplication (solution)

  [PASS] Starts as Draft
  [PASS] submit(): Draft -> Submitted
  [PASS] approve(): Submitted -> Approved
  [PASS] approve() on a Draft throws DomainException
  [PASS] decline(reason): Submitted -> Declined and stores reason
  [PASS] decline() on a Draft throws DomainException
  [PASS] decline() with a blank reason throws InvalidArgumentException

------------------------------------------------
ALL 7 CHECKS PASSED ✅
```

Reference: [`challenge/solution/BondApplication.php`](challenge/solution/BondApplication.php). Notice every mutator has the *same shape* — guard, then transition. That regularity is what makes a rich model easy to extend safely.

---

## 📂 Files in this lesson

```
lesson-1.1-rich-vs-anemic/
├── README.md                              ← You are here
├── src/
│   ├── ApplicationStatus.php              ← closed set of lifecycle states (enum)
│   └── BondApplication.php                ← the rich reference model
├── examples/
│   ├── 01-anemic-vs-rich.php              ← side-by-side contrast
│   └── 02-lifecycle-guards.php            ← the guarded state machine
├── php85-preview/
│   └── bond-application-with-hooks.php    ← PHP 8.4+ asymmetric visibility + hooks (do NOT run on 8.3)
└── challenge/
    ├── BondApplication.php                ← implement approve() + decline()
    ├── solution/BondApplication.php       ← reference solution
    └── verify.php                         ← behaviour-based self-checker
```

---

## 🧠 Quiz — Tell-Don't-Ask and encapsulation

1. Give the two-part definition of an anemic model. Why does it "look OO but isn't"?
2. Rewrite `setStatus(string $s)` as one or more intent-revealing methods for the Bond lifecycle. What did you gain?
3. Why is `public private(set)` better than a private property plus a public getter? What does it remove?
4. The rich `BondApplication` throws `\DomainException` on an illegal transition. Why is that still not ideal, and which module fixes it?
5. Where should the rule "a bond amount must be positive" live — the controller, the action, or the model? Defend your answer with the Diagnostic Question from Lesson 1.0.

---

## ✅ Lesson 1.1 checklist

- [ ] Define anemic vs rich in your own words
- [ ] Identify the setters in the anemic example that should become intent-revealing methods
- [ ] Run both examples and observe the rich model rejecting illegal transitions
- [ ] Understand the guard-then-transition pattern in `src/BondApplication.php`
- [ ] Read the PHP 8.4 `public private(set)` + property-hook preview
- [ ] **Code Challenge:** implement `approve()` + `decline()` until `php challenge/verify.php` is all green
- [ ] Answer the five quiz questions

---

*Next lesson: **Lesson 1.2 — Value Objects & Self-Validation**, where we attack the primitives themselves and build `Money` and `Percentage` — then **Lesson 1.3** gives this rich model a stable identity.*
