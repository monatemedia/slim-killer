# Slim Killer Build Philosophy

Six rules for building Slim Killer, adapted from the build philosophy of the PHP 8.5 OOP
mastery course and the same rules already used in Edward's other projects. When a design
decision feels uncertain (a new service, a new controller, a new interface, a new
container binding), check it against this list first.

Slim Killer has one extra reason to take these rules seriously: it is a **core** that many
client sites depend on. A shortcut taken in core is a shortcut every client inherits.

---

## Rule 1 — The Container Belongs at the Edges, Not in Business Logic

PHP-DI wires the application together in one place: the container definitions (the
composition root). A class that calls `$container->get(...)` from inside its own methods,
or reads `$_ENV` / `getenv()` directly, has turned the container into a service locator:
the coupling is hidden instead of removed. Dependencies arrive through the constructor.

```
✅  A controller that needs a service: constructor-inject it and let autowiring supply it.
✅  A service that needs configuration: inject a small, typed config object built once
    in the container definitions from .env.
✅  Several implementations of one job (ContentStore, BackupTarget, PaymentGateway):
    core defines the interface, the container binds one implementation, and a client
    site overrides the binding in its own definitions file.
❌  A service calling $container->get(SomeService::class) "because it's convenient here".
❌  getenv('GITHUB_TOKEN') inside a class that commits files.
```

---

## Rule 2 — Test Behaviours, Not Layouts

Test what a client or a host would notice, not how the code is arranged. "Saving an
article commits exactly one file with the new front matter" is a behaviour. "The save
controller calls the repository twice" is a layout, and it breaks the moment the code is
tidied.

```
✅  Save an entry against a fake ContentStore and assert the committed Markdown.
✅  Feed the doorbell endpoint a badly signed request and assert a 401 with nothing queued.
❌  Assert that a private method was called, or snapshot a whole Twig page for one field.
```

Fakes for every interface in Rule 1 are what make these tests fast and offline.

---

## Rule 3 — Backed Enums Over Magic Strings

Anything status-, type- or role-shaped is a backed enum with a case for every valid value
and nothing else: a user's role (`owner`, `admin`), a build's state (`queued`, `building`,
`live`, `failed`), a branch or release type.

```
✅  enum BuildStatus: string { case Queued = 'queued'; ... }
❌  A string column whose valid values are only listed in a comment.
```

---

## Rule 4 — Composition Over Inheritance

When behaviour needs sharing, ask whether there is a real "is-a" relationship or just code
reuse. Core grows by small collaborators behind interfaces, and client sites change
behaviour by swapping a binding, not by subclassing core classes.

```
✅  A client site needs a different backup target: implement BackupTarget and bind it in
    the site's definitions.
✅  A multi-step job (fetch release, verify checksum, unpack, switch): separate focused
    classes coordinated by one service.
❌  class HoneyBadgerContentStore extends GitHubContentStore, overriding three methods.
❌  One service that gains a new method for every step forever.
```

---

## Rule 5 — Objects Hold State, Services Perform Work — Rarely Both

Value objects and entities (an entry, a release, a user) hold data and protect their own
rules. Services do the work (commit, fetch, build, back up) and stay as stateless as
possible: same inputs, same outputs, no memory between calls.

```
✅  A Release value object that knows its version and checksum, and a ReleaseFetcher
    service that downloads one.
❌  A Release object that downloads itself, writes to disk and sends an email.
```

---

## Rule 6 — Read the Failing Test Before Reading the Code

When something breaks, the failure message says what broke. Read the test's expectation
first, then open the source to understand why it was violated. The Pest suite in `tests/`
is where failures show up first.

---

## How These Rules Connect

```
Rule 4 (Composition)
   ↓ enables
Rule 1 (Container at the edges) + Rule 3 (Backed enums)
   ↓ enables
Rule 2 (Test behaviours)
   ↓ enables
Rule 6 (Read the failing test)
   ↓ sustained by
Rule 5 (State vs behaviour)
```

Composition makes dependency injection possible. Injection and typed data make the code
testable. Tests make debugging a matter of reading. Keeping state and behaviour apart keeps
all of it durable as core and the client sites built on it keep growing.
