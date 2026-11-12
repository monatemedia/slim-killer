<a id="readme-top"></a>

<!-- PROJECT SHIELDS -->
[![Contributors][contributors-shield]][contributors-url]
[![Forks][forks-shield]][forks-url]
[![Stargazers][stars-shield]][stars-url]
[![Issues][issues-shield]][issues-url]
[![MIT License][license-shield]][license-url]

<!-- PROJECT LOGO -->
<br />
<div align="center">
  <a href="https://github.com/monatemedia/slim-killer">
    <img src="clown-svgrepo-com.svg" alt="Slim Killer logo" width="120" height="120">
  </a>

<h3 align="center">Slim Killer</h3>

  <p align="center">
    A WordPress killer with personality.
    <br />
    Fast static websites, edited from any phone, on plain shared hosting.
    <br />
    <br />
    <a href="https://github.com/monatemedia/slim-killer/issues/new?labels=bug">Report Bug</a>
    &middot;
    <a href="https://github.com/monatemedia/slim-killer/issues/new?labels=enhancement">Request Feature</a>
  </p>
</div>

<!-- TABLE OF CONTENTS -->
<details>
  <summary>Table of Contents</summary>
  <ol>
    <li>
      <a href="#about-the-project">About The Project</a>
      <ul>
        <li><a href="#how-it-works">How It Works</a></li>
        <li><a href="#built-with">Built With</a></li>
      </ul>
    </li>
    <li>
      <a href="#getting-started">Getting Started</a>
      <ul>
        <li><a href="#prerequisites">Prerequisites</a></li>
        <li><a href="#installation">Installation</a></li>
      </ul>
    </li>
    <li><a href="#usage">Usage</a></li>
    <li><a href="#roadmap">Roadmap</a></li>
    <li>
      <a href="#contributing">Contributing</a>
      <ul>
        <li><a href="#how-we-build">How We Build</a></li>
        <li><a href="#branching-and-releases">Branching and Releases</a></li>
        <li><a href="#branch-helper-scripts">Branch Helper Scripts</a></li>
        <li><a href="#versioning">Versioning</a></li>
      </ul>
    </li>
    <li><a href="#license">License</a></li>
    <li><a href="#contact">Contact</a></li>
    <li><a href="#acknowledgments">Acknowledgments</a></li>
  </ol>
</details>

<!-- ABOUT THE PROJECT -->
## About The Project

Slim Killer is Monate Media's replacement for WordPress. It takes inspiration from WordPress (a core that updates without touching the client's content) and from Laravel (a container, conventions and an `artisan`-style CLI), but it is its own thing.

Why another one?

* **Clients should be able to edit from anywhere.** A website that can only be edited from one laptop stops being editable when that laptop dies. Slim Killer's control panel runs in the browser, including on a phone.
* **The public site should be boring to attack.** Visitors get plain static files built by Astro. There is no database or login behind the public pages.
* **It should run on cheap hosting.** The host only needs PHP. No Node.js, no VPS, no special plan.
* **Every change should be undoable.** Content lives in Git, so every save is a commit.

This is the second attempt. The first one is kept at the tag `slim-killer-v1` and the branch `archive/v1`.

> **Status:** early. `hammer`, the host checker and the control panel's sign-in (with two-factor) work; editing content is next.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

### How It Works

```
 client's phone/browser
        │  edits
        ▼
 /cp  (PHP: Slim + PHP-DI, SQLite)  ──commit via GitHub API──▶  client repo (main)
        ▲                                                            │
        │ fetch dist zip, verify, switch                             ▼
 signed webhook (cron as backup)  ◀──"build ready"──  GitHub Actions: astro build → dist branch
```

1. The client edits a page at `/cp` on her own domain and taps Save.
2. The control panel commits the change to her GitHub repository.
3. GitHub Actions builds the Astro site.
4. GitHub calls a signed webhook on her host, and the host fetches the finished build and switches to it. The host always pulls; nothing is pushed into it. In testing, a save was live in about 70 seconds.

Core, client-specific code and client content are kept apart, so a core update never touches the client's words or photos:

| Layer | What | Changed by |
|---|---|---|
| Core | Control panel, Astro integration, `hammer` CLI | Edward, through versioned releases |
| Site | One client's Astro project and custom modules | Edward |
| Content | Markdown and images in `content/` | The client, through `/cp` |

<p align="right">(<a href="#readme-top">back to top</a>)</p>

### Built With

* [![PHP][PHP-badge]][PHP-url]
* [![Slim][Slim-badge]][Slim-url]
* [![PHP-DI][PHPDI-badge]][PHPDI-url]
* [![Twig][Twig-badge]][Twig-url]
* [![SQLite][SQLite-badge]][SQLite-url]
* [![Astro][Astro-badge]][Astro-url]
* [![GitHub Actions][Actions-badge]][Actions-url]

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- GETTING STARTED -->
## Getting Started

The command-line tool (`hammer`), the host checker and the control panel's sign-in work today. Editing content is next.

### Prerequisites

For development we recommend [Laravel Herd](https://herd.laravel.com/) (Windows and macOS): one install gives you PHP 8.4 with the extensions Slim Killer needs, Composer, and a local `.test` address for each project, in place of a hand-built LAMP stack (XAMPP, WAMP, MAMP and the like). Slim Killer needs no MySQL, only SQLite, so Herd's free version is enough. On Linux, use your distribution's PHP 8.4 packages. Either way, `php hammer doctor` confirms the machine has everything.

What you need (Windows with Herd, using Git Bash, is how Slim Killer itself is built):

* PHP 8.4 and Composer
* Node.js 24 LTS and npm (for building the Astro sites, later)
* Git

For a client's host, see [Host requirements](https://github.com/monatemedia/slim-killer/wiki/Host-requirements) in the wiki. In short: PHP 8.4 for the website and the command line, a handful of common extensions, outgoing HTTPS to GitHub, `.htaccess` rewrites and cron. `php hammer doctor` checks all of it.

### Installation

1. Clone the repo
   ```sh
   git clone https://github.com/monatemedia/slim-killer.git
   cd slim-killer
   ```
2. Install the dependencies
   ```sh
   composer install
   ```
3. Check the machine
   ```sh
   php hammer doctor
   ```
4. Install: this writes `.env`, creates the database and your account, and prints a welcome link
   ```sh
   php hammer install --url=https://slim-killer.test --email=you@example.com --name="Your Name" --site-name="Slim Killer"
   ```
5. Open the welcome link, choose a password and set up two-factor with your phone.

On a hosting account, follow [Installing on a new host](https://github.com/monatemedia/slim-killer/wiki/Installing-on-a-new-host) in the wiki: the code goes outside the web root, and the doctor also checks the website itself.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- USAGE EXAMPLES -->
## Usage

Day to day, clients only ever see `/cp`. Developers work through `hammer`:

```sh
php hammer                     # list every command
php hammer doctor              # does this host have what Slim Killer needs?
php hammer doctor --url=https://example.com --webroot=~/domains/example.com/public_html
                               # ...including the website's own PHP and web server
php hammer install --url=... --email=... --name=...
                               # first-time setup: .env, database, first user
php hammer user:create --email=client@example.com --name="Client Name"
                               # add someone (a site owner unless --role=admin), print their welcome link
php hammer user:reset --email=client@example.com
                               # lost phone or password: clear both, print a new welcome link
php hammer migrate             # bring the database up to date
php hammer cron:heartbeat      # what cron runs every minute (for now)
```

Nobody types a password on the server: every account starts from a welcome link, and the person chooses their own password in the browser.

Planned: `deploy:fetch`, `deploy:rollback`, `cp:update`, `backup:run` and `fleet:test`.

And yes, `php hammer artisan` is a thing. Try it.

### Quality checks

```sh
composer check         # Pint (style), PHPStan (static analysis) and Pest (tests), in that order
composer test          # tests only
composer test:browser  # the control panel in a real browser (WebKit, at iPhone 7 size)
composer format        # fix code style
```

The browser tests drive the control panel's JavaScript through [Playwright](https://playwright.dev/), with Pest's browser plugin. They need Node.js once:

```sh
npm install
npx playwright install chromium webkit
```

They run in WebKit (Safari's engine) by default; `vendor/bin/pest --testsuite=Browser --browser chrome` tries Chromium. Each run starts PHP's built-in server with the panel on a copy of the test site (`tests/Browser/server.php`); nothing is saved to GitHub.

`feature-branch-stop.sh` runs the same checks before it merges a branch, and the browser tests too when Playwright is installed.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- ROADMAP -->
## Roadmap

- [x] Spikes S1 and S2: shared hosting can commit to GitHub, fetch and switch builds, and run cron
- [x] Host checker: `hammer doctor`
- [ ] Spikes S3 to S5: export Astro's Zod schemas; editor round trip; time from save to live
- [x] Control panel skeleton: Slim, Twig, SQLite migrations, login with two-factor, `install`, the setup page
- [ ] One collection end to end on a pilot client site
- [ ] All collections, including nested lists and single pages
- [ ] Releases and recovery: panel self-update, rollbacks, backups with a test restore
- [ ] Pilot client migration, including the move to Astro 7
- [ ] `fleet:test` across client sites
- [ ] Edge Storefront (Yoco checkout, stock)

See the [open issues](https://github.com/monatemedia/slim-killer/issues) for proposed features and known issues.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- CONTRIBUTING -->
## Contributing

Slim Killer is built and maintained by Monate Media. Suggestions and bug reports are welcome as [issues](https://github.com/monatemedia/slim-killer/issues). Before changing code, read [`BUILD_PHILOSOPHY.md`](BUILD_PHILOSOPHY.md): the twelve rules the codebase follows (see "How We Build" below). If you work with an AI coding assistant, it will pick up its instructions from [`AGENTS.md`](AGENTS.md).

### How We Build

Slim Killer follows twelve rules in two parts, taken from two PHP courses by Monate Media. The short form is in [`BUILD_PHILOSOPHY.md`](BUILD_PHILOSOPHY.md); each rule has its own page in the [wiki](https://github.com/monatemedia/slim-killer/wiki/Build-philosophy), with where it shows in the code today and when not to apply it.

- **Part one: wiring** (Rules 1 to 6), from [PHP 8.5 OOP Mastery](https://oop.monatemedia.com/): how components are connected. These always apply: configuration and the container live at the entry points, tests describe behaviour, types guard every boundary, small parts are composed rather than inherited.
- **Part two: modelling** (Rules 7 to 12), from [Advanced Domain Architecture & Tactical DDD](https://ddd.monatemedia.com/): what the components should be. Rule 7, "ceremony must be paid for", decides where the rest apply: lightly for users and sign-in, fully for the publish pipeline and the shop, and not at all for editing content, which stays plain forms over files.

Rule 11, "speak the client's words, exactly", shows outside the code too. The wiki keeps a [Glossary](https://github.com/monatemedia/slim-killer/wiki/Glossary) with one word for each idea, and everything a client reads follows [Writing for clients](https://github.com/monatemedia/slim-killer/wiki/Writing-for-clients), which uses the ASD-STE100 Simplified Technical English writing rules: one instruction per sentence, short sentences, one word for one meaning.

### Branching and Releases

There is **one long-lived branch, `main`**. Everything else is short-lived and deleted after it is merged. A **release is a version tag** (`v0.1.0`) on a commit of `main`; client sites pin core to a tag, never to a branch.

- **`main`**: the default branch. Every short-lived branch starts here and merges back here.
- **`feature/<name>`**, **`fix/<name>`**, **`chore/<name>`**, **`docs/<name>`**: short-lived, created from `main`, merged back into `main` with `--no-ff`, then deleted. `chore/` is for tooling and housekeeping, `docs/` for documentation only.
- **`archive/v1`** (and the tag `slim-killer-v1`): the first attempt at Slim Killer, kept for reference. Do not delete it.

### Branch Helper Scripts

`feature-branch-start.sh` and `feature-branch-stop.sh` handle the short-lived branch workflow. Run them from Git Bash in the repository root.

```sh
./feature-branch-start.sh <name>           # feature/<name>, from main
./feature-branch-start.sh --fix <name>     # fix/<name>
./feature-branch-start.sh --chore <name>   # chore/<name>
./feature-branch-start.sh --docs <name>    # docs/<name>
```

```sh
./feature-branch-stop.sh                   # check, gate, merge into main, clean up
./feature-branch-stop.sh --skip-gates      # same, without the quality gates (asks you to confirm)
```

The start script refuses to run with uncommitted changes. The stop script checks that `CHANGELOG.md` was updated, runs whichever quality gates are installed (Pint, PHPStan, Pest, `npm test`), pushes, merges into `main`, deletes the branch locally and on GitHub, and returns you to `main`. A failing gate stops it before anything is pushed or merged. Both scripts read the branch rules from `feature-branch-types.sh`.

### Versioning

Slim Killer uses [semantic versioning](https://semver.org/), `vMAJOR.MINOR.PATCH`. Because client sites depend on core, the version number is a promise about upgrades:

- **MAJOR** (`v1.4.2` → `v2.0.0`): a client site may need changes to upgrade, for example a new Astro major version, a removed or renamed interface, or a changed content folder layout.
- **MINOR** (`v1.4.2` → `v1.5.0`): new features that existing client sites can take without changes.
- **PATCH** (`v1.4.2` → `v1.4.3`): bug fixes and security fixes only.

Tag releases with annotated tags, and never move or reuse a tag; a fix is always a new patch tag.

```sh
git tag -a v0.1.0 -m "v0.1.0"
git push origin v0.1.0
```

Record every release in [`CHANGELOG.md`](CHANGELOG.md), moving the entries from **[Unreleased]** under the new version.

### Top Contributors

<a href="https://github.com/monatemedia/slim-killer/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=monatemedia/slim-killer" alt="contrib.rocks image" />
</a>

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- LICENSE -->
## License

Distributed under the MIT License. See [`LICENSE`](LICENSE) for more information.

The Monate Media name and logo (`public/logo-wide.png`, `public/cp/assets/monate-media.png`) are not covered by the MIT License. The clown logo is CC0, from SVG Repo.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- CONTACT -->
## Contact

Edward, Monate Media

Project link: [https://github.com/monatemedia/slim-killer](https://github.com/monatemedia/slim-killer)

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- ACKNOWLEDGMENTS -->
## Acknowledgments

* [Best-README-Template](https://github.com/othneildrew/Best-README-Template)
* [SVG Repo](https://www.svgrepo.com/svg/206648/clown) for the clown logo (CC0)
* [Shields.io](https://shields.io/)
* WordPress and Laravel, for the inspiration

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- MARKDOWN LINKS & IMAGES -->
[contributors-shield]: https://img.shields.io/github/contributors/monatemedia/slim-killer.svg?style=for-the-badge
[contributors-url]: https://github.com/monatemedia/slim-killer/graphs/contributors
[forks-shield]: https://img.shields.io/github/forks/monatemedia/slim-killer.svg?style=for-the-badge
[forks-url]: https://github.com/monatemedia/slim-killer/network/members
[stars-shield]: https://img.shields.io/github/stars/monatemedia/slim-killer.svg?style=for-the-badge
[stars-url]: https://github.com/monatemedia/slim-killer/stargazers
[issues-shield]: https://img.shields.io/github/issues/monatemedia/slim-killer.svg?style=for-the-badge
[issues-url]: https://github.com/monatemedia/slim-killer/issues
[PHP-badge]: https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white
[PHP-url]: https://www.php.net/
[Slim-badge]: https://img.shields.io/badge/Slim-719A40?style=for-the-badge&logoColor=white
[Slim-url]: https://www.slimframework.com/
[PHPDI-badge]: https://img.shields.io/badge/PHP--DI-4F5B93?style=for-the-badge&logoColor=white
[PHPDI-url]: https://php-di.org/
[Twig-badge]: https://img.shields.io/badge/Twig-BACF29?style=for-the-badge&logoColor=black
[Twig-url]: https://twig.symfony.com/
[SQLite-badge]: https://img.shields.io/badge/SQLite-003B57?style=for-the-badge&logo=sqlite&logoColor=white
[SQLite-url]: https://www.sqlite.org/
[Astro-badge]: https://img.shields.io/badge/Astro-BC52EE?style=for-the-badge&logo=astro&logoColor=white
[Astro-url]: https://astro.build/
[Actions-badge]: https://img.shields.io/badge/GitHub_Actions-2088FF?style=for-the-badge&logo=githubactions&logoColor=white
[Actions-url]: https://github.com/features/actions
[license-shield]: https://img.shields.io/github/license/monatemedia/slim-killer.svg?style=for-the-badge
[license-url]: https://github.com/monatemedia/slim-killer/blob/main/LICENSE
