<?php

declare(strict_types=1);

namespace SlimKiller\Updates;

use RuntimeException;

/** GitHub could not be asked for the newest Slim Killer. */
final class UpdateCheckFailed extends RuntimeException {}
