# Changelog

All notable changes to Slim Killer are documented here, newest first. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/): entries are grouped as Added,
Changed, Removed and Fixed under each release. Versions are git tags (`vMAJOR.MINOR.PATCH`,
see "Versioning" in `README.md`). Work that has not been released yet sits under
**[Unreleased]** at the top.

`feature-branch-stop.sh` checks that this file was touched before it merges a branch into
`main`. If a change has nothing worth recording, you can answer "yes, continue anyway".

## [Unreleased]

### Added

- **Cover page and public project page.** `public/index.php` is the project's cover. Served
  locally (Herd), it shows live status: PHP version, whether `composer install` has run,
  required extensions and WebP. `php public/index.php --static` prints the public page for
  slimkiller.monatemedia.com from the same source, so the two cannot drift. Roadmap
  progress, test count and host checks are read from the code, not typed in.
- **`hammer`, the command-line tool.** `php hammer` lists the commands. It starts with a
  pre-flight guard: run with a PHP older than 8.4, it says so and prints the full path of
  a newer PHP it found on the server (shared hosts often default to an older PHP on the
  command line than on the website).
- **`hammer doctor`, the host checker.** Checks the command line's PHP, the server and,
  with `--url` and `--webroot`, the website's own PHP and web server, and reports each
  item as PASS, WARN (works, with an accepted alternative), FAIL (with how to fix it) or
  INFO. It checks the PHP version, extensions, disabled functions, memory, time and upload
  limits, WebP support, the PHP binaries installed, the storage folder, outgoing
  connections to GitHub, `.htaccess` rewrites, blocked folder listings and cron. The
  website check places a temporary, key-protected probe in the web root and always
  removes it again.
- **`hammer cron:heartbeat`.** The command to run from cron every minute; the doctor
  confirms it ran, and with which PHP.
- **Project skeleton.** Composer project with PHP-DI (container wired in one place,
  `config/container.php`), Pest tests, PHPStan (level 8) and Pint. `composer check`
  runs all three, and `feature-branch-stop.sh` now runs them before merging.

- **README and MIT licence.** The project README, with the clown logo, and the MIT licence.
- **Branch helper scripts.** `feature-branch-start.sh` and `feature-branch-stop.sh` create
  `feature/`, `fix/`, `chore/` and `docs/` branches from `main` and close them again: check
  the changelog, run whichever quality gates are installed (Pint, PHPStan, Pest, npm test),
  merge into `main` and delete the branch.
- **Project conventions.** `BUILD_PHILOSOPHY.md` (six build rules), this changelog,
  `.editorconfig`, `.gitattributes` (LF line endings everywhere) and issue templates for bug
  reports and feature requests.

- **`AGENTS.md`.** Instructions for AI coding assistants working on Slim Killer: the three
  layers (core, site, content), the build rules, the git workflow, and what never to do.

### Changed

- **README roadmap brought up to date.** The spikes and the host checker are ticked; the
  remaining steps are listed in the order they will be built.
- **`feature-branch-start.sh` suggests `feat:` commit messages** on feature branches, matching
  the commit prefixes in `AGENTS.md`.
- **`BUILD_PHILOSOPHY.md` now covers both PHP courses.** Part 1 (Wiring) restores the full
  "type system is a security layer" rule; Part 2 (Modelling) adds six domain-architecture
  rules, gated by "ceremony must be paid for", with a table of where Slim Killer applies
  them (the deploy pipeline and the shop) and where it does not (content editing,
  settings). `AGENTS.md` summarises both parts.

### Removed

- **The first attempt's code.** Kept at the tag `slim-killer-v1` and the branch `archive/v1`.
