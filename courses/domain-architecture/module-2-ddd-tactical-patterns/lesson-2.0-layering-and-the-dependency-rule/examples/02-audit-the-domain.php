<?php

declare(strict_types=1);

/**
 * Example 02 — Audit the live domain against the Dependency Rule
 * -------------------------------------------------------------------------
 * A real, runnable tool. It scans the ACTUAL files under the repo's src/Domain/
 * and reports two classes of violation:
 *
 *   1. FRAMEWORK LEAK     — a "domain" file importing Pixie / Slim / Twig / PDO / PSR-HTTP,
 *                           or depending on a concrete Infrastructure class instead of an interface.
 *   2. PSR-4 MISMATCH     — the declared namespace does not match the file's path under src/,
 *                           so the class does not even autoload (the half-finished DDD migration).
 *
 *   php examples/02-audit-the-domain.php
 *
 * Output reflects the repo's CURRENT state — it changes as you fix the domain in Module 2.
 */

$root      = dirname(__DIR__, 5);              // .../slim-killer
$srcDir    = $root . '/src';
$domainDir = $srcDir . '/Domain';

$forbiddenVendors = ['Pixie\\', 'Slim\\', 'Twig\\', 'Psr\\Http\\', 'PDO', 'Doctrine\\'];

echo "=== Example 02 — Domain Dependency Audit ===\n";
echo "Scanning: " . str_replace('\\', '/', $domainDir) . "\n\n";

if (!is_dir($domainDir)) {
    echo "No src/Domain directory found — nothing to audit.\n";
    exit(0);
}

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($domainDir));
$violations = 0;
$clean = 0;

foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $path     = str_replace('\\', '/', $file->getPathname());
    $relative = ltrim(substr($path, strlen(str_replace('\\', '/', $srcDir))), '/'); // e.g. Domain/Application/Foo.php
    $contents = file_get_contents($path) ?: '';

    // Declared namespace + imports.
    preg_match('/^namespace\s+([^;]+);/m', $contents, $nsMatch);
    $declaredNs = trim($nsMatch[1] ?? '(none)');

    preg_match_all('/^use\s+([^;]+);/m', $contents, $useMatch);
    $imports = array_map('trim', $useMatch[1] ?? []);

    // Expected PSR-4 namespace from the path: src/ -> App\, directory separators -> backslashes.
    $expectedNs = 'App\\' . str_replace('/', '\\', dirname($relative));

    $findings = [];

    // 1. PSR-4 mismatch?
    if ($declaredNs !== $expectedNs) {
        $findings[] = "PSR-4 MISMATCH  declared '{$declaredNs}', expected '{$expectedNs}' (will not autoload)";
    }

    // 2. Framework / infrastructure leaks in the imports?
    foreach ($imports as $import) {
        foreach ($forbiddenVendors as $vendor) {
            if (str_starts_with($import, $vendor)) {
                $findings[] = "FRAMEWORK LEAK  imports '{$import}'";
            }
        }
        if (str_starts_with($import, 'App\\Infrastructure\\')
            || str_starts_with($import, 'App\\Repositories\\')) {
            $findings[] = "CONCRETE DEPENDENCY  imports '{$import}' (depend on a domain interface instead)";
        }
    }

    if ($findings === []) {
        $clean++;
        echo "  \e[32m[CLEAN]\e[0m {$relative}\n";
    } else {
        $violations++;
        echo "  \e[31m[VIOLATION]\e[0m {$relative}\n";
        foreach ($findings as $f) {
            echo "      - {$f}\n";
        }
    }
}

echo "\n" . str_repeat('-', 60) . "\n";
echo $violations === 0
    ? "\e[32mDomain is clean — every file obeys the Dependency Rule.\e[0m\n"
    : "\e[33m{$clean} clean, \e[31m{$violations} file(s) violating the Dependency Rule.\e[0m\n";
echo "Module 2 fixes these by moving the Bond domain into App\\Domain\\Bond\\ and\n";
echo "depending on interfaces, not concrete persistence.\n";
