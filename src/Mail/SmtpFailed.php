<?php

declare(strict_types=1);

namespace SlimKiller\Mail;

use RuntimeException;

/** The mail server could not be reached, or said no, in its own words. Never holds a password. */
final class SmtpFailed extends RuntimeException {}
