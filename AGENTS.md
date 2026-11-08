# AGENTS.md

Instructions for AI coding assistants working in this repository. People are welcome to
read it too: it is the short version of how Slim Killer is built.

Tool-specific instruction files (for example `CLAUDE.md`) should only point here, so this
file stays the single source of instructions.

---

## What this repository is

Slim Killer is a small, opinionated replacement for WordPress. This repository is the
**core**: the code every client website depends on.

- Client websites are **Astro** static sites.
- Clients edit their content in a **PHP control panel** at `/cp` on their own domain
  (Slim 4, PHP-DI, Twig, SQLite).
- Saving commits the content to the client site's Git repository, GitHub Actions builds
  the site, and the host fetches the finished build. The host only needs PHP.

**Status:** early. The repository holds the project conventions and tooling; product
code arrives next. When a section below describes something that does not exist yet,
follow it as the plan, and do not invent code that the plan does not ask for.

## The three layers

| Layer | What | Lives in |
|---|---|---|
| Core | Control panel, Astro integration, `hammer` CLI | This repository, released as tagged versions |
| Site | One client's Astro project, theme and modules | The client site's own repository (`site/`, `cp/`) |
| Content | Markdown and images | The client site's repository (`content/`) |

- Core never contains anything specific to one client. If a client site needs something
  core cannot do, change core in a general way, or add a module in the client site.
- Core updates must never touch a client's `content/`.
- In a client site, never patch core files in place. Report the gap so it is fixed in
  core and released.

## How to build

Read [`BUILD_PHILOSOPHY.md`](BUILD_PHILOSOPHY.md) before adding a class, controller,
interface, container binding or domain object. It has two parts.

**Wiring** (always applies):

1. **Config and the container belong at the entry point.** Dependencies arrive through
   constructors, wired by PHP-DI. No `$container->get()` inside business logic, no
   `getenv()` inside services.
2. **Test behaviours, not layouts.** Pest tests assert what a client or host would notice,
   using fakes for external services.
3. **The type system is a security layer.** Strict types everywhere, interfaces as
   parameter types, backed enums instead of magic strings.
4. **Composition over inheritance.** Every external service sits behind an interface with
   a fake for tests. Client sites change behaviour by binding a different implementation,
   not by subclassing core.
5. **Objects hold state, services do work.**
6. **Read the failing test before reading the code.**

**Modelling** (only where it pays for itself):

7. **Ceremony must be paid for.** Content editing and settings stay simple: schema in,
   file out. Value objects, aggregates and exception trees are for the deploy pipeline and
   the shop, where the rules are real.
8. **The dependency rule points inward.** Domain code imports nothing infrastructural and
   takes the current time as an argument.
9. **Make illegal states unrepresentable.** No setters on domain objects.
10. **Aggregates are small consistency boundaries,** linked to each other by ID.
11. **Speak the client's words.** Clients see Save, Publish, Draft and Live, never commit,
    build or deploy.
12. **Predictable failures are typed,** separate from infrastructure failures.

Other conventions:

- PHP 8.4 (no PHP 8.5-only features in core yet), `declare(strict_types=1);` in every PHP
  file, PSR-4 autoloading.
- Controllers are single-action invokable classes, one per route.
- Plain PDO behind repository classes. No ORM.
- Secrets live in `.env`, outside the web root, never in Git.
- Follow `.editorconfig`: UTF-8, LF line endings, 4 spaces (2 for YAML, JS, TS, JSON and
  Astro files).

## Commands

Planned, not yet available: `php hammer` lists the CLI commands (`doctor`, `install`,
`user:create`, `deploy:fetch`, `cp:update`, `backup:run` and others).

Quality checks, once installed (the branch scripts run them automatically):

```sh
php vendor/bin/pint --test        # code style
php vendor/bin/phpstan analyse    # static analysis
php vendor/bin/pest               # tests
```

## Git workflow

- `main` is the only long-lived branch. Work happens on short-lived `feature/`, `fix/`,
  `chore/` or `docs/` branches created with `./feature-branch-start.sh` and closed with
  `./feature-branch-stop.sh` (see "Contributing" in [`README.md`](README.md)).
- The maintainer runs git. Do not commit, push, merge, tag or delete branches unless
  you are explicitly asked to. Prepare the change, then give the exact commands and a
  suggested commit message.
- Every user-visible change gets an entry under `[Unreleased]` in
  [`CHANGELOG.md`](CHANGELOG.md), in plain language.
- Commit messages use a type prefix: `feat:`, `fix:`, `chore:`, `docs:`, `test:`,
  `refactor:`.

## Never

- **Never credit an AI assistant.** No `Co-Authored-By` trailers, "Generated with" lines,
  or mentions of the assistant in commits, pull requests, the changelog, documentation or
  code comments. The work belongs to the project's owner.
- Never name a client, or include a client's details, in this public repository, its
  wiki or its changelog.
- Never commit secrets, `.env` files, `vendor/`, `node_modules/` or build output.
- Never edit files in a client's `content/` folder except through the control panel's
  own code paths.
- Never add a dependency without saying why, and prefer maintained, widely used packages
  with pinned versions.

## Writing style

Documentation, changelog entries and messages to clients use plain, short sentences.
Explain a technical term the first time it appears. Prefer an example to an abstraction.
