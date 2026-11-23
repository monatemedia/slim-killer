<?php

declare(strict_types=1);

namespace SlimKiller\Backups;

use RuntimeException;

/** A backup could not be made, sent, checked or put back, for a reason it can name. */
final class BackupFailed extends RuntimeException {}
