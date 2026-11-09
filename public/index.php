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
 * Self-contained: no Composer autoload, no CDN, no build step, no images to upload.
 * It must render before `composer install` has ever run. Numbers that can change are
 * read from disk (the README roadmap, the test files), never written down here. The
 * numbers that cannot change are measurements from spikes S1 and S2, and say so.
 *
 * On a client's host the domain root serves the client's Astro site, never this page.
 */

$root = dirname(__DIR__);
$static = PHP_SAPI === 'cli' && in_array('--static', $argv ?? [], true);
$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

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
$missing = array_values(array_filter($requiredExtensions, static fn (string $x): bool => ! extension_loaded($x)));
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
$done = count(array_filter($roadmap, static fn (array $r): bool => $r['done']));
$total = count($roadmap);
$pct = $total > 0 ? (int) round($done / $total * 100) : 0;

/** Roadmap text may contain `code`; escape everything, then restore code spans. */
$markup = static fn (string $s): string => (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', $e($s));

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Slim Killer: a WordPress killer with personality · Monate Media</title>
<meta name="description" content="Fast static Astro websites that clients edit from their phone, on plain shared hosting. No plugins to patch, no database behind the public pages. Part three of the Monate Media PHP series. MIT licensed.">
<?php if ($static): ?>
<link rel="canonical" href="<?= $site ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Monate Media">
<meta property="og:title" content="Slim Killer: a WordPress killer with personality">
<meta property="og:description" content="Static Astro sites, edited from a phone, on plain shared hosting. Part three of the Monate Media PHP series, after OOP Mastery and Domain Architecture.">
<meta property="og:image" content="https://www.monatemedia.com/wp-content/uploads/2024/06/Monate-Media-Logo-Wide-New-Age-6.png">
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
  .topbar .brand { color: #fff; font-weight: 800; text-decoration: none; letter-spacing: -0.01em; }
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
  .logo-badge { justify-self: center; width: 240px; height: 240px; border-radius: 50%; background: var(--skin); border: 4px solid var(--ink);
    box-shadow: 8px 8px 0 var(--ink); display: grid; place-items: center; transform: rotate(-6deg); }
  .logo-badge svg { width: 170px; height: 170px; }
  @media (max-width: 819px) { .logo-badge { width: 160px; height: 160px; } .logo-badge svg { width: 112px; height: 112px; } }

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
  ol.spine::before { content: ""; position: absolute; left: 15px; top: 10px; bottom: 10px; width: 4px; background: var(--ink); border-radius: 2px; }
  ol.spine li { position: relative; padding: 0.15rem 0 1.25rem 3.2rem; font-weight: 600; }
  ol.spine li::before { content: ""; position: absolute; left: 3px; top: 0.25rem; width: 28px; height: 28px; border-radius: 50%; background: var(--panel); border: 4px solid var(--ink); }
  ol.spine li.done::before { background: var(--orange); }
  ol.spine li.done { color: var(--muted); }
  ol.spine li.done::after { content: "✓"; position: absolute; left: 11px; top: 0.12rem; color: #fff; font-weight: 900; font-size: 0.85rem; }

  /* Steps */
  ol.steps { list-style: none; margin: 0; padding: 0; counter-reset: s; display: grid; gap: 1.2rem; }
  ol.steps li { counter-increment: s; position: relative; padding-left: 3rem; min-width: 0; }
  ol.steps li::before { content: counter(s); position: absolute; left: 0; top: 0; width: 2rem; height: 2rem; border-radius: 50%; background: var(--ink); color: #fff;
    display: grid; place-items: center; font-weight: 900; font-size: 0.9rem; }
  ol.steps h3 { font-size: 1.1rem; margin: 0.2rem 0 0.35rem; letter-spacing: -0.02em; }
  ol.steps p { margin: 0 0 0.5rem; color: var(--muted); }
  ol.steps pre { margin: 0; background: var(--ink); color: #f5efe8; border-radius: 8px; padding: 0.6rem 0.9rem; font-family: var(--mono); font-size: 0.84rem; overflow-x: auto; }

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
  footer .joke { margin-top: 0.8rem; font-weight: 700; color: var(--ink); }
  @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } .btn { transition: none; } }
</style>
</head>
<body>

<div class="topbar">
  <div class="wrap">
    <?php if ($static): ?>
      <a href="https://www.monatemedia.com/"><img src="logo-wide.png" alt="Monate Media"></a>
    <?php else: ?>
      <a class="brand" href="https://www.monatemedia.com/">Monate Media</a>
    <?php endif; ?>
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
      <span class="eyebrow">Part three · Open source · PHP <?= $e($minPhp) ?> · MIT</span>
      <h1>A WordPress killer, <span class="pop">with personality.</span></h1>
      <p class="lede">
        Fast static websites that clients edit from their phone, on plain shared hosting. No
        plugins to patch, no database behind the public pages, no Node.js on the server, and
        every change saved as a Git commit you can undo.
      </p>
      <p class="lede small">
        <a href="<?= $oop ?>">Part one</a> taught <em>how to wire components</em>.
        <a href="<?= $ddd ?>">Part two</a> taught <em>what the components should be</em>.
        Slim Killer puts both to work in a real product that ships to real clients.
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
          <div class="row"><span><?= $static ? 'Early days, built in the open.' : 'Read from the README roadmap. Run <code>php hammer doctor</code> for the full check.' ?></span></div>
        </div>
      <?php endif; ?>

      <div class="cta">
        <a class="btn primary" href="<?= $repo ?>">View on GitHub</a>
        <a class="btn" href="#how">How it works</a>
        <a class="btn" href="#proof">What is proved</a>
      </div>
    </div>
    <?php if ($logo !== ''): ?>
      <div class="logo-badge"><?= $logo ?></div>
    <?php endif; ?>
  </div>
</header>

<main>

<section class="alt">
  <div class="wrap">
    <div class="figures">
      <div class="fig"><b>~1 s</b><span>to commit a client's save to GitHub from shared hosting</span></div>
      <div class="fig"><b>2–3 s</b><span>to fetch, unpack and switch a real 30 MB, 217-file client site</span></div>
      <div class="fig"><b>0</b><span>plugins, databases or PHP between a visitor and the page</span></div>
      <div class="fig"><b><?= $testCount ?></b><span>automated tests<?= $checkCount > 0 ? ', and ' . $checkCount . ' host checks in <code>hammer doctor</code>' : '' ?></span></div>
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
          A PHP control panel at <code>/cp</code> on the client's own domain. It saves every change
          as a Git commit, so nothing is ever lost and any mistake can be undone.
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
      <li class="card"><h3>The host fetches</h3><p>Cron collects the finished build and switches the site to it in one step. The old one stays, for instant rollback.</p></li>
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
          <tr><td>What attackers see</td><td>A login page at a well-known address, plus every plugin's endpoints</td><td class="us">Static files, and a small control panel (two-factor login planned)</td></tr>
          <tr><td>Undo</td><td>Restore a backup, if there is one</td><td class="us">Every save is a Git commit; every release is kept</td></tr>
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

<section class="alt">
  <div class="wrap">
    <h2>Twelve rules, in two parts</h2>
    <p class="sub">Slim Killer is built by the rules of the two courses before it. Rule 7 is the gate: it decides where rules 8 to 12 are worth their weight.</p>
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
    </div>
    <div class="note">
      Content editing stays simple: schema in, file out. The deploy pipeline and the shop get the
      full treatment, because that's where a mistake loses a client's work or money. The reasoning
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
    <p class="sub">Read straight from the README, so it is never out of date. Early days, built in the open.</p>
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
    <p class="sub">You need PHP <?= $e($minPhp) ?> and Composer. Today Slim Killer can check a host; the control panel is next.</p>
    <ol class="steps">
      <li><h3>Clone it</h3><pre>git clone <?= $repo ?>.git
cd slim-killer</pre></li>
      <li><h3>Install the dependencies</h3><pre>composer install</pre></li>
      <li><h3>Ask the doctor</h3><p>Every line is PASS, WARN, FAIL or INFO, with a hint for anything to fix.</p><pre>php hammer doctor</pre></li>
      <li><h3>Check a real host</h3><p>On shared hosting, include the website too. The <a href="<?= $wiki ?>/Installing-on-a-new-host">installation guide</a> walks through it.</p><pre>php hammer doctor --url=https://example.com --webroot=~/domains/example.com/public_html</pre></li>
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
    Built by Edward Baitsewe · Monate Media · Cape Town, South Africa<br>
    <a href="https://www.monatemedia.com/">monatemedia.com</a> ·
    <a href="https://github.com/monatemedia">GitHub</a> ·
    <a href="https://www.linkedin.com/company/monatemediaofficial/">LinkedIn</a> ·
    <a href="mailto:edward@monatemedia.com">edward@monatemedia.com</a><br>
    MIT licensed. Logo: CC0, from SVG Repo.
    <div class="joke">No plugins were harmed in the making of this site.</div>
  </div>
</footer>

</body>
</html>
