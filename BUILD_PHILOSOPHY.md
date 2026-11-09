# Slim Killer Build Philosophy

Twelve rules in two parts, adapted from Edward's two PHP courses:

- **Part 1, Wiring** comes from *PHP 8.5 OOP Mastery* (`php8.5-oop-mastery-course`): how
  components are connected.
- **Part 2, Modelling** comes from *Advanced Domain Architecture & Tactical DDD*
  (`php8.5-domain-architecture`): what the components should be, and when that effort is
  worth it.

When a design decision feels uncertain (a new service, controller, interface, container
binding or domain object), check it against this list first.

This file is the short form, and the version that counts. Each rule has a page in the
[wiki](https://github.com/monatemedia/slim-killer/wiki/Build-philosophy) that goes deeper: why it exists, where it shows in the code
today, and when not to apply it. Words used here are defined in the
[Glossary](https://github.com/monatemedia/slim-killer/wiki/Glossary).

Slim Killer has one extra reason to take these rules seriously: it is a **core** that many
client sites depend on. A shortcut taken in core is a shortcut every client inherits.

**PHP version.** Core targets **PHP 8.4**, because that is what shared hosts offer. The
courses teach PHP 8.5; features newer than 8.4 (the pipe operator, `clone` with new values)
stay out of core until hosts support them. When code uses a feature newer than PHP 8.0, say
so in a short comment, the way the courses do:

```php
// Asymmetric visibility (PHP 8.4).
public private(set) string $slug;
```

---

# Part 1 — Wiring

## Rule 1 — Config and the Container Belong at the Entry Point

*In depth: [wiki page](https://github.com/monatemedia/slim-killer/wiki/Rule-1-Config-at-the-entry-point).*

PHP-DI wires the application together in one place: the container definitions, used by
the control panel's front controller and by `hammer` (the composition root). Classes receive their
dependencies through the constructor. A class that calls `$container->get(...)` inside its
own methods, or reads `$_ENV` / `getenv()` / a config file directly, has turned the
container into a service locator: the coupling is hidden instead of removed.

```
✅  A controller that needs a service: constructor-inject it and let autowiring supply it.
✅  A service that needs configuration: inject a small, typed config object built once in
    the container definitions from .env.
✅  Several implementations of one job (ContentStore, BackupTarget, PaymentGateway): core
    defines the interface, the container binds one implementation, and a client site
    overrides the binding in its own definitions file.
❌  A service calling $container->get(SomeService::class) "because it's convenient here".
❌  getenv('GITHUB_TOKEN') inside the class that commits files.
```

## Rule 2 — Test Behaviours, Not Layouts

*In depth: [wiki page](https://github.com/monatemedia/slim-killer/wiki/Rule-2-Test-behaviours-not-layouts).*

Tests assert what the system does, not how it is built. "Saving an article commits exactly
one file with the new front matter" is a behaviour. "The save controller has a `$store`
property" is a layout, and it breaks the moment the code is tidied.

```
✅  Save an entry against a fake ContentStore and assert the committed Markdown.
✅  Send the build-ready webhook a badly signed request and assert a 404 with nothing fetched.
❌  Assert that a private method was called, or count constructor parameters.
❌  Snapshot a whole Twig page to check one field.
```

Fakes for every interface in Rule 1 are what make these tests fast and offline.

## Rule 3 — The Type System Is a Security Layer

*In depth: [wiki page](https://github.com/monatemedia/slim-killer/wiki/Rule-3-Types-as-a-security-layer).*

Types are an enforcement mechanism, not documentation. They stop the wrong kind of value at
the boundary, before it becomes a bug or a security hole, and Slim Killer takes untrusted
input from `/cp` forms, uploads and webhooks.

- `declare(strict_types=1);` in every PHP file.
- Type parameters against interfaces, not concrete classes.
- Backed enums for anything status-, type- or role-shaped (`Role`, `BuildStatus`,
  `ReleaseState`). A `string $status` accepts "panding"; a `BuildStatus $status` accepts
  only the cases you defined.
- `never` for functions that always throw or exit.
- Property hooks and asymmetric visibility (PHP 8.4) to validate data where it enters.

## Rule 4 — Favour Composition Over Inheritance

*In depth: [wiki page](https://github.com/monatemedia/slim-killer/wiki/Rule-4-Composition-over-inheritance).*

Before writing `extends`, ask whether a genuine "is-a" relationship exists or whether you
just want to reuse code. The practical test: if you can replace `extends Parent` with a
private field and call `$this->parent->method()`, do that instead. Client sites change
behaviour by binding a different implementation, not by subclassing core.

```
✅  A client needs a different backup target: implement BackupTarget and bind it in the
    site's definitions.
✅  A multi-step job (fetch release, verify checksum, unpack, switch): separate focused
    classes coordinated by one service.
❌  class ClientContentStore extends GitHubContentStore, overriding three methods.
```

## Rule 5 — Objects Either Hold State or Perform Work — Rarely Both

*In depth: [wiki page](https://github.com/monatemedia/slim-killer/wiki/Rule-5-State-or-work-rarely-both).*

Value objects and entities hold data and protect their own rules. Services (a committer, a
fetcher, a backup job) do the work and stay stateless: all inputs arrive as arguments, all
outputs are return values, nothing is remembered between calls.

```
✅  A Release value object that knows its version and checksum; a ReleaseFetcher service
    that downloads one.
❌  A ReleaseFetcher that keeps $lastRelease between calls.
❌  A Release object that downloads itself, writes to disk and sends an email.
```

## Rule 6 — Read the Failing Test Before Reading the Code

*In depth: [wiki page](https://github.com/monatemedia/slim-killer/wiki/Rule-6-Read-the-failing-test-first).*

The failure message says what broke. Read the test's expectation first, then open the
source only to understand why it was violated. The Pest suite in `tests/` is the brief,
stated precisely.

### How the wiring rules connect

```
Rule 4 (Composition)
   ↓ enables
Rule 1 (Config at the entry point) + Rule 3 (Type system)
   ↓ enables
Rule 2 (Test behaviours)
   ↓ enables
Rule 6 (Read the failing test)
   ↓ sustained by
Rule 5 (State vs behaviour)
```

---

# Part 2 — Modelling

## Rule 7 — Ceremony Must Be Paid For

*In depth: [wiki page](https://github.com/monatemedia/slim-killer/wiki/Rule-7-Ceremony-must-be-paid-for).*

This rule decides whether Rules 8 to 12 apply at all. Domain modelling (value objects,
aggregates, domain events, exception trees) adds indirection that every reader pays for.
It is worth it only where the business rules are genuinely complicated. The domain course
names content management and configuration as work that does **not** justify it, and much
of Slim Killer is exactly that.

| Area | Pay for the ceremony? | Why |
|---|---|---|
| Content editing (forms over Markdown files) | **No** | Schema in, file out. The only rules are "this field is required" and "this is an image". Keep it simple. |
| Settings, users, the media list | **No** | Configuration and plain records. |
| The deploy and release pipeline | **Yes** | Illegal transitions: a release cannot go live before its checksum is verified; a failed build must never replace a live site; a save must not overwrite a newer version. |
| Edge Storefront (orders, stock, payments) | **Yes** | Invariants span entities (a reservation and its stock), money is involved, and mistakes carry consumer-law and POPIA consequences. |

When in doubt, start simple and add structure when a real rule appears. The domain-
architecture course's signals for "this needs modelling": an invariant spans more than one
entity, the lifecycle has illegal transitions, people who do not write code argue about the
rules, or getting it wrong costs money or breaks a law.

## Rule 8 — The Dependency Rule Points Inward

*In depth: [wiki page](https://github.com/monatemedia/slim-killer/wiki/Rule-8-The-dependency-rule-points-inward).*

Where we do model (Rule 7), domain code depends on nothing outside itself: not Slim, not
PDO, not the HTTP request, not the filesystem, not `$_SESSION`, and **not the clock**.
`time()`, `date()`, `rand()`, `uniqid()` and `getenv()` are all the outside world.

```
✅  $reservation->hasExpired($now)        // the current time is passed in
❌  $reservation->hasExpired()            // calls time() inside; untestable without
                                          // controlling the universe
```

The test anyone can run: read the `use` statements at the top of a domain file. If it
imports anything infrastructural, the rule is broken.

## Rule 9 — Make Illegal States Unrepresentable

*In depth: [wiki page](https://github.com/monatemedia/slim-killer/wiki/Rule-9-Illegal-states-unrepresentable).*

A domain object cannot be constructed in an invalid state. Validation in the constructor is
a guarantee; validation afterwards is a request. Public setters disappear from the domain
layer: state changes through named operations that are free to refuse.

```
✅  new Money(-500, Currency::ZAR)      → throws. There is no negative Money.
✅  new Version('1.4')                  → throws. Versions are vMAJOR.MINOR.PATCH.
✅  $release->activate()               → throws unless the checksum was verified.
❌  $order->setStatus('paid')          → a setter is a hole in every rule above it.
```

## Rule 10 — The Aggregate Is a Consistency Boundary, and It Is Smaller Than You Think

*In depth: [wiki page](https://github.com/monatemedia/slim-killer/wiki/Rule-10-Small-aggregates).*

An aggregate is the set of objects that must be correct together, at the same instant, in
one transaction. Ask: "if these two changed a second apart, would the business be harmed?"
If not, they are separate aggregates linked by ID.

```
✅  An Order holds its OrderLines: the total must match the lines at every instant.
✅  Stock is its own aggregate; an Order refers to a ProductId, and a reservation holds
    stock until the payment webhook confirms or releases it.
❌  An Order that loads the whole Product, which loads every other Order for it.
```

## Rule 11 — Speak the Client's Words, Exactly

*In depth: [wiki page](https://github.com/monatemedia/slim-killer/wiki/Rule-11-Speak-the-clients-words).*

Code and the control panel use the words the client uses. Clients see **Save**,
**Publish**, **Draft** and **Live**, never "commit", "build" or "deploy". When the code uses
the business's words, the business can read it back to you and tell you where it is wrong.

Every term has one meaning, written down in the
[Glossary](https://github.com/monatemedia/slim-killer/wiki/Glossary). Text a client reads also follows
[Writing for clients](https://github.com/monatemedia/slim-killer/wiki/Writing-for-clients), based on the ASD-STE100 Simplified
Technical English writing rules.

The same word can mean different things in different parts of the system: a "product" in
the content editor is a page with photos; in the shop it is something with a price and
stock. Each part keeps its own model rather than sharing one overloaded class.

## Rule 12 — A Failure the Business Can Predict Is Not Exceptional

*In depth: [wiki page](https://github.com/monatemedia/slim-killer/wiki/Rule-12-Predictable-failures-are-typed).*

Predictable rule-breaking and genuine breakdowns are different categories and never share a
code path.

```
✅  A save over a newer version   → typed domain exception → 409, "Someone saved this
                                     page after you opened it. Reload to see their changes."
✅  GitHub unreachable            → infrastructure exception → keep the edit, retry, log
✅  Database or disk failure      → 500, logged, details hidden
❌  catch (\Exception $e)          → everything becomes "something went wrong"
❌  throw new \Exception('nope')   → nothing downstream can tell it from a crash
```

Domain exceptions form a tree, so a boundary can catch broadly while the core throws
narrowly. Exceptions carry structured data; the sentence the client reads is written at the
boundary, where it can be translated or turned into JSON.

### How the modelling rules connect

```
Rule 7 (Ceremony must be paid for)
   ↓ decides whether to apply any of the rest
Rule 11 (Speak the client's words)
   ↓ gives you the names that
Rule 9 (Illegal states unrepresentable) protects
   ↓ within a boundary defined by
Rule 10 (The aggregate is a consistency boundary)
   ↓ which is only keepable because of
Rule 8 (The dependency rule points inward)
   ↓ and is communicated outward by
Rule 12 (Predictable failures are typed)
```

Rule 7 sits at the top on purpose. It is a gate, not a footnote.
