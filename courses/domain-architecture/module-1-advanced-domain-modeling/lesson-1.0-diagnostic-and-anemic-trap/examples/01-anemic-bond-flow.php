<?php

declare(strict_types=1);

/**
 * Example 01 — The Anemic Bond Flow (a faithful miniature of today's code)
 * -------------------------------------------------------------------------
 * This is a self-contained reproduction of how the Bond Application ACTUALLY
 * works in Slim Killer right now. Compare it to the real files:
 *
 *   src/Http/Application/ProcessApplyController.php   ($request->getParsedBody() -> array)
 *   src/Domain/Application/SubmitApplicationAction.php (execute(array $data): bool)
 *   src/Infrastructure/Persistence/Application/ApplicationRepository.php (create(array $data))
 *
 * Run it and notice three diseases, all of which the runtime happily tolerates:
 *   A. The "domain" only speaks arrays and booleans — it has no business vocabulary.
 *   B. Invalid data (negative bond, bad email) flows straight through to "saved".
 *   C. On failure it returns `false` — the REASON for rejection is thrown away.
 *
 *   php examples/01-anemic-bond-flow.php
 */

echo "=== Example 01 — The Anemic Bond Flow ===\n\n";

/**
 * An "anemic" repository: a thin wrapper over a data array. It has no opinion about
 * what a valid application is — it just shovels keys into storage. (Mirrors
 * ApplicationRepository::create(), which casts `(float) $data['bond_amount']`.)
 */
final class AnemicApplicationRepository
{
    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    public function create(array $data): int
    {
        $this->rows[] = [
            'first_name'  => $data['first_name'],
            'last_name'   => $data['last_name'],
            'email'       => $data['email'],
            'bond_amount' => (float) $data['bond_amount'], // ← money as a float
        ];

        return count($this->rows);
    }
}

/**
 * An "anemic" action: takes an array, returns a bool. All the business meaning
 * lives in the developer's head, not in the types. (Mirrors SubmitApplicationAction.)
 */
final class AnemicSubmitApplicationAction
{
    public function __construct(
        private AnemicApplicationRepository $repository,
    ) {}

    public function execute(array $data): bool
    {
        // There is nowhere natural to put a rule like "bond must be positive",
        // so in practice it is simply... not enforced. The array is the model.
        $this->repository->create($data);

        return true; // success/failure collapsed to a single bit
    }
}

$action = new AnemicSubmitApplicationAction(new AnemicApplicationRepository());

// --- A "valid" submission ---
$ok = $action->execute([
    'first_name'  => 'Thabo',
    'last_name'   => 'Mokoena',
    'email'       => 'thabo@example.co.za',
    'bond_amount' => 1_250_000.00,
]);
echo "A. Normal application saved?            " . ($ok ? 'true' : 'false') . "\n";

// --- A nonsense submission the system CANNOT reject ---
$ok = $action->execute([
    'first_name'  => '',                 // empty applicant name
    'last_name'   => 'Mokoena',
    'email'       => 'definitely-not-an-email',
    'bond_amount' => -5_000_000.00,      // a NEGATIVE bond
]);
echo "B. Garbage application ALSO saved?      " . ($ok ? 'true (!!)' : 'false') . "\n";

// --- The failure case: all we ever learn is "false" ---
echo "C. If it had failed, the caller gets:   only `false` — no reason, no rule name.\n";

echo "\nNow ask the Diagnostic Question of every class above:\n";
echo "  \"Delete Slim, Pixie and Apache — does this still make sense to a loan officer?\"\n";
echo "  An array of strings and a bool say NOTHING about bonds. There is no domain here\n";
echo "  yet — only infrastructure wearing a domain's name. Module 1 builds the real thing.\n";
