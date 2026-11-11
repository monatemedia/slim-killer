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

- **Publishing progress.** After a save, the dashboard and content pages show Saved →
  Building → Going live → Live, updating by themselves, from GitHub's record of the build
  and the host's live release. A failed build says the website is unchanged. Once a few
  publishes have gone live, it adds the time left, from this site's own recent
  publishes. The GitHub token needs "Actions: read" for the Building stage.
- **The webhook: "a build is ready".** `POST /cp/hooks/build-ready`, signed with
  HMAC-SHA256 over a timestamp and the body (`WEBHOOK_SECRET`), answers at once and then
  fetches the build. Calls without a valid signature, or older than five minutes, are
  refused; with no secret set, the address does not exist.
- **`hammer deploy:fetch` and `deploy:rollback`.** The host fetches the newest build from
  the client site's `dist` branch (streamed to disk, unpacked, checked), moves the panel's
  files out of it, and switches the website to it by replacing one `.htaccess` in
  `_site/`. It refreshes the panel's working copy from the same build, keeps three
  releases, and leaves the website as it was if anything fails. A rollback stays live
  until the next new build. Set `WEB_ROOT` in `.env`.
- **hammer's logo and its opinion of `artisan`, back from the first Slim Killer.**
  `php hammer` shows the logo above the commands. `php artisan <command>` gets one of
  six remarks about the name, then runs the command with hammer anyway; `php hammer
  artisan` gets one too. The PHP version check moved to `bootstrap/guard.php`, shared by
  both.
- **Editing forms you can fill in.** A page's form can now be posted. The panel checks
  it against the collection's schema (required fields, character limits, choices, whole
  numbers) and shows each problem beside its field and in a list at the top, keeping
  what was typed. Saving to GitHub follows in this branch.
- **Help while filling in a form.** Fields with a limit show "12 of 37 characters" as
  you type and stop at the limit; help text opens from a "?" beside the label; an
  optional choice offers "Let the page decide".
- **Lists you can change.** Every list in a form (sections, photos, people) has "Add",
  and each item has "Move up", "Move down" and "Remove". The form is shown again with
  the change; nothing is saved until Save. A page's web address is shown under its
  heading.
- **Saves change only what changed.** The panel writes a form back into its file field
  by field: unchanged fields, the body, a byte-order mark and Windows line endings stay
  exactly as they were, and fields the panel does not know are kept.
- **Saving to GitHub, safely.** A save is one commit on top of the branch, made through
  GitHub's API (no git on the host), and the branch only moves forward. If someone saved
  the same page after it was opened, nothing is written and the client is told; a save
  of another page at the same moment is taken into account, not lost.
- **Save in the control panel.** Save commits the changed page to the site's repository
  on GitHub, records the save in the audit log and shows "Saved."; if someone saved the
  page first, or GitHub cannot be reached, the form keeps what was typed and says why.
  After a conflict the panel fetches the newer version, so Discard changes shows it.
  Set `GITHUB_REPOSITORY`, `GITHUB_BRANCH`, `GITHUB_TOKEN` and `GITHUB_CONTENT_FOLDER`
  in `.env`.
- **Content in the control panel, read-only.** Signed-in people can open "Your content"
  from the dashboard: the site's collections, each collection's pages, and each page's
  form, filled with its real values. The forms are built from the collection schemas
  Astro writes (`astro sync`): labels, help text and editors come from each field's
  editor hint, or are chosen from the field's name and type when there is none. Saving
  arrives next.
- **The panel's working copy.** It reads content and schemas from `storage/site/`, or,
  for local development, from the folders set by `CONTENT_DIR` and `SCHEMA_DIR` in
  `.env`. Each page records its version (its Git blob hash), ready for detecting "someone
  saved this page after you" when saving is built.
- **Wider pages once signed in.** The dashboard and content pages use up to 1200 pixels
  of a wide screen. The sign-in and setup pages keep their narrow layout.
- **Fresh styles after an update.** The panel's stylesheet and script addresses end in
  the file's change time (`cp.css?v=…`), so a browser does not keep using an old copy.
- **Front matter that only changes what changed** (`SlimKiller\Content\FrontMatter`,
  from spike S4): unchanged fields keep their exact text, byte-order mark and line
  endings included. Symfony YAML is a new dependency.
- **How we build, in the wiki.** One page per build rule (why it exists, where it shows
  in the code today, when not to apply it), a Glossary with one word for each idea and
  the words we avoid, and "Writing for clients", which applies the ASD-STE100 Simplified
  Technical English writing rules to everything a client reads. `BUILD_PHILOSOPHY.md`
  links each rule to its page; the README gains a "How We Build" section; `AGENTS.md`
  asks assistants to use the glossary's terms and add new ones there first.
- **Sign-in for the control panel, with two-factor for everyone.** Email and password,
  then a six-digit code from an authenticator app (or a single-use recovery code).
  Two-factor is set up on first sign-in with a QR code drawn on the server; a code
  cannot be used twice. Passwords use Argon2id (bcrypt where PHP lacks it) and need 12
  characters or more. Five wrong passwords for an address (or twenty from one IP) means
  a 15-minute wait. Sessions live in SQLite, end after two idle hours or twelve in
  all, and get a new token at every sign-in. Every form carries a CSRF token.
  Password fields have a show/hide button, and every form message comes from the panel
  rather than the browser. Recovery codes can be downloaded as a text file (made in
  the browser; the server keeps only hashes), and each page says how to use them. The
  setup page points people to Google Authenticator and to scan from inside the app.
- **Welcome links instead of passwords on the server.** `hammer install`, `user:create`
  and `user:reset` print a link (valid 72 hours, once) where the person chooses their
  own password and sets up two-factor. Shared hosts cannot hide typed passwords on the
  command line, and Monate Media never needs to know a client's password.
- **`hammer install`.** Writes `APP_URL` (and, with `--site-name`, `APP_NAME`: the site's
  own name, shown in authenticator apps and on recovery codes) to `.env`, never
  overwriting a value; creates
  the SQLite database and the first user (an administrator), and prints their welcome link.
- **`hammer user:create`, `user:reset` and `migrate`.** Add a site owner or an
  administrator; reset someone who lost their phone or password (also signs them out
  everywhere); bring the database up to date.
- **SQLite database with migrations** in `database/migrations`, and an audit log of
  sign-ins, failed sign-ins, password and two-factor changes, and resets.
- **The control panel's first pages, at `/cp`.** Slim 4, Twig and PHP-DI, with a strict
  Content Security Policy (no inline styles or scripts, no CDN), no referrer, and no
  caching or indexing. `public/cp/index.php` is the front controller; like `hammer`, it
  tells an old PHP what is wrong instead of failing with a blank page.
  - `/cp/` says only that the panel is being set up. It becomes the login page once
    the first user exists.
  - `/cp/setup?token=...` is the setup page: the website's PHP checked live, plus the
    doctor's last report for what only the command line can check (cron, the PHP for
    cron, outgoing connections), with next steps.
- **`hammer doctor` ends with a private link to the setup page.** Each run saves the
  report for the setup page and issues a new link, which works for one hour and
  replaces the last. Only a hash of the link is stored.
- **Monate Media logo on the cover page.** In the top bar (live as well as static) and in
  the footer, like the course pages. The README notes that the Monate Media name and logo
  are not covered by the MIT License.
- **Roadmap on the cover page lines up.** The line now runs through the middle of each
  step's circle, and the tick is drawn larger and centred, rather than a font character
  that sat differently on each system.
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

- The dashboard no longer says saving is coming: it is here.
- **Code names follow the glossary.** Welcome links are `WelcomeLinks` in code (was
  `Invites`); signing in is at `/cp/sign-in` and signing out at `/cp/sign-out` (were
  `/cp/login` and `/cp/logout`), with `SignInAction`, `SignOutAction` and
  `SignInThrottle`. Audit log events are `sign_in`, `sign_in_failed` and `sign_out`.
  Migration `0002` renames the `user_invites` and `login_attempts` tables to
  `welcome_links` and `sign_in_attempts`; run `php hammer migrate` on an installed site.
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

### Fixed

- **Tests on Windows no longer print warnings at the end.** The clean-up closes each
  test's SQLite connection before deleting its temporary folder.
