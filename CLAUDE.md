# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

"Slim Killer" is a hand-built micro-framework wrapped around Slim 4 + PHP-DI + Twig + Pixie (query builder), intended as a Laravel-flavored teaching project. It ships its own Artisan-style CLI (`hammer`), an auto-discovered command registry, code generators (`make:*`), and a from-scratch migration runner. There is no application framework doing this work for you — the bootstrap, container wiring, routing loader, and migration engine are all local code you can read and change.

## Commands

The CLI is `hammer` (run as `php hammer <command>`). `artisan` is a deliberate troll alias that prints an insult and proxies to `hammer` — use `hammer` directly.

```bash
php hammer                          # list all commands (grouped by namespace)
php hammer serve                    # dev server at http://localhost:8000 (serves public/)
php hammer migration:migrate        # run pending migrations (transactional, batched)
php hammer migration:rollback       # roll back the last batch
php hammer auth:create-admin <user> <password>   # seed an admin row into users
php hammer cache:clear              # clear compiled Twig cache
php hammer debug:container          # list registered DI services

# Generators — all accept an optional [Context/] prefix that becomes a subnamespace + subfolder
php hammer make:controller Application/Home   # -> src/Http/Application/HomeController.php
php hammer make:action <Name>
php hammer make:service <Name>
php hammer make:middleware <Name>
php hammer make:repository <Name>
php hammer make:migration CreateFooTable       # parses table name out of Create*Table / Update*Table
php hammer make:view <Name>
```

There is **no test suite, linter, or build step** — `composer.json` has no `require-dev` and no scripts. The `php8.5-oop-mastery-course-overview/` directory is course/teaching material, not part of the running app.

Environment: copy `.env.example` to `.env`. SQLite is the default driver (`database/database.sqlite`); MySQL/MariaDB/PostgreSQL are configured in `config/database.php` and selected via `DB_DRIVER`.

## Architecture

Two entry points share the same container definitions (`config/services.php`) and `.env` bootstrap (`config/bootstrap.php`):

- **HTTP** — `public/index.php`: builds the PHP-DI container, hands it to Slim's `AppFactory`, adds Twig + routing + error middleware, then loads `routes/web.php`. `routes/web.php` returns a `function (App $app)` that registers routes against invokable controller class names.
- **CLI** — `hammer`: builds the same container, then **auto-discovers** every class under `src/Infrastructure/Commands/` that implements `CommandInterface`, resolves it through the container, and dispatches by `getName()`. To add a command, just drop a class implementing `CommandInterface` (`getName`, `getDescription`, `handle(array $argv)`) anywhere under that tree — no registration needed. A colon in the name (e.g. `make:controller`) groups it under a namespace heading in the help output.

### Layering (DDD-ish, mid-migration — see warning below)

PSR-4 maps `App\` → `src/`. The intended layers:

- `src/Http/{Context}/` — invokable single-action controllers. Each is a class with `__construct` (constructor-promoted dependencies, autowired by PHP-DI) and `__invoke(Request, Response, array $args): Response`. Controllers render Twig templates or redirect; they hold no business logic.
- `src/Domain/{Context}/` — business actions/services (e.g. `SubmitApplicationAction`). Controllers depend on these.
- `src/Infrastructure/` — framework plumbing: `Commands/` (CLI), `Middleware/` (e.g. `AuthMiddleware`), `Persistence/{Context}/` (repositories).
- `src/Utils/` — helpers like `NameParser` (shared by all generators to turn CLI input into PascalCase class names, snake_case table/view names, and `Context/` subnamespaces).

### Persistence

Repositories wrap Pixie's `QueryBuilderHandler`, fetched from the container via the `'db'` alias. Repositories are explicitly wired in `config/services.php` (not autowired) so they receive `'db'`. Use `$db->table(...)` for queries; reach raw PDO via `$db->getConnection()->getPdoInstance()` when you need driver-specific SQL.

Migrations live in `database/migrations/` as `YYYY_MM_DD_HHMMSS_*.php` files that **return an anonymous class** with `up(QueryBuilderHandler $db)` / `down(QueryBuilderHandler $db)`. They write **raw cross-driver SQL** (branching on `PDO::ATTR_DRIVER_NAME` for `INTEGER PRIMARY KEY AUTOINCREMENT` vs `INT AUTO_INCREMENT PRIMARY KEY`, `CURRENT_TIMESTAMP` vs `NOW()`). The runner tracks applied files in a `migrations` table by batch and runs each batch inside a single transaction.

### Views & auth

Twig templates are in `resources/views/`: `pages/` (public marketing pages), `admin/`, `layouts/`, `partials/`. Compiled cache goes to `storage/cache/views/`. `make:controller` auto-derives a template path and treats the `Application` context as the `pages/` folder.

Auth is session-based: `auth:create-admin` hashes a password with `password_hash`; `AuthMiddleware` (applied to the `/admin` group in `routes/web.php`) redirects to `/login` when `$_SESSION['user_id']` is absent.

## Conventions

- **Controllers are single-action invokables**, one per route. Follow the existing pattern rather than adding multi-method controllers.
- **Path depth in CLI code is hardcoded** via `dirname(__DIR__, N)` to reach the project root from a command's location. If you move a command between subfolders, fix the depth count (commands under `src/Infrastructure/Commands/<Group>/` use `dirname(__DIR__, 4)`).
- CLI output uses raw ANSI escape codes (`\e[32m...\e[0m`) for color — match this style in new commands.
- The container is defined as a **plain array** returned from `config/services.php`. Add explicit factory closures there only when autowiring can't resolve a dependency (e.g. the `'db'` string alias, repositories needing it).

## ⚠️ Branch state: namespace migration in progress

This branch (`ddd-design`) is mid-refactor toward the `Domain` / `Infrastructure` layout. Some files still declare **old namespaces that no longer match their PSR-4 path** and will not autoload as-is — notably `src/Domain/Application/SubmitApplicationAction.php` declares `namespace App\Actions;` and imports `App\Repositories\ApplicationRepository` (both legacy paths). `ProcessApplyController` imports the same legacy `App\Actions\SubmitApplicationAction`. When touching the application/apply flow, expect to fix these namespaces to the new `App\Domain\...` / `App\Infrastructure\Persistence\...` locations rather than assuming the code currently runs. `roadmap.md` and `todo.md` track the broader plan.
