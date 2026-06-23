<?php

declare(strict_types=1);

/**
 * Example 01 — What a raw exception leaks at the edge
 * -------------------------------------------------------------------------
 * A REAL database failure (a genuine PDOException), rendered the way Slim Killer
 * renders it today. In public/index.php the app does:
 *
 *     $app->addErrorMiddleware((getenv('APP_DEBUG') === 'true'), true, true);
 *
 * and .env ships APP_DEBUG=true — so an uncaught exception in the /apply flow prints
 * its full type, message, file, line and stack trace straight to the browser.
 *
 *   php examples/01-the-leak.php
 */

echo "=== Example 01 — The Leak ===\n\n";

// A real persistence failure: insert into a table that was never migrated.
function persistBondApplication(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "INSERT INTO applications (first_name, last_name, email, bond_amount)
            VALUES (:first, :last, :email, :amount)";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':first'  => 'Thabo',
            ':last'   => 'Mokoena',
            ':email'  => 'thabo@example.co.za',
            ':amount' => 1250000.00,
        ]);
    } catch (PDOException $e) {
        // A VERY common anti-pattern: "helpfully" embed the failing SQL in the message.
        // Now the query — table names, columns — travels inside the exception.
        throw new RuntimeException("Database write failed running [{$sql}]: " . $e->getMessage(), previous: $e);
    }
}

/** Mimics Slim's error handler with displayErrorDetails = true (APP_DEBUG=true). */
function renderLikeSlimDebug(\Throwable $e): void
{
    echo "HTTP/1.1 500 Internal Server Error      <-- what the browser receives\n";
    echo "Content-Type: text/html\n\n";
    echo "Type:    " . $e::class . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File:    " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n\n";
}

try {
    persistBondApplication();
} catch (\Throwable $e) {
    renderLikeSlimDebug($e);

    // --- Inventory: what just leaked to an anonymous visitor? ---
    echo "What an attacker just learned from that response:\n";
    echo "  • Absolute server path:  " . $e->getFile() . "\n";
    echo "    → reveals the OS, the web root, and the username in the path.\n";
    echo "  • The database schema:   table 'applications' with columns first_name, last_name, email, bond_amount\n";
    echo "  • The storage engine:    " . (str_contains($e->getMessage(), 'SQLSTATE') ? 'a SQL database (SQLSTATE codes)' : 'unknown') . "\n";
    echo "  • Internal call stack:   class + method names from the trace above\n\n";

    echo "This is an information-disclosure vulnerability. The domain RAISED a failure;\n";
    echo "letting that raw failure reach the EDGE is what leaked the secrets.\n\n";

    echo "What the visitor SHOULD have seen instead:\n";
    echo "  HTTP/1.1 500 Internal Server Error\n";
    echo "  { \"title\": \"Something went wrong\", \"reference\": \"err_9f2a1c\" }\n";
    echo "  (full detail logged server-side under that reference — never shown to the client).\n";
}
