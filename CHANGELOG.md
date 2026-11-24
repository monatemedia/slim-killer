# Changelog

All notable changes to Slim Killer are documented here, newest first. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/): entries are grouped as Added,
Changed, Removed and Fixed under each release. Versions are git tags (`vMAJOR.MINOR.PATCH`,
see "Versioning" in `README.md`). Work that has not been released yet sits under
**[Unreleased]** at the top.

`feature-branch-stop.sh` checks that this file was touched before it merges a branch into
`main`. If a change has nothing worth recording, you can answer "yes, continue anyway".

## [Unreleased]

## [0.4.0] - 2026-11-24

After updating, run `hammer web:install`, so the website serves the panel's new files.

### Added

- **AVIF photos can be uploaded** where the host's PHP reads AVIF (Hostinger's does).
  Like every upload, they're turned the right way up, sized and saved again as JPEG,
  dropping their location data. The setup page and `hammer doctor` say "AVIF photos can
  be uploaded too" on hosts that can.
- **HEIC photos (an iPhone's own format):** an iPhone already sends JPEG to the panel by
  itself. A HEIC file uploaded from a computer is turned into a JPEG in the browser where
  the browser reads HEIC (Safari 17 and later); elsewhere, and on the server, the panel
  says how to export it as JPEG instead of "not a photo".

## [0.3.2] - 2026-11-24

After updating, run `hammer web:install`, so the website serves the panel's new files.

### Changed

- **The copy buttons are icons:** two overlapping sheets, which turn into a tick once
  copied. If the browser won't copy, the command is selected instead, ready for Ctrl+C.
  Screen readers hear "Copy the command", then "Copied".

## [0.3.1] - 2026-11-24

Smaller fixes after connecting the first Google Drive. After updating, run
`hammer web:install`: the panel's files in the web root (the copy buttons) are only
updated by it.

### Added

- **Copy buttons** on the commands on the setup page, and on "Getting started" on the
  Slim Killer page.
- **Backups can be turned off** with `BACKUPS="off"`. Cron then makes none, and
  `hammer doctor` says what's at stake instead of warning: the panel's accounts and the
  visit history. Pages and photos are in GitHub either way.
- **A row in the comparison table:** "If the database is lost".

### Changed

- **`hammer doctor` no longer hands out a setup page link** on every run. It ends with
  "To see this report in a browser: hammer setup:link", and the new `hammer setup:link`
  gives the private link (one hour, each new one replacing the last).

### Fixed

- **`hammer backup:connect` says plainly which address to copy:** an IMPORTANT note that
  it's the one the browser landed on (`http://127.0.0.1:53682/?state=…`), not the link
  that was opened. Pasting the opened link, or the `<the address>` placeholder, now gets
  its own message instead of "There is no code".
- **The doctor says "1 warning"**, not "1 warnings".

## [0.3.0] - 2026-11-24

Backups. After updating, see the wiki page "Backups" to connect the site's Google Drive.

### Added

- **Backups.** Cron backs up the control panel's database every day after 02:00 (site
  time): copied by SQLite itself (`VACUUM INTO`), checked, gzipped, the last 7 kept in
  `storage/backups/`. With `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` set and
  `hammer backup:connect` run once, each one also goes to a folder in the root of the
  site's own Google Drive (`BACKUP_FOLDER`), encrypted with AES-256-GCM and the site's
  `BACKUP_KEY`, which keeps the last 731 (`BACKUPS_KEPT`).
  Uploads use Google's web API from PHP with the `drive.file` permission only, and are
  checked against Google's checksum. A backup that doesn't arrive is sent again the next
  hour. New commands: `hammer backup`, `hammer backup:check` (the test restore),
  `hammer backup:restore`, `hammer backup:connect` and `hammer backup:key`. `hammer doctor` warns when backups
  stop or stay on the server only. See the wiki page "Backups".
- **The comparison table** gains password storage (Argon2id), backups and "if the host
  is lost"; Photos names AVIF first and JPEG as the last fallback; its asterisk is orange.

## [0.2.1] - 2026-11-23

Two fixes, found on the first update to `v0.2.0`. After updating, run `hammer web:install`
and flush the host's CDN once.

### Fixed

- **The website's own scripts reach visitors soon after an update.** `hammer web:install`
  now writes `cp/assets/.htaccess`, which lets a CDN or browser keep `visits.js`,
  `editor-bar.js` and `editor-bar.css` for five minutes instead of the host's default
  (a week on Hostinger's CDN). After updating to `v0.2.0`, monatemedia.com showed no
  website bar until the CDN was flushed and the page reloaded with Ctrl+F5. The panel's
  own files already carry their change time in their address. The wiki's "Updating
  Slim Killer" now says to flush the CDN.
- **One address for the website.** `hammer web:install` now moves the other form of the
  address to the one in `APP_URL` with a permanent redirect: `www.example.com` to
  `example.com`, or the other way round when `APP_URL` has `www`. monatemedia.com
  answered on both, which splits search results and, because a browser keeps storage
  and sign-ins per address, showed the website bar on one and not the other. Nothing
  changes for a local address (`.test`, `localhost`) or an IP.

## [0.2.0] - 2026-11-23

The website bar: people signed in to the control panel get a small button on their own
website that takes them straight to the page's form, and their visits aren't counted.
Also Preferences, a way to try a site on your own computer (`LOCAL_WEBSITE`), branch
scripts shared by every Slim Killer repository, and a new cover page section on
WooCommerce. After updating, run `hammer migrate` (one new migration) and
`hammer web:install` (new files for the website bar). Sites need no change: the bar
comes with the `visits.js` they already load.

### Added

- **The website bar.** While someone is signed in to the control panel, a small button
  sits in the bottom-right corner of their website. Tapped, it opens to Edit this page
  (straight to that page's form), the control panel, Visits, and Hide this bar (until
  the dashboard is next opened). Drag it anywhere to move it out of the way; it stays
  there in that browser. Flick it off the screen to hide it. Visitors never see it or
  download it: `visits.js` loads it only in a browser the panel marked as signed in, and
  the bar checks with `/cp/me` before showing anything. It draws itself in a shadow
  root, so a site's styles and the bar's can't touch. See "Website bar" in the wiki.
- **Signed-in browsers aren't counted** by the visit counter, whether the bar shows or
  not. The Visits page has **Don't count this browser**, for a browser that should be
  left out even when nobody is signed in.
- **Preferences**, at `/cp/preferences` (from the dashboard): each person chooses
  whether they see the website bar.
- **`LOCAL_WEBSITE=1`** (local development only): on Herd, `slim-killer.test` serves
  the built site beside `CONTENT_DIR` at the root, like a client's host, so "Web
  address" links and the website bar can be tried on your own computer. Only when
  `APP_URL` ends in `.test` or is `localhost`; the cover page shows otherwise.
- **The branch scripts, for every Slim Killer repository.** `sync-branch-scripts.sh`
  copies `feature-branch-start.sh`, `-stop.sh` and `-types.sh` from core into another
  repository. A repository says what "tested" means in its own `feature-branch-gates.sh`
  (`CHANGELOG_REQUIRED=0`, and a `run_gates` function); without one, the stop script
  runs the gates it finds installed, as before. The start script mentions the changelog
  only where there is one.
- **A warning fails the tests.** `composer check` and `feature-branch-stop.sh` run Pest
  with `--fail-on-warning`. In parallel a PHP warning isn't shown at all, so without
  this one could reach `main` unseen (it happened in the contact form helper).

### Changed

- **"Not looking AI-made"**, a design brief in the wiki: the habits that make a page look
  machine-made (gradients, card grids, stock section order, stock or AI images, scroll
  animation, em dashes and stock phrases), and what to do instead. `AGENTS.md` now has
  "Building a client's website": an AI assistant asks the developer at the start of
  each site and follows the brief unless they opt out. The status line is brought up to
  date too.
- **The cover page:** why we don't build WooCommerce shops, and what we do instead
  (Catalogue mode, Shopify for card payments, Laravel for businesses that need more);
  "What comes in the box" gains Middleware, Form protection, Security headers and Photos
  rows; the Undo row credits WordPress's revisions; the test figure reads "behaviours
  under test" (each written test, where Pest also counts every variation it runs).
- **No more "the shop".** Slim Killer doesn't build shops (card payments go to Shopify),
  so the cover page, `BUILD_PHILOSOPHY.md`, `AGENTS.md` and the wiki (Glossary, Rule 7,
  Rule 10) no longer use one as an example. Where the full modelling rules pay off is now
  publishing and sign-in.
- **Roadmap:** `fleet:test` dropped. Each host runs a pinned tag and each site will check
  itself with `hammer site:check`, so testing every client site from one place isn't
  worth keeping their content together.
- **No client names in the product.** Examples in code comments, the Glossary and an
  older changelog entry use neutral names ("Acme Tours", `/destinations/iceland/`).

## [0.1.1] - 2026-11-23

Fixes from the first day of a live website (monatemedia.com), and one security
addition. Nothing for a site to change: check out the tag and run `hammer migrate` as
usual (there are no new migrations).

### Added

- **Passwords found in data breaches are refused.** When someone chooses a password from
  a welcome link, Slim Killer asks Have I Been Pwned whether it has turned up in a
  breach, and refuses it with how many times it was seen. Only the first five
  characters of the password's SHA-1 hash leave the server (the k-anonymity range API,
  with padding). If the service can't be reached within 5 seconds, the check is
  skipped and `breach_check_skipped` is written to the audit log, so nobody is locked
  out. See "Users and sign in" in the wiki.

### Changed

- **The setup page's next steps follow the install.** Once `hammer install` has run,
  step 3 says to sign in (or to ask for a new welcome link with `hammer user:reset`)
  instead of "Install, and create your account".
- **Slim Killer's rules, revised.** Rule 1 now names the two small doors visitors can
  reach (the visit count and the contact form); the old rules 4 and 5 are merged into
  "Keep every blast radius small"; and a new rule 6, "Collect the least, keep it the
  shortest". On the cover page they are numbered 13 to 18, after the twelve engineering
  rules.
- **The cover page** (`public/index.php`, and the static page printed from it) shows
  the name beside the clown, the version from this changelog, a comparison of what
  comes first-party with WordPress, Slim Killer and Laravel, why we don't build
  WooCommerce shops (and what we do instead), a security section, the
  product rules, and up-to-date getting-started steps. Its preview image no longer
  points at the old WordPress site.

- **`composer check` and `feature-branch-stop.sh` run the unit tests in parallel**
  (`pest --parallel`): faster, and only failures are listed. `composer test` still
  names every test.
- **The README roadmap** is brought up to date: what's done since the spikes, what's
  next (`hammer site:check`, email through the site's own mailbox), and the shop item
  replaced by Catalogue mode, since card payments go to Shopify, never Slim Killer.

### Fixed

- **Visits page on a first day.** With nothing added up yet, the chart said "Most in a
  day: 1" over an empty chart; it now shows no chart. And where a period has nothing
  yet but today has visitors, it says today's visits are added after midnight, instead
  of "Nobody was counted in these 30 days" right under "1 visitor".

## [0.1.0] - 2026-11-22

The first tagged release, and the first a live website runs. It has the host checker
(`hammer doctor`), installation and the web root, the control panel with welcome links
and two-factor sign-in, editing content and photos with saves to GitHub, publishing by
fetching builds, one cron line (`hammer cron:run`), the contact form's held messages, and
the visit counter with its daily email and Visits page. While the version starts with 0,
a minor release may still ask a site to change something; the changelog will say so.

### Added

- **Visits page in the control panel.** `/cp/visits`, from a new "Visits" tile on the
  dashboard: visitors, pages opened, buttons tapped and forms sent for the last 7, 30
  or 90 days or 12 months, each next to the same length of time before it; a chart of
  visitors day by day; where they came from, the pages, buttons, forms and devices; and
  today so far. Drawn as SVG, with no JavaScript, so it works on any phone and within
  the panel's security rules. Signed-in people only.

- **`hammer visits:sample`** fills a local copy with made-up visits, so the Visits page
  can be seen before a real website has any (`--days=90`, `--fresh`). It uses the page
  addresses of the site the copy is pointed at, from that site's last build. It refuses to run
  unless `APP_URL` ends in `.test` or is `localhost`.

- **Visit counter.** Each website can count its own visits, without cookies and
  without Google. Add `<script src="/cp/assets/visits.js" defer></script>` to every
  page. It counts pages opened, taps on WhatsApp, email and phone links (and anything
  marked `data-count`), and forms sent, with where each visit came from (campaign tags,
  a Google Ads click, or the website that sent the visitor) and the kind of device. It
  keeps no internet addresses: a visitor is a code made fresh each day from a secret
  that is deleted when the day ends, so nobody can be followed from one day to the next.
  It counts nothing when a browser sends Do Not Track or Global Privacy Control, or when
  the visitor switches "Site statistics" off. Single events and daily totals are kept
  for good; a site that must follow France's rules sets `VISIT_EVENTS_MONTHS=25` and
  older single events are deleted (the totals stay). `hammer visits` shows the last 7 days (`--days=30` for
  more). `web:install` writes `cp/visit.php`, so run it again after updating. New in
  `.env`: `TIMEZONE`, so a day starts at the business's midnight. Run `hammer migrate`.

- **Daily visit report by email.** Every morning after 06:00 (site time), yesterday's
  visitors, pages, taps and forms next to the 7-day average, with where visitors came
  from. Set `VISIT_REPORT_TO` and `MAIL_FROM` (a mailbox on the website's own domain) in
  `.env`. Each day's report goes once; if the host refuses it, cron tries again an hour
  later.

- **One email layout.** Every email Slim Killer sends is written from
  `templates/mail/layout.html.twig` and its plain-text twin `layout.txt.twig`, and sent
  with both parts. A new email is two small templates that fill in the layout.

- **One cron line: `hammer cron:run`.** Cron runs it every minute, and it runs whatever
  is due: it notes that cron ran (for the doctor), sends the contact form's held
  messages (`contact.php` in the live release, from slim-killer-forms), and every five
  minutes checks for a build the webhook missed. Each job has its own lock, so a slow
  fetch never holds up the others, and its own try/catch, so one failure does not stop
  the rest. It prints nothing when all is well; `-v` lists each job. Sites add jobs of
  their own to the container's `cron.jobs` list.

- **Publishing chip.** Publishing shows as a small chip under the logo ("Building…",
  "Live ✓") with a coloured light, instead of a large box above the page; tap it for the
  steps, the message and the estimate.
- **Light and dark switch.** The panel follows the device's setting until the switch in
  the header is used; the choice is then kept on that device and applied before the page
  is drawn (`theme.js`).

- **Focus point picker.** An image's "focus" box becomes the photo with a dot: tap the part
  that must stay in view when the website crops it (or use the arrow keys), and two small
  crops, wide and square, show the result. Stored as `x% y%`, which the site uses as the
  photo's object-position; values written in CSS words (`center 30%`) show in the right
  place. A new photo starts with its focus in the middle; **Back to the middle** clears
  it. Without JavaScript the box stays a text box.

- **Browser tests.** The control panel's JavaScript is tested in a real browser with Pest's
  browser plugin and Playwright, in WebKit (Safari's engine) at the size of an iPhone 7:
  the form fits the screen with nothing to scroll sideways, a picked photo shows at once
  and asks for a new description, the photo chooser fills the field, and a field's tip
  stays on screen. `composer test:browser` runs them; `feature-branch-stop.sh` does too
  when Playwright is installed. Each run serves the real panel on a copy of the test site
  with PHP's built-in server.

- **Photo upload.** An image field shows its photo with its file name underneath, and two
  buttons: **Upload a new photo** (JPEG, PNG or WebP, up to 20 MB) and **Choose a photo**
  for the site's existing photos. The file name is not a box to type in: a new photo is
  named after the file it came from ("Lions at dusk.jpg" → `lions-at-dusk.jpg`). When that
  name says nothing (an iPhone's random name, `IMG_2041`, `PXL_…`, WhatsApp's), it is named
  from the first words of the photo's description ("A lion at a waterhole" →
  `lion-at-a-waterhole.jpg`), or else after the page (`discover-botswana.jpg`). Changing
  the photo clears its description (keeping the old one as a faint "Was: …" to retype) and
  highlights the box, saying why it matters: a description belongs to one photo. An upload is
  checked (its bytes and its content must agree it is a photo, at most 40 megapixels),
  turned the right way up, made at most 2400 px on the long edge and re-encoded as a JPEG
  at quality 85 (a transparent PNG stays PNG), which drops EXIF, GPS included, and
  anything hidden in the file. It is kept on the host, outside the web root, under a clean
  name, and goes to GitHub with the page in the same commit when Save is pressed; list
  buttons in between keep it, and unsaved photos are forgotten after a day.
- **Photo previews.** The site's build carries a small preview of every photo
  (`_slim-killer/previews/`, kept beside the working copy); a photo uploaded through the
  panel shows its own preview until the next build. Signed-in people get them at
  `/cp/photos/<name>`.

- **The doctor checks for photo uploads.** `exif` is now a required extension (it turns
  phone photos the right way up), and GD must be able to write a JPEG, tested by writing
  one. WebP reading is reported as information; the WebP-writing and AVIF lines are gone,
  since uploads are saved as JPEG.
- **README recommends Laravel Herd** for local development, in place of a LAMP stack.

- **The page's real web address.** A content page shows where it is on the website, as a
  link that opens it (for example `/destinations/iceland/`). The addresses come from the
  site's build (`_slim-killer/addresses.json`, worked out from the site's
  `slim-killer.json` and checked against the built pages) and are kept beside the working
  copy. Without them the panel shows no address, rather than guessing one.
- **Real redirects for old addresses.** The build's `_slim-killer/redirects.json` becomes
  301 rules in the live release's `.htaccess`, so an old address (with or without its
  trailing slash) reaches its new page in one step. Each release keeps its own, so a
  rollback brings back that release's redirects. Anything that is not a plain path, or a
  full http(s) address as the target, is left out.
- **`HIDE_FROM_SEARCH` for test copies.** Set to `true` in `.env`, `web:install` adds an
  `X-Robots-Tag: noindex, nofollow` header for the whole website.

- **`hammer web:install`.** Sets up the website folder (`WEB_ROOT`): an `.htaccess` that
  sends `/cp` to the control panel and everything else to the live release, a stub
  `cp/index.php` that loads the panel from the project folder outside the web root, and
  the panel's assets. Safe to run again after an update; an `.htaccess` it did not write
  is kept as `.htaccess.before-slim-killer`. Old releases can no longer be opened by
  their folder name (`/_site/<release>/`).
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

- **The doctor asks for `cron:run`.** Its cron line is now `hammer cron:run`. A host that
  still runs `cron:heartbeat` gets a warning: change the line, and delete the separate
  `deploy:fetch` cron job. `cron:heartbeat` keeps working until then.

- **The logo and the name** are one link to the dashboard (only the logo was).
- **Focus rings** follow the rounded corners of fields and buttons (a box-shadow ring;
  older Safari drew a square outline around rounded fields).
- **Small phones** give the form more width: smaller page, card and group margins below
  520 px.

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

- **Made-up visits leave out the 404 page and files.** `hammer visits:sample` only uses
  page addresses that end in `/`, so `/404.html` and `/llms.txt` no longer show as pages.
- **A gallery photo's description.** Changing the second photo in a gallery cleared the
  first photo's description: the description was looked for in the whole gallery rather
  than in the photo's own item.
- **The website's own 404 page.** `web:install` names `/404.html` as the error page in the
  website folder's `.htaccess` as a backstop, but missing pages are now answered by
  `cp/not-found.php`: the release's rules send any address it doesn't have there, and the
  script returns the live release's 404 page with a 404 status. LiteSpeed did not reliably
  honour `ErrorDocument` behind rewrites (the host's generic page came back for some
  addresses and not others). Every switch copies the live `404.html` to the website
  folder, so a rollback brings back that release's page; `web:install` re-applies the
  live release's rules. It no longer suggests `deploy:fetch` when a release is live.
- **Tests on Windows no longer print warnings at the end.** The clean-up closes each
  test's SQLite connection before deleting its temporary folder.
