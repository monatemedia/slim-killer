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

### Removed

- **The first attempt's code.** Kept at the tag `slim-killer-v1` and the branch `archive/v1`.
