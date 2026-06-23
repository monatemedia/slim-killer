<?php

declare(strict_types=1);

/**
 * ⚠️  PHP 8.4 / 8.5 PREVIEW — DO NOT RUN ON PHP 8.3 OR BELOW
 * -------------------------------------------------------------------------
 * Uses syntax that PARSE-ERRORS on older runtimes:
 *   - asymmetric visibility  `public private(set)`   (PHP 8.4)
 *   - property hooks          `set { ... }`           (PHP 8.4)
 *
 * This is the TARGET form of the rich BondApplication. The runnable src/BondApplication.php
 * achieves the same protection with a private property + a status() getter so the lesson
 * runs on PHP 8.3 today. On Herd 8.5:  herd use 8.5 && php php85-preview/bond-application-with-hooks.php
 */

namespace Bond\Model\Preview;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
}

final class BondApplication
{
    // PHP 8.4: readable as a normal property everywhere, writable ONLY inside this class.
    // No getter needed, and no external code can ever assign it.
    public private(set) ApplicationStatus $status = ApplicationStatus::Draft;

    // PHP 8.4 property hook: validation runs at the point of assignment.
    public private(set) string $applicantEmail {
        set (string $value) {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException("Invalid applicant email: {$value}");
            }
            $this->applicantEmail = $value;
        }
    }

    public function __construct(string $applicantEmail)
    {
        $this->applicantEmail = $applicantEmail; // runs the hook above
    }

    public function submit(): void
    {
        if ($this->status !== ApplicationStatus::Draft) {
            throw new \DomainException("Only a draft can be submitted; status is {$this->status->value}.");
        }
        $this->status = ApplicationStatus::Submitted;
    }

    public function approve(): void
    {
        if ($this->status !== ApplicationStatus::Submitted) {
            throw new \DomainException("Only a submitted application can be approved; status is {$this->status->value}.");
        }
        $this->status = ApplicationStatus::Approved;
    }
}

$app = new BondApplication('thabo@example.co.za');
echo "Status (read as property): {$app->status->value}\n";  // readable
$app->submit();
$app->approve();
echo "After submit->approve:     {$app->status->value}\n";

try {
    $app->status = ApplicationStatus::Draft;                 // ❌ Fatal: cannot write from outside
} catch (\Error $e) {
    echo "External write blocked:     {$e->getMessage()}\n";
}
