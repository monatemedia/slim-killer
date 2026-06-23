<?php

declare(strict_types=1);

/**
 * Self-checking verifier for the persistence-boundary challenge.
 * -------------------------------------------------------------------------
 *   php challenge/verify.php              → checks YOUR challenge/SafeBondApplicationStore.php
 *   php challenge/verify.php --solution   → checks the reference solution (always green)
 */

require __DIR__ . '/../autoload.php';

// Load the implementation under test BEFORE its class name is referenced, so the
// autoloader never pulls the src/ copy (same FQCN).
$useSolution = in_array('--solution', $argv, true);
require $useSolution
    ? __DIR__ . '/solution/SafeBondApplicationStore.php'
    : __DIR__ . '/SafeBondApplicationStore.php';

use Bond\Domain\Exception\DomainException;
use Bond\Infrastructure\Exception\PersistenceException;
use Bond\Infrastructure\Persistence\SafeBondApplicationStore;

$pass = 0;
$fail = 0;

function check(string $label, callable $test): void
{
    global $pass, $fail;
    try {
        $ok = $test();
    } catch (\Throwable $e) {
        $ok = false;
        $label .= "  [threw: " . $e::class . ": " . $e->getMessage() . "]";
    }
    echo $ok ? "  \e[32m[PASS]\e[0m {$label}\n" : "  \e[31m[FAIL]\e[0m {$label}\n";
    $ok ? $pass++ : $fail++;
}

/** A PDO whose `applications` table exists. */
function pdoWithTable(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec('CREATE TABLE applications (id TEXT PRIMARY KEY)');
    return $pdo;
}

/** A PDO with no schema at all. */
function pdoWithoutTable(): PDO
{
    return new PDO('sqlite::memory:');
}

$id = '11111111-1111-4111-8111-111111111111';

echo "Verifying SafeBondApplicationStore (" . ($useSolution ? 'solution' : 'your implementation') . ")\n\n";

check('save() succeeds when the table exists', function () use ($id) {
    (new SafeBondApplicationStore(pdoWithTable()))->save($id);
    return true; // no exception
});

check('save() throws PersistenceException when the DB fails', function () use ($id) {
    try {
        (new SafeBondApplicationStore(pdoWithoutTable()))->save($id);
        return false;
    } catch (PersistenceException) {
        return true;
    }
});

check('a raw PDOException does NOT escape save()', function () use ($id) {
    try {
        (new SafeBondApplicationStore(pdoWithoutTable()))->save($id);
        return false;
    } catch (PDOException) {
        return false; // leaked!
    } catch (PersistenceException) {
        return true;  // correctly translated
    }
});

check('the original PDOException is preserved as $previous', function () use ($id) {
    try {
        (new SafeBondApplicationStore(pdoWithoutTable()))->save($id);
        return false;
    } catch (PersistenceException $e) {
        return $e->getPrevious() instanceof PDOException;
    }
});

check('PersistenceException is NOT a DomainException (routes to 500, not 422)', function () {
    return ! (new PersistenceException('x') instanceof DomainException);
});

echo "\n" . str_repeat('-', 56) . "\n";
echo $fail === 0
    ? "\e[32mALL {$pass} CHECKS PASSED ✅\e[0m\n"
    : "\e[33m{$pass} passed, \e[31m{$fail} failed\e[0m — keep going.\n";

exit($fail === 0 ? 0 : 1);
