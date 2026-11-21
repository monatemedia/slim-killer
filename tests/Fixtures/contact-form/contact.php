<?php

declare(strict_types=1);

namespace MonateMedia\SlimKillerForms;

use stdClass;

/*
 * A stand-in for slim-killer-forms' contact.php, for ContactFormJob's tests. It notes
 * each "send what is due" in $GLOBALS['contactFormSentAt'], and answers with the number
 * in $GLOBALS['contactFormDue']. $GLOBALS['contactFormSetUp'] = false acts as a missing
 * contact-config.php.
 */

function config(): ?stdClass
{
    return ($GLOBALS['contactFormSetUp'] ?? true) === true ? new stdClass() : null;
}

function outbox(stdClass $config): object
{
    return new class {
        public function sendDue(int $now): int
        {
            $GLOBALS['contactFormSentAt'][] = $now;

            return $GLOBALS['contactFormDue'] ?? 0;
        }
    };
}
