<?php

declare(strict_types=1);

/**
 * index.php: Slim Killer's cover page.
 * =====================================
 *
 * Two jobs, one file:
 *
 *   1. LIVE: Herd serves it at https://slim-killer.test. It reports this machine's
 *      PHP, whether dependencies are installed, and the extensions Slim Killer needs,
 *      the way the course cover pages do.
 *   2. STATIC: `php public/index.php --static > ../monatemedia-course-pages/slim-killer/index.html`
 *      prints the public page for slimkiller.monatemedia.com, without anything
 *      machine-specific. One source, so the two can never drift apart.
 *
 * PART THREE OF A SET. The OOP Mastery page is a dark terminal (wiring components);
 * the Domain Architecture page is warm paper (modelling a business). This one is a
 * poster: light, loud and confident, because Slim Killer is a product people install,
 * not a course. It keeps the shared bones on purpose: eyebrow, lede, status pills and
 * progress, the one question, the rules, what is proved, getting started.
 *
 * Self-contained: no Composer autoload, no CDN, no build step. The one image file is
 * the Monate Media logo, logo-wide.png, beside this file (copy it next to the static page).
 * It must render before `composer install` has ever run. Numbers that can change are
 * read from disk (the README roadmap, the test files), never written down here. The
 * numbers that cannot change are measurements from spikes S1 and S2, and say so.
 *
 * On a client's host the domain root serves the client's Astro site, never this page.
 * Locally, APP_ENV="local" in .env does the same on Herd (see below).
 */

// On Herd (nginx), a path with no file behind it falls through to this script. Hand
// the control panel's paths to its own front controller, as public/cp/.htaccess does
// on Apache and LiteSpeed. On a client's host this file is never served.
$requestPath = parse_url(is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
if (PHP_SAPI !== 'cli' && is_string($requestPath) && str_starts_with($requestPath, '/cp/')) {
    require __DIR__ . '/cp/index.php';

    return;
}

// ─────────────────────────────────────────────────────────────────────────────
// Local development only: APP_ENV="local" in .env makes https://slim-killer.test
// behave like a client's host, with the built site of CONTENT_DIR at the root
// (../site/dist beside that content folder) and the panel at /cp. Then the panel's
// "Web address" links and the website bar can be tried on this machine. Only when
// APP_URL ends in .test or is localhost; on a client's host this file is never served.
// Build the site first (npm run build:panel in the site's repository).
// ─────────────────────────────────────────────────────────────────────────────

if (PHP_SAPI !== 'cli' && is_string($requestPath) && is_file(dirname(__DIR__) . '/.env')) {
    $env = [];
    foreach (file(dirname(__DIR__) . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (preg_match('/^\s*([A-Z_]+)\s*=\s*(.*?)\s*$/', $line, $pair) === 1) {
            $env[$pair[1]] = trim($pair[2], "\"'");
        }
    }
    $local = (string) preg_replace('#^https?://#', '', $env['APP_URL'] ?? '');
    $isLocal = str_ends_with(rtrim($local, '/'), '.test') || str_starts_with($local, 'localhost');
    $contentDir = $env['CONTENT_DIR'] ?? '';
    $localMode = strtolower($env['APP_ENV'] ?? '') === 'local'
        || (($env['APP_ENV'] ?? '') === '' && in_array(strtolower($env['LOCAL_WEBSITE'] ?? ''), ['1', 'true', 'yes'], true));
    if ($localMode && $isLocal && $contentDir !== '') {
        $base = preg_match('#^([a-zA-Z]:)?[/\\\\]#', $contentDir) === 1 ? $contentDir : dirname(__DIR__) . '/' . $contentDir;
        $dist = realpath($base . '/../site/dist');
        if ($dist !== false) {
            $file = realpath($dist . '/' . ltrim(rawurldecode($requestPath), '/'));
            if ($file !== false && is_dir($file)) {
                $file = realpath($file . '/index.html');
            }
            $status = 200;
            // Only files inside the build: nothing a crafted path could reach beyond it.
            if ($file === false || ! is_file($file) || ! str_starts_with($file, $dist . DIRECTORY_SEPARATOR)) {
                $file = $dist . '/404.html';
                $status = 404;
            }
            $types = [
                'html' => 'text/html; charset=utf-8', 'css' => 'text/css', 'js' => 'text/javascript', 'mjs' => 'text/javascript',
                'json' => 'application/json', 'xml' => 'application/xml', 'txt' => 'text/plain; charset=utf-8',
                'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                'webp' => 'image/webp', 'avif' => 'image/avif', 'gif' => 'image/gif', 'ico' => 'image/x-icon',
                'woff' => 'font/woff', 'woff2' => 'font/woff2', 'webmanifest' => 'application/manifest+json',
            ];
            http_response_code($status);
            header('Content-Type: ' . ($types[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
            if (is_file($file)) {
                readfile($file);
            }

            return;
        }
    }
}

$root = dirname(__DIR__);
$static = PHP_SAPI === 'cli' && in_array('--static', $argv ?? [], true);
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

// ─────────────────────────────────────────────────────────────────────────────
// Requirements: shared with `hammer doctor`, so the page and the doctor agree.
// Requirements.php uses typed class constants (PHP 8.3+); older PHP gets the
// fallback values and a red pill.
// ─────────────────────────────────────────────────────────────────────────────

$minPhp = '8.4';
$requiredExtensions = ['curl', 'openssl', 'json', 'pdo_sqlite', 'zip', 'gd', 'mbstring', 'fileinfo'];
if (PHP_VERSION_ID >= 80300 && is_file($root . '/src/Doctor/Requirements.php')) {
    require_once $root . '/src/Doctor/Requirements.php';
    $minPhp = SlimKiller\Doctor\Requirements::MIN_PHP_LABEL;
    $requiredExtensions = array_keys(SlimKiller\Doctor\Requirements::REQUIRED_EXTENSIONS);
}

$phpOk = version_compare(PHP_VERSION, $minPhp . '.0', '>=');
$vendorOk = is_file($root . '/vendor/autoload.php');
$missing = array_values(array_filter($requiredExtensions, static fn(string $x): bool => ! extension_loaded($x)));
$webpOk = function_exists('imagewebp');

// ─────────────────────────────────────────────────────────────────────────────
// The roadmap, read from the README's checkboxes
// ─────────────────────────────────────────────────────────────────────────────

$roadmap = [];
$readme = is_file($root . '/README.md') ? (string) file_get_contents($root . '/README.md') : '';
if (preg_match('/^## Roadmap\s*$(.*?)^## /ms', $readme, $section) === 1) {
    preg_match_all('/^- \[([ xX])\] (.+)$/m', $section[1], $items, PREG_SET_ORDER);
    foreach ($items as $item) {
        $roadmap[] = ['done' => strtolower($item[1]) === 'x', 'text' => trim($item[2])];
    }
}
$done = count(array_filter($roadmap, static fn(array $r): bool => $r['done']));
$total = count($roadmap);
$pct = $total > 0 ? (int) round($done / $total * 100) : 0;

/** Roadmap text may contain `code`; escape everything, then restore code spans. */
$markup = static fn(string $s): string => (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', $e($s));

// The newest released version: the first "## [x.y.z]" heading in the changelog.
$version = '';
$changelog = is_file($root . '/CHANGELOG.md') ? (string) file_get_contents($root . '/CHANGELOG.md') : '';
if (preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $release) === 1) {
    $version = 'v' . $release[1];
}

// ─────────────────────────────────────────────────────────────────────────────
// Live counts
// ─────────────────────────────────────────────────────────────────────────────

$testCount = 0;
$checkCount = count(glob($root . '/src/Doctor/Checks/*Check.php') ?: []);
if (is_dir($root . '/tests')) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/tests', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file instanceof SplFileInfo && str_ends_with($file->getFilename(), 'Test.php')) {
            $testCount += preg_match_all('/^\s*(it|test)\(/m', (string) file_get_contents($file->getPathname()));
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// The logo, inlined, so the page needs no image files
// ─────────────────────────────────────────────────────────────────────────────

$logo = '';
if (is_file($root . '/clown-svgrepo-com.svg')) {
    $svg = (string) file_get_contents($root . '/clown-svgrepo-com.svg');
    $svg = (string) preg_replace(['/<\?xml.*?\?>/s', '/<!DOCTYPE.*?>/s', '/<!--.*?-->/s', '/\s(width|height)="[^"]*"/'], '', $svg);
    $logo = trim((string) preg_replace('/<svg\b/', '<svg role="img" aria-label="Slim Killer clown logo"', $svg, 1));
}
$favicon = $logo !== '' ? 'data:image/svg+xml;base64,' . base64_encode($logo) : '';

// ─────────────────────────────────────────────────────────────────────────────
// Links. Absolute, because the static page is read far away from this repo.
// ─────────────────────────────────────────────────────────────────────────────

$repo = 'https://github.com/monatemedia/slim-killer';
$wiki = $repo . '/wiki';
$oop = 'https://oop.monatemedia.com/';
$ddd = 'https://ddd.monatemedia.com/';
$site = 'https://slimkiller.monatemedia.com/';

$wiring = [
    'Config and the container belong at the entry point',
    'Test behaviours, not layouts',
    'The type system is a security layer',
    'Favour composition over inheritance',
    'Objects either hold state or perform work, rarely both',
    'Read the failing test before reading the code',
];
$modelling = [
    'Ceremony must be paid for',
    'The dependency rule points inward',
    'Make illegal states unrepresentable',
    'The aggregate is a consistency boundary, and it is smaller than you think',
    "Speak the client's words, exactly",
    'A failure the business can predict is not exceptional',
];
// Slim Killer's own six (wiki: Slim Killer's rules), numbered on after the twelve.
$product = [
    'Static to the world',
    'A login edits content, never runs code',
    'The repository is the truth; the host is a cache',
    'Keep every blast radius small',
    'Assume instant discovery',
    'Collect the least, keep it the shortest',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Slim Killer: a WordPress killer with personality · Monate Media</title>
<meta name="description" content="Fast static Astro websites that clients edit from their phone, on plain shared hosting. No plugins to patch, no database or PHP behind the pages, two-factor sign-in for editors. Part three of the Monate Media PHP series. MIT licensed.">
<?php if ($static): ?>
<link rel="canonical" href="<?= $site ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Monate Media">
<meta property="og:title" content="Slim Killer: a WordPress killer with personality">
<meta property="og:description" content="Static Astro sites, edited from a phone, on plain shared hosting. Part three of the Monate Media PHP series, after OOP Mastery and Domain Architecture.">
<meta property="og:image" content="<?= $site ?>logo-wide.png">
<meta name="twitter:card" content="summary">
<?php endif; ?>
<?php if ($favicon !== ''): ?>
<link rel="icon" type="image/svg+xml" href="<?= $favicon ?>">
<?php endif; ?>
<style>
  :root {
    --cream:   #fff8f1;
    --panel:   #ffffff;
    --ink:     #1c1917;
    --muted:   #57534e;
    --faint:   #8a837c;
    --orange:  #f95428;
    --red:     #d12f19;
    --blue:    #2197d8;
    --sky:     #2ba5f7;
    --skin:    #ffd3ae;
    --skin-lt: #ffe9d6;
    --ok:      #15803d;
    --bad:     #b3261e;
    --shadow:  5px 5px 0 var(--ink);
    --maxw:    1040px;
    --sans:    ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    --mono:    ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
  }
  * { box-sizing: border-box; }
  .hero-grid > *, .figures > *, .branches > *, ol.flow > li, .rules-grid > *, .series > * { min-width: 0; }
  html { -webkit-text-size-adjust: 100%; scroll-behavior: smooth; }
  body { margin: 0; background: var(--cream); color: var(--ink); font: 16px/1.65 var(--sans); -webkit-font-smoothing: antialiased; }
  .wrap { max-width: var(--maxw); margin: 0 auto; padding-inline: 1.4rem; }
  a { color: var(--blue); text-decoration-thickness: 2px; text-underline-offset: 3px; }
  code { font-family: var(--mono); font-size: 0.88em; background: var(--skin-lt); padding: 0.05em 0.35em; border-radius: 4px; }
  h1, h2, h3 { font-weight: 900; letter-spacing: -0.035em; line-height: 1.05; }

  /* ── Top bar ──────────────────────────────────────────────────────────── */
  .topbar { background: var(--ink); color: #fff; }
  .topbar .wrap { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding-block: 0.8rem; flex-wrap: wrap; }
  .topbar img { height: 54px; width: auto; display: block; }
  .series-nav { display: flex; gap: 0.4rem; flex-wrap: wrap; font-size: 0.8rem; }
  .series-nav a, .series-nav span { color: #fff; text-decoration: none; border: 2px solid #fff; border-radius: 999px; padding: 0.2rem 0.75rem; font-weight: 700; }
  .series-nav a:hover { background: #fff; color: var(--ink); }
  .series-nav span { background: var(--orange); border-color: var(--orange); }

  /* ── Hero ─────────────────────────────────────────────────────────────── */
  header.hero { position: relative; overflow: hidden; padding-block: 3.6rem 3.4rem; border-bottom: 4px solid var(--ink);
    background: repeating-linear-gradient(-45deg, var(--cream) 0 28px, #fff2e6 28px 56px); }
  .hero-grid { display: grid; gap: 2rem; align-items: center; }
  @media (min-width: 820px) { .hero-grid { grid-template-columns: 1fr 260px; } }
  .eyebrow { display: inline-block; font-family: var(--mono); font-size: 0.74rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase;
    background: var(--ink); color: #fff; padding: 0.3rem 0.7rem; border-radius: 6px; margin-bottom: 1.2rem; }
  h1 { font-size: clamp(2.7rem, 7.5vw, 4.9rem); margin: 0 0 1.2rem; }
  h1 .pop { color: var(--orange); display: block; }
  .lede { font-size: 1.18rem; max-width: 60ch; margin: 0 0 1rem; }
  .lede.small { font-size: 1rem; color: var(--muted); }
  .lede.after { margin-top: 1.5rem; }
  /* The clown and the name, tilted together. Above the headline on a phone, beside it on a wide screen. */
  .brand { justify-self: center; align-self: start; display: flex; flex-direction: column; align-items: center; gap: 1.3rem; }
  .logo-badge { width: 240px; height: 240px; border-radius: 50%; background: var(--skin); border: 4px solid var(--ink);
    box-shadow: 8px 8px 0 var(--ink); display: grid; place-items: center; transform: rotate(-6deg); }
  .logo-badge svg { width: 170px; height: 170px; }
  /* "Slim" leans with the clown, "Killer" leans the other way. */
  .wordmark { margin: 0; font-weight: 900; font-size: 4.6rem; line-height: 0.9; letter-spacing: -0.05em; text-align: center; }
  .wordmark span { display: block; }
  .wordmark .slim { transform: rotate(-6deg); }
  .wordmark .killer { color: var(--orange); text-shadow: 4px 4px 0 var(--ink); transform: rotate(6deg); }
  @media (max-width: 819px) {
    .brand { order: -1; flex-direction: row; gap: 1.1rem; justify-self: start; }
    .logo-badge { width: 112px; height: 112px; box-shadow: 5px 5px 0 var(--ink); }
    .logo-badge svg { width: 78px; height: 78px; }
    .wordmark { font-size: 2.9rem; text-align: left; }
    .wordmark .killer { text-shadow: 3px 3px 0 var(--ink); }
  }

  .status { display: flex; flex-wrap: wrap; gap: 0.5rem; margin: 1.5rem 0 0; }
  .pill { display: inline-flex; align-items: center; gap: 0.45rem; background: var(--panel); border: 2px solid var(--ink); border-radius: 999px;
    padding: 0.25rem 0.8rem; font-size: 0.82rem; font-weight: 600; }
  .pill .dot { width: 9px; height: 9px; border-radius: 50%; background: var(--faint); }
  .pill.ok .dot { background: var(--ok); }
  .pill.bad { border-color: var(--bad); color: var(--bad); }
  .pill.bad .dot { background: var(--bad); }

  .progress { margin-top: 1.4rem; background: var(--panel); border: 3px solid var(--ink); border-radius: 12px; box-shadow: var(--shadow); padding: 0.9rem 1.1rem; max-width: 34rem; }
  .progress .row { display: flex; justify-content: space-between; gap: 1rem; font-size: 0.88rem; color: var(--muted); }
  .progress .row strong { color: var(--ink); }
  .bar { height: 12px; border: 2px solid var(--ink); border-radius: 999px; overflow: hidden; margin: 0.55rem 0 0.45rem; background: var(--skin-lt); }
  .bar i { display: block; height: 100%; background: repeating-linear-gradient(-45deg, var(--orange) 0 10px, var(--red) 10px 20px); }

  .cta { display: flex; flex-wrap: wrap; gap: 0.8rem; margin-top: 1.8rem; }
  .btn { display: inline-block; font-weight: 800; text-decoration: none; color: var(--ink); background: var(--panel); border: 3px solid var(--ink);
    border-radius: 10px; padding: 0.6rem 1.15rem; box-shadow: 4px 4px 0 var(--ink); transition: transform .08s, box-shadow .08s; }
  .btn:hover { transform: translate(2px, 2px); box-shadow: 2px 2px 0 var(--ink); }
  .btn.primary { background: var(--orange); color: #fff; }

  /* ── Sections ─────────────────────────────────────────────────────────── */
  section { padding-block: 3.6rem; border-bottom: 4px solid var(--ink); }
  section.alt { background: var(--panel); }
  section.blue { background: var(--blue); color: #fff; }
  section.blue .sub { color: #e6f4fd; }
  section.blue a { color: #fff; }
  section.blue .btn { color: var(--ink); }
  section.blue .btn.primary { color: #fff; }
  h2 { font-size: clamp(1.9rem, 4.5vw, 2.7rem); margin: 0 0 0.6rem; }
  .sub { color: var(--muted); margin: 0 0 2rem; max-width: 64ch; font-size: 1.05rem; }
  .card { background: var(--panel); color: var(--ink); border: 3px solid var(--ink); border-radius: 14px; box-shadow: var(--shadow); padding: 1.5rem; }

  /* Figures */
  .figures { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
  .fig b { display: block; font-size: 2.6rem; font-weight: 900; letter-spacing: -0.04em; line-height: 1; color: var(--orange); }
  .fig span { display: block; margin-top: 0.45rem; font-size: 0.92rem; color: var(--muted); }

  /* The question */
  .question blockquote { margin: 0; font-size: clamp(1.4rem, 3.4vw, 2.1rem); font-weight: 900; letter-spacing: -0.03em; line-height: 1.15; }
  .branches { display: grid; gap: 1rem; margin-top: 1.6rem; }
  @media (min-width: 700px) { .branches { grid-template-columns: 1fr 1fr; } }
  .branch { border: 3px solid var(--ink); border-radius: 12px; padding: 1rem 1.2rem; }
  .branch.no { background: var(--skin-lt); }
  .branch.yes { background: #e3f3fd; }
  .branch b { display: block; font-family: var(--mono); font-size: 0.78rem; letter-spacing: 0.12em; text-transform: uppercase; margin-bottom: 0.3rem; }

  /* How it works */
  ol.flow { list-style: none; margin: 0; padding: 0; display: grid; gap: 1rem; counter-reset: f; }
  @media (min-width: 760px) { ol.flow { grid-template-columns: repeat(4, 1fr); } }
  ol.flow li { counter-increment: f; position: relative; padding-top: 3.2rem; }
  ol.flow li::before { content: counter(f); position: absolute; top: 1.1rem; left: 1.4rem; width: 2.2rem; height: 2.2rem; border-radius: 50%;
    background: var(--orange); color: #fff; border: 3px solid var(--ink); display: grid; place-items: center; font-weight: 900; }
  ol.flow h3 { font-size: 1.15rem; margin: 0.6rem 0 0.4rem; letter-spacing: -0.02em; }
  ol.flow p { margin: 0; font-size: 0.93rem; color: var(--muted); }

  /* Compare */
  table.compare { width: 100%; border-collapse: separate; border-spacing: 0; background: var(--panel); border: 3px solid var(--ink); border-radius: 14px;
    box-shadow: var(--shadow); overflow: hidden; font-size: 0.95rem; }
  .compare th, .compare td { text-align: left; padding: 0.75rem 1rem; border-bottom: 2px solid #efe6dc; vertical-align: top; }
  .compare tr:last-child td { border-bottom: 0; }
  .compare thead th { background: var(--ink); color: #fff; font-size: 0.82rem; letter-spacing: 0.06em; text-transform: uppercase; }
  .compare td:first-child { font-weight: 800; white-space: nowrap; }
  .compare td.us { background: #fff4ec; }
  .compare th.us { background: var(--orange); }
  table.three { min-width: 760px; }
  table.three td:first-child { white-space: normal; width: 18%; }
  table.three td { width: 27%; }
  .security p { margin: 0; font-size: 1.12rem; }
  .security p + p { margin-top: 0.9rem; }
  .star { text-decoration: none; font-weight: 800; color: var(--orange); }
  .footnote { margin: 1rem 0 0; font-size: 0.85rem; color: var(--muted); }
  ul.reasons { margin: 0; padding: 0; list-style: none; display: grid; gap: 0.9rem; }
  ul.reasons li { padding-left: 1.6rem; position: relative; }
  ul.reasons li::before { content: ""; position: absolute; left: 0; top: 0.55em; width: 0.7rem; height: 0.7rem; border-radius: 50%; background: var(--orange); border: 2px solid var(--ink); }
  .rules-grid .wide { grid-column: 1 / -1; }
  @media (min-width: 820px) { .rules-grid .wide ol.rules { column-count: 2; column-gap: 2.4rem; } .rules-grid .wide ol.rules li { break-inside: avoid; } .rules-grid .wide ol.rules li:nth-child(4) { border-top: 0; } }
  .scroll { overflow-x: auto; padding: 0 6px 8px 0; }

  /* Rules */
  .rules-grid { display: grid; gap: 1.4rem; }
  @media (min-width: 820px) { .rules-grid { grid-template-columns: 1fr 1fr; } }
  .rules-grid h3 { font-size: 1.3rem; margin: 0 0 0.2rem; }
  .rules-grid .from { font-size: 0.88rem; color: var(--muted); margin: 0 0 1rem; }
  ol.rules { list-style: none; margin: 0; padding: 0; }
  ol.rules li { display: grid; grid-template-columns: 2.2rem 1fr; gap: 0.6rem; padding: 0.55rem 0; border-top: 2px dashed #ecdccc; font-weight: 600; }
  ol.rules li:first-child { border-top: 0; }
  ol.rules li span { font-family: var(--mono); font-weight: 800; color: var(--orange); }
  .note { margin-top: 1.6rem; border: 3px solid var(--ink); border-left-width: 10px; border-left-color: var(--orange); background: #fff4ec;
    border-radius: 10px; padding: 0.9rem 1.2rem; font-size: 0.95rem; }

  /* Proof */
  .proof pre { margin: 1rem 0 0; background: var(--ink); color: #f5efe8; border-radius: 10px; padding: 1.1rem 1.2rem; overflow-x: auto;
    font-family: var(--mono); font-size: 0.82rem; line-height: 1.55; }
  .proof pre b { color: #7ee2a8; font-weight: 700; }
  .proof pre i { color: #ffb59c; font-style: normal; }

  /* Roadmap spine */
  ol.spine { list-style: none; margin: 0; padding: 0; position: relative; }
  /* One 32px circle per step, centred on a 4px line: line left = 16px - 2px. */
  ol.spine::before { content: ""; position: absolute; left: 14px; top: 16px; bottom: 16px; width: 4px; background: var(--ink); border-radius: 2px; }
  ol.spine li { position: relative; min-height: 32px; padding: 0.2rem 0 1.25rem 3.3rem; font-weight: 600; }
  ol.spine li::before { content: ""; box-sizing: border-box; position: absolute; left: 0; top: 0; width: 32px; height: 32px; border-radius: 50%; background: var(--panel); border: 4px solid var(--ink); }
  ol.spine li.done::before { background: var(--orange); }
  ol.spine li.done { color: var(--muted); }
  /* The tick is drawn, not a font glyph, so it sits in the middle of the circle on every system. */
  ol.spine li.done::after { content: ""; box-sizing: border-box; position: absolute; left: 11px; top: 6px; width: 10px; height: 17px; border: solid #fff; border-width: 0 4px 4px 0; transform: rotate(45deg); }

  /* Steps */
  ol.steps { list-style: none; margin: 0; padding: 0; counter-reset: s; display: grid; gap: 1.2rem; }
  ol.steps li { counter-increment: s; position: relative; padding-left: 3rem; min-width: 0; }
  ol.steps li::before { content: counter(s); position: absolute; left: 0; top: 0; width: 2rem; height: 2rem; border-radius: 50%; background: var(--ink); color: #fff;
    display: grid; place-items: center; font-weight: 900; font-size: 0.9rem; }
  ol.steps h3 { font-size: 1.1rem; margin: 0.2rem 0 0.35rem; letter-spacing: -0.02em; }
  ol.steps p { margin: 0 0 0.5rem; color: var(--muted); }
  ol.steps pre { margin: 0; background: var(--ink); color: #f5efe8; border-radius: 8px; padding: 0.6rem 0.9rem; font-family: var(--mono); font-size: 0.84rem; overflow-x: auto; }
  .cmd { position: relative; }
  .cmd pre { padding-right: 3rem; }
  .copy-btn { position: absolute; top: 0.4rem; right: 0.4rem; display: flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; padding: 0; border: 1px solid #5a534c; border-radius: 6px; background: #2c2723; color: #f5efe8; cursor: pointer; }
  .copy-btn svg { display: block; }
  .copy-btn.copied { color: #6fd08c; border-color: #6fd08c; }
  .copy-btn:hover { border-color: var(--orange); }
  .copy-area { position: fixed; top: 0; left: 0; width: 1px; height: 1px; opacity: 0; }

  /* Series */
  .series { display: grid; gap: 1.2rem; }
  @media (min-width: 820px) { .series { grid-template-columns: repeat(3, 1fr); } }
  .series .card { display: flex; flex-direction: column; }
  .series .part { font-family: var(--mono); font-size: 0.75rem; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; color: var(--orange); }
  .series h3 { font-size: 1.3rem; margin: 0.3rem 0 0.5rem; letter-spacing: -0.02em; }
  .series p { margin: 0 0 1rem; color: var(--muted); font-size: 0.95rem; flex: 1; }
  .series .here { background: var(--skin-lt); }
  .series .btn { align-self: flex-start; font-size: 0.9rem; padding: 0.45rem 0.9rem; }
  .audience { font-size: 1.15rem; max-width: 66ch; margin: 0 0 1.8rem; }

  footer { padding-block: 2.4rem 3.2rem; color: var(--muted); font-size: 0.86rem; }
  footer a { color: var(--ink); }
  /* The logo is white on transparent; brightness(0) makes it ink for the light footer. */
  .footer-logo img { height: 66px; width: auto; display: block; margin-bottom: 1rem; filter: brightness(0); opacity: 0.6; }
  footer .joke { margin-top: 0.8rem; font-weight: 700; color: var(--ink); }
  @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } .btn { transition: none; } }
</style>
</head>
<body>

<div class="topbar">
  <div class="wrap">
    <a href="https://www.monatemedia.com/"><img src="logo-wide.png" alt="Monate Media"></a>
    <nav class="series-nav" aria-label="The series">
      <a href="<?= $oop ?>">1 · OOP Mastery</a>
      <a href="<?= $ddd ?>">2 · Domain Architecture</a>
      <span aria-current="page">3 · Slim Killer</span>
    </nav>
  </div>
</div>

<header class="hero">
  <div class="wrap hero-grid">
    <div>
      <span class="eyebrow">Part three<?= $version !== '' ? ' · ' . $e($version) : '' ?> · Open source · PHP <?= $e($minPhp) ?> · MIT</span>
      <h1>A WordPress killer, <span class="pop">with personality.</span></h1>
      <p class="lede">
        Fast static websites that clients edit from their phone, on plain shared hosting. No
        plugins to patch, no database or PHP behind the pages, no Node.js on the server,
        two-factor sign-in for editors, and every change saved as a Git commit you can undo.
      </p>

      <?php if (! $static): ?>
        <div class="status" aria-label="This machine">
          <span class="pill <?= $phpOk ? 'ok' : 'bad' ?>"><span class="dot"></span>PHP <?= $e(PHP_VERSION) ?><?= $phpOk ? '' : ', needs ' . $e($minPhp) ?></span>
          <span class="pill <?= $vendorOk ? 'ok' : '' ?>"><span class="dot"></span><?= $vendorOk ? 'Dependencies installed' : 'Run composer install' ?></span>
          <span class="pill <?= $missing === [] ? 'ok' : 'bad' ?>"><span class="dot"></span><?= $missing === [] ? 'Extensions ready' : 'Missing: ' . $e(implode(', ', $missing)) ?></span>
          <span class="pill <?= $webpOk ? 'ok' : '' ?>"><span class="dot"></span>WebP <?= $webpOk ? 'ready' : 'not available' ?></span>
        </div>
      <?php endif; ?>

      <?php if ($total > 0): ?>
        <div class="progress">
          <div class="row"><strong>Roadmap</strong><span><?= $done ?> of <?= $total ?> done · <?= $pct ?>%</span></div>
          <div class="bar"><i style="width: <?= max($pct, 2) ?>%"></i></div>
          <div class="row"><span><?= $static ? 'Live on its first website, built in the open.' : 'Read from the README roadmap. Run <code>php hammer doctor</code> for the full check.' ?></span></div>
        </div>
      <?php endif; ?>

      <p class="lede small after">
        <a href="<?= $oop ?>">Part one</a> taught <em>how to wire components</em>.
        <a href="<?= $ddd ?>">Part two</a> taught <em>what the components should be</em>.
        Slim Killer puts both to work in a real product that ships to real clients.
      </p>

      <div class="cta">
        <a class="btn primary" href="<?= $repo ?>">View on GitHub</a>
        <a class="btn" href="#how">How it works</a>
        <a class="btn" href="#features">Compared</a>
        <a class="btn" href="#security">Security</a>
      </div>
    </div>
    <div class="brand">
      <?php if ($logo !== ''): ?>
        <div class="logo-badge"><?= $logo ?></div>
      <?php endif; ?>
      <p class="wordmark" aria-label="Slim Killer"><span class="slim">Slim</span><span class="killer">Killer</span></p>
    </div>
  </div>
</header>

<main>

<section class="alt">
  <div class="wrap">
    <div class="figures">
      <div class="fig"><b>~1 s</b><span>to commit a client's save to GitHub from shared hosting</span></div>
      <div class="fig"><b>2–3 s</b><span>to fetch, unpack and switch a real 30 MB, 217-file client site</span></div>
      <div class="fig"><b>0</b><span>plugins, databases or PHP between a visitor and the page</span></div>
      <div class="fig"><b><?= $testCount ?></b><span>behaviours under test<?= $checkCount > 0 ? ', and ' . $checkCount . ' host checks in <code>hammer doctor</code>' : '' ?></span></div>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <h2>The one question</h2>
    <p class="sub">Every design decision in Slim Killer comes back to it.</p>
    <div class="card question">
      <blockquote>“Does this website need a server at all?”</blockquote>
      <div class="branches">
        <div class="branch no">
          <b>For visitors: no</b>
          Every public page is a plain file, built ahead of time by Astro. There is nothing to
          inject into, nothing to patch at midnight, and nothing that slows down under traffic.
        </div>
        <div class="branch yes">
          <b>For editors: a small one</b>
          A PHP control panel at <code>/cp</code> on the client's own domain, behind two-factor
          sign-in. It saves every change as a Git commit, so nothing is ever lost and any mistake
          can be undone.
        </div>
      </div>
    </div>
  </div>
</section>

<section id="how" class="alt">
  <div class="wrap">
    <h2>How it works</h2>
    <p class="sub">Four steps between a tap on a phone and a faster website. The host only ever needs PHP.</p>
    <ol class="flow">
      <li class="card"><h3>Edit anywhere</h3><p>The client opens <code>/cp</code> on any phone or laptop, changes a page or adds photos, and taps Save.</p></li>
      <li class="card"><h3>One commit</h3><p>The control panel writes the page and its photos to the site's GitHub repository in a single commit.</p></li>
      <li class="card"><h3>GitHub builds</h3><p>GitHub Actions builds the Astro site into plain files. No Node.js on the host, ever.</p></li>
      <li class="card"><h3>The host fetches</h3><p>GitHub rings a signed bell; the host fetches the finished build and switches to it in one step (cron checks too). The old one stays, for instant rollback.</p></li>
    </ol>
  </div>
</section>

<section>
  <div class="wrap">
    <h2>Why not just WordPress?</h2>
    <p class="sub">WordPress powers a huge share of the web for good reasons. For a small business site that changes a few times a month, it does a lot of work nobody asked for.</p>
    <div class="scroll">
      <table class="compare">
        <thead><tr><th></th><th>A typical WordPress site</th><th>Slim Killer</th></tr></thead>
        <tbody>
          <tr><td>Each page view</td><td>PHP and a database run, unless a caching plugin steps in</td><td class="us">A plain file is sent. That's it.</td></tr>
          <tr><td>Adding features</td><td>Plugins from many authors, each with its own updates and risks</td><td class="us">Modules written in code, in one repository, behind tests</td></tr>
          <tr><td>What attackers see</td><td>A login page at a well-known address, plus every plugin's endpoints</td><td class="us">Static files, and a small control panel with required two-factor</td></tr>
          <tr><td>Visitor statistics</td><td>Google Analytics or a plugin, and a cookie banner to go with it</td><td class="us">Built in: no cookies, no internet addresses kept, a short email every morning</td></tr>
          <tr><td>Enquiries</td><td>Often stored in the database by a forms plugin, for anyone who gets in</td><td class="us">Emailed, not stored</td></tr>
          <tr><td>Updates</td><td>Core, the theme and every plugin, each on its own schedule</td><td class="us">One tagged release for the panel; the pages have nothing to update</td></tr>
          <tr><td>Undo</td><td>Revisions for text; for anything else, a backup</td><td class="us">Every save is a Git commit; every release is kept</td></tr>
          <tr><td>Hosting</td><td>Any PHP host</td><td class="us">Any PHP host. <a href="<?= $wiki ?>/Host-requirements"><code>hammer doctor</code> checks it</a></td></tr>
        </tbody>
      </table>
    </div>
    <div class="note">
      <strong>Where WordPress still wins:</strong> when the site owner wants to install features
      themselves from a marketplace. Slim Killer is for sites built and looked after by a developer,
      where speed, safety and a calm Tuesday night matter more.
    </div>
  </div>
</section>

<section id="moving" class="alt">
  <div class="wrap">
    <h2>Moving from WordPress without losing search traffic</h2>
    <p class="sub">A fair worry. When a site loses its Google traffic after a move, broken old addresses are usually why. Slim Killer is built so they don't break.</p>
    <div class="card">
      <ul class="reasons">
        <li><strong>Old addresses keep working.</strong> Each old WordPress address is listed once in the site's <code>slim-killer.json</code> and becomes a permanent (301) redirect in <code>.htaccess</code>, in one step to the new page. The web server answers it before PHP starts. On WordPress, a redirect plugin usually runs PHP and a database query on every visit, and a change of plugin or permalinks can leave redirects pointing at other redirects.</li>
        <li><strong>One address for every page.</strong> With or without <code>www</code>, with or without the closing slash: each lands on the one address, in one 301. Search engines never find the same page twice.</li>
        <li><strong>Missing pages say so.</strong> An address that no longer exists answers with a real 404 status and the site's own page. Visits lists every page not found, with where its link was, so an old address you missed shows up within days and gets its redirect.</li>
        <li><strong>What search engines read, on every page.</strong> A sitemap, a <code>robots.txt</code>, a canonical address and a description on every page, share cards, and schema.org data for the business and every price, built from the content. On WordPress that's Yoast or Rank Math, and settings to get right.</li>
        <li><strong>AI search reads it too.</strong> ChatGPT, Perplexity, Claude and Google's AI answers read plain HTML without running JavaScript, and plain HTML is all a Slim Killer page is. <code>robots.txt</code> names them as welcome. An <code>llms.txt</code>, built from the content, comes free; few AI tools read it yet, so we don't count on it for visits.</li>
        <li><strong>Faster pages.</strong> Pre-built pages with no plugins show fast on a phone, and speed is part of how Google ranks.</li>
      </ul>
    </div>
    <div class="note">
      <strong>On the day:</strong> list every address the old site had (its sitemap and
      Search Console have them), add the old ones to <code>slim-killer.json</code>, launch,
      and submit the new sitemap in Search Console.
      <a href="<?= $wiki ?>/Starting-a-client-project">Starting a client project</a> has each step.
    </div>
  </div>
</section>

<section id="woocommerce">
  <div class="wrap">
    <h2>Why we don't build WooCommerce shops</h2>
    <p class="sub">A shop holds money and people's details. Shared hosting was built for neither, and WooCommerce makes both harder to protect.</p>
    <div class="card">
      <ul class="reasons">
        <li><strong>It can't be cached.</strong> Carts and checkouts are built fresh for every shopper, on a plan that rations processing power. The site slows down when people are trying to pay.</li>
        <li><strong>Bad neighbours.</strong> On shared hosting, one hacked site can often read another's settings, including the shop's database password, and with it every customer's details.</li>
        <li><strong>Skimmers and card testing.</strong> A break-in can add a few lines of JavaScript that copy cards as they're typed, and bots use checkouts to try stolen cards in bulk. The shop pays the fees and risks its merchant account.</li>
        <li><strong>Customer data, and the law that comes with it.</strong> Every order stores names, addresses, phone numbers and emails, which data-protection laws (the GDPR in Europe and the UK, the CCPA in California, and their equivalents elsewhere) require you to protect, and to report if they leak.</li>
        <li><strong>Backups that lose orders.</strong> Restoring yesterday's copy of a shop deletes today's orders. The clean rollback that rescues a hacked website is gone.</li>
        <li><strong>Receipts in spam.</strong> Shared hosting sends mail under the host's name, not the shop's, so order emails often never arrive.</li>
      </ul>
    </div>
    <div class="note">
      <strong>What we do instead:</strong> a site that shows products and takes enquiries,
      for dealerships, property and tours, runs on Slim Killer as it is (Catalogue mode).
      When a card is involved, the payment goes to Shopify, which is built to carry exactly
      that risk. When a business needs more than a shop (bookings, accounts, its own
      rules), we build it in Laravel, on hosting made for it. Either way, your website
      stays static and the money never touches it.
    </div>
  </div>
</section>

<section id="features" class="alt">
  <div class="wrap">
    <h2>What comes in the box</h2>
    <p class="sub">First-party only: what you get from the project itself, before any plugin, package or paid service. Slim Killer sits between the two on purpose: WordPress's ease for the client, Laravel's tooling for the developer.</p>
    <div class="scroll">
      <table class="compare three">
        <thead><tr><th></th><th>WordPress</th><th class="us">Slim Killer</th><th>Laravel</th></tr></thead>
        <tbody>
          <tr><td>Command line</td><td>Not in the download. WP-CLI is installed separately</td><td class="us"><code>hammer</code>: doctor, install, users, cron, deploys, rollback, visits, backups</td><td>Artisan</td></tr>
          <tr><td>Middleware</td><td>None. Plugins hook in by priority number</td><td class="us">PSR-15 middleware, in one readable order</td><td>Middleware</td></tr>
          <tr><td>Dependency injection container</td><td>None. Global functions and hooks</td><td class="us">PHP-DI, wired once at the entry point</td><td>The service container</td></tr>
          <tr><td>Automated tests</td><td>None for your site. Core's own suite is a separate setup</td><td class="us">Pest unit and real-browser tests, run before every merge</td><td>PHPUnit or Pest, set up in every new app</td></tr>
          <tr><td>Static analysis and formatting</td><td>None</td><td class="us">PHPStan and Pint, with zero warnings to merge</td><td>Pint. Larastan is a community package</td></tr>
          <tr><td>Instructions for AI coding agents</td><td>Separate agent skills and plugins</td><td class="us">In the repository: <code>AGENTS.md</code>, the rules and the wiki, written for people and agents alike</td><td>Laravel Boost, a separate first-party package</td></tr>
          <tr><td>Database migrations</td><td>None. Each plugin manages its own tables</td><td class="us">Plain SQL migrations, <code>hammer migrate</code></td><td>Migrations</td></tr>
          <tr><td>Form protection</td><td>Depends on each plugin</td><td class="us">CSRF tokens in the panel; origin checks and bot traps on public forms</td><td>CSRF tokens on every form</td></tr>
          <tr><td>Security headers</td><td>None by default</td><td class="us">A strict Content Security Policy on every panel page</td><td>None by default</td></tr>
          <tr><td>Search engines and AI search</td><td>A plugin (Yoast or Rank Math) for the sitemap, descriptions and schema.org</td><td class="us">In the site's layout, built from content: sitemap, <code>robots.txt</code>, canonical addresses, share cards, schema.org for the business and its prices, and <code>llms.txt</code></td><td>Packages (Spatie's laravel-sitemap), and the rest by hand</td></tr>
          <tr><td>Old addresses after a move</td><td>A redirect plugin, run in PHP on every visit</td><td class="us">Listed once; single 301s the web server sends before PHP starts, and pages not found counted in Visits</td><td>Routes or middleware, in PHP</td></tr>
          <tr><td>Photos</td><td>JPEG in several sizes; WebP or AVIF need a plugin</td><td class="us">AVIF first, WebP next, JPEG as the last fallback, sized for each screen; location data removed</td><td>None</td></tr>
          <tr><td>Two-factor sign-in</td><td>A plugin</td><td class="us">Required for everyone, with recovery codes and breached passwords refused</td><td>In the starter kits</td></tr>
          <tr><td>Password storage</td><td>bcrypt, since 6.8 (phpass before)</td><td class="us">Argon2id<a href="#argon" class="star" aria-label="See the note on Argon2id">*</a>, the first choice of OWASP's guidance</td><td>bcrypt by default; Argon2id is one setting</td></tr>
          <tr><td>Checking a host before you install</td><td>Site Health, once it's installed</td><td class="us"><code>hammer doctor</code>: PHP for web and command line, cron, rewrites, outgoing connections</td><td>None</td></tr>
          <tr><td>Visitor statistics</td><td>None. A plugin or Google Analytics</td><td class="us">Cookieless visit counter, Visits page and a morning email</td><td>None</td></tr>
          <tr><td>Deploys and rollback</td><td>None</td><td class="us">Fetch a finished build, switch in one step, roll back with one command</td><td>Paid services (Forge, Laravel Cloud)</td></tr>
          <tr><td>Backups</td><td>None in core. A plugin, often paid</td><td class="us">The database, daily: checked, encrypted (AES-256) and kept on the client's own Google Drive, with a test restore</td><td>None built in. A package (Spatie's laravel-backup)</td></tr>
          <tr><td>If the database is lost</td><td>The site: every page and setting is in it</td><td class="us">No pages or photos: they're in GitHub. Accounts are made again; the visit history starts over</td><td>Usually everything the app stores</td></tr>
          <tr><td>If the host is lost</td><td>The site lives on the host: restore a backup, if there is one</td><td class="us">The host is a copy. Code and pages are in GitHub, the database on Google Drive: a new host is rebuilt from both</td><td>Code in Git; the database and uploads need their own backups</td></tr>
          <tr><td>A client editing their own pages</td><td>Yes: the block editor, WordPress's real strength</td><td class="us">A phone-friendly panel, built from the site's own schema</td><td>Nova, a paid add-on</td></tr>
          <tr><td>Plugin marketplace</td><td>Yes, tens of thousands</td><td class="us">No, on purpose</td><td>Composer packages</td></tr>
        </tbody>
      </table>
    </div>
    <div class="note">
      <strong>The point isn't the length of the list.</strong> It's that a developer can hold
      the whole of Slim Killer in their head after an afternoon: the site's schema defines
      the pages, the panel builds its forms from it, every save is a commit, GitHub builds,
      and the host fetches. That isn't true of WordPress.
    </div>
  </div>
</section>

<section id="security">
  <div class="wrap">
    <h2>Security, by having less</h2>
    <p class="sub">Most of a site's security comes from what isn't there.</p>
    <div class="card security">
      <p><strong>No plugins to break, no hooks to exploit, no theme to go stale.</strong> Your pages are built ahead of time as plain HTML, with only the CSS and the little JavaScript each page uses, so there's no PHP and no database behind them for anyone to attack, and no caching layer hiding a slow site. Even your visitor statistics are counted without cookies or internet addresses.</p>
      <p>The only door in is a small control panel, guarded by required two-factor sign-in, passwords stored with Argon2id<a href="#argon" class="star" aria-label="See the note on Argon2id">*</a> and checked against known data breaches, and a tiny SQLite database kept out of the website's reach. Every change is saved as a version you can roll back. And enquiries aren't stored at all: they go straight to your inbox, and on the rare busy moment they're held briefly outside the website and deleted as soon as they're sent.</p>
    </div>
    <p class="footnote" id="argon">* Argon2id when the host's PHP has it, as most PHP 8.4 hosts do (<code>php -r "print_r(password_algos());"</code> lists <code>argon2id</code>); bcrypt otherwise.</p>
  </div>
</section>

<section class="alt">
  <div class="wrap">
    <h2>Eighteen rules, in three parts</h2>
    <p class="sub">Slim Killer is built by the rules of the two courses before it, and six of its own. Rule 7 is the gate: it decides where rules 8 to 12 are worth their weight.</p>
    <div class="rules-grid">
      <div class="card">
        <h3>Part one: wiring</h3>
        <p class="from">From <a href="<?= $oop ?>">PHP 8.5 OOP Mastery</a>. Always applies.</p>
        <ol class="rules">
          <?php foreach ($wiring as $i => $rule): ?>
            <li><span><?= $i + 1 ?></span><?= $e($rule) ?></li>
          <?php endforeach; ?>
        </ol>
      </div>
      <div class="card">
        <h3>Part two: modelling</h3>
        <p class="from">From <a href="<?= $ddd ?>">Advanced Domain Architecture</a>. Only where it pays.</p>
        <ol class="rules">
          <?php foreach ($modelling as $i => $rule): ?>
            <li><span><?= $i + 7 ?></span><?= $e($rule) ?></li>
          <?php endforeach; ?>
        </ol>
      </div>
      <div class="card wide">
        <h3>Part three: the product</h3>
        <p class="from">Slim Killer's own, from building it and from cleaning up a hacked server. <a href="<?= $wiki ?>/Slim-Killer-rules">Each one explained</a>.</p>
        <ol class="rules">
          <?php foreach ($product as $i => $rule): ?>
            <li><span><?= $i + 13 ?></span><?= $e($rule) ?></li>
          <?php endforeach; ?>
        </ol>
      </div>
    </div>
    <div class="note">
      Content editing stays simple: schema in, file out. Publishing and sign-in get the full
      treatment, because that's where a mistake loses a client's work or lets hackers and
      crooks in. The reasoning
      is in <a href="<?= $repo ?>/blob/main/BUILD_PHILOSOPHY.md"><code>BUILD_PHILOSOPHY.md</code></a>.
    </div>
  </div>
</section>

<section id="proof">
  <div class="wrap">
    <h2>What is proved, rather than promised</h2>
    <p class="sub">Before a line of product code, two spikes ran on a real, entry-level shared hosting plan. These are their own words.</p>
    <div class="card proof">
      <strong>Spike S1: shared hosting can save to GitHub, and refuses to overwrite</strong>
<pre>blob: markdown         HTTP 201 in 299 ms
blob: image            HTTP 201 in 289 ms
commit                 HTTP 201 in 353 ms
move branch            HTTP 200 in 622 ms     <i># one save, one commit, one build</i>
move branch (stale)    HTTP 422, GitHub says: Update is not a fast forward
<b>S1 PASSES on this host (litespeed) in 8.0 s.</b></pre>
    </div>
    <div class="card proof" style="margin-top:1.4rem">
      <strong>Spike S2: fetch a real client build, switch to it, roll back</strong>
<pre>zip size                 30.3 MB
files                    217
time                     142 ms                 <i># to unpack</i>
GET /about/              HTTP 200
GET /_site/ directly     HTTP 403                <i># old builds stay private</i>
<b>S2 PASSES (cli, PHP 8.4.27) in 2.7 s, peak memory 12.0 MB.</b></pre>
    </div>
    <div class="note">
      What the spikes found became <code>hammer doctor</code>: it checks the command line's PHP
      <em>and</em> the website's (on shared hosting they are often different versions), finds the
      right PHP binary for cron, and proves rewrites work by asking the web server itself.
    </div>
  </div>
</section>

<?php if ($roadmap !== []): ?>
<section class="alt">
  <div class="wrap">
    <h2>The roadmap</h2>
    <p class="sub">Read straight from the README, so it is never out of date. Built in the open.</p>
    <ol class="spine">
      <?php foreach ($roadmap as $item): ?>
        <li class="<?= $item['done'] ? 'done' : '' ?>"><?= $markup($item['text']) ?></li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
<?php endif; ?>

<section id="start">
  <div class="wrap">
    <h2>Getting started</h2>
    <p class="sub">You need PHP <?= $e($minPhp) ?>, Composer and SSH on the host. The <a href="<?= $wiki ?>/Installing-on-a-new-host">installation guide</a> has every step, with the exact commands for shared hosting.</p>
    <ol class="steps">
      <li><h3>Clone a release</h3><p>Outside the web root. A website always runs a tagged version, never <code>main</code>.</p><pre>git clone <?= $repo ?>.git
cd slim-killer
git checkout <?= $e($version !== '' ? $version : 'main') ?></pre></li>
      <li><h3>Install the dependencies</h3><pre>composer install --no-dev</pre></li>
      <li><h3>Ask the doctor</h3><p>Every line is PASS, WARN, FAIL or INFO, with a hint for anything to fix.</p><pre>php hammer doctor --url=https://example.com --webroot=~/domains/example.com/public_html</pre></li>
      <li><h3>Install, and add one cron line</h3><p>It prints a welcome link: open it, choose a password and set up two-factor. Cron does the rest every minute.</p><pre>php hammer install --url=https://example.com --email=you@example.com --name="Your Name"
* * * * * php ~/slim-killer/hammer cron:run</pre></li>
    </ol>
  </div>
</section>

<section class="blue">
  <div class="wrap">
    <h2>For WordPress and Laravel developers</h2>
    <p class="audience">
      Frameworks are brilliant at hiding the hard parts. That's their job. These three projects
      are about the hard parts themselves: wiring a container by hand, testing behaviour instead
      of layout, modelling rules that cannot be broken, and knowing when all of that is overkill.
      The things most framework tutorials leave out, taught in plain PHP and then put to work.
    </p>
    <div class="series">
      <div class="card">
        <span class="part">Part one · course</span>
        <h3>PHP 8.5 OOP Mastery</h3>
        <p>SOLID, dependency injection, reflection, containers, TDD and object lifecycle. Thirty lessons, CLI-first, free.</p>
        <a class="btn" href="<?= $oop ?>">Start here</a>
      </div>
      <div class="card">
        <span class="part">Part two · course</span>
        <h3>Advanced Domain Architecture</h3>
        <p>Aggregates, invariants, repositories and typed failures, and first, how to tell when none of it is worth the ceremony.</p>
        <a class="btn" href="<?= $ddd ?>">Then this</a>
      </div>
      <div class="card here">
        <span class="part">Part three · product</span>
        <h3>Slim Killer</h3>
        <p>Both courses, put to work in software that real clients rely on. Read the code to see every rule applied.</p>
        <a class="btn primary" href="<?= $repo ?>">Read the code</a>
      </div>
    </div>
  </div>
</section>

</main>

<footer>
  <div class="wrap">
    <a class="footer-logo" href="https://www.monatemedia.com/"><img src="logo-wide.png" alt="Monate Media"></a>
    Built by Edward Baitsewe · Monate Media · Cape Town, South Africa<br>
    <a href="https://www.monatemedia.com/">monatemedia.com</a> ·
    <a href="https://github.com/monatemedia">GitHub</a> ·
    <a href="https://www.linkedin.com/company/monatemediaofficial/">LinkedIn</a> ·
    <a href="mailto:edward@monatemedia.com">edward@monatemedia.com</a><br>
    MIT licensed. Logo: CC0, from SVG Repo.
    <div class="joke">No plugins were harmed in the making of this site.</div>
  </div>
</footer>

<script>
// A copy button (an icon) on each command in "Getting started". The clipboard API needs
// a secure page; opened from a file, or on older Safari, the hidden-textarea way is used.
// If the browser won't copy, the commands are selected instead, ready for Ctrl+C.
(function () {
  var COPY_ICON = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="9" y="9" width="11" height="11" rx="2"></rect><path d="M5 15V6a2 2 0 0 1 2-2h8"></path></svg>';
  var DONE_ICON = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"></path></svg>';
  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      var area = document.createElement('textarea');
      area.value = text;
      area.setAttribute('readonly', '');
      area.className = 'copy-area';
      document.body.appendChild(area);
      area.select();
      area.setSelectionRange(0, text.length);
      var copied = false;
      try { copied = document.execCommand('copy'); } catch (e) { copied = false; }
      document.body.removeChild(area);
      if (copied) { resolve(); } else { reject(new Error('copy refused')); }
    });
  }
  function selectText(element) {
    var range = document.createRange();
    range.selectNodeContents(element);
    var selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
  }
  document.querySelectorAll('ol.steps pre').forEach(function (pre) {
    var box = document.createElement('div');
    box.className = 'cmd';
    pre.parentNode.insertBefore(box, pre);
    box.appendChild(pre);
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'copy-btn';
    button.innerHTML = COPY_ICON;
    button.setAttribute('aria-label', 'Copy the commands');
    button.title = 'Copy';
    box.appendChild(button);
    var reset;
    button.addEventListener('click', function () {
      clearTimeout(reset);
      copyText(pre.textContent.trim()).then(function () {
        button.innerHTML = DONE_ICON;
        button.classList.add('copied');
        button.setAttribute('aria-label', 'Copied');
        button.title = 'Copied';
      }, function () {
        selectText(pre);
        button.setAttribute('aria-label', 'Selected: press Ctrl+C to copy');
        button.title = 'Selected: press Ctrl+C to copy';
      });
      reset = setTimeout(function () {
        button.innerHTML = COPY_ICON;
        button.classList.remove('copied');
        button.setAttribute('aria-label', 'Copy the commands');
        button.title = 'Copy';
      }, 2000);
    });
  });
})();
</script>
</body>
</html>
