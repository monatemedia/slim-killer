<?php

declare(strict_types=1);

namespace SlimKiller\Cron;

use RuntimeException;

/** A job could not do its work, for a reason it can name (BUILD_PHILOSOPHY.md, Rule 12). */
final class JobFailed extends RuntimeException {}
