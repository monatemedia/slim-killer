<?php

declare(strict_types=1);

namespace SlimKiller\Publishing;

use RuntimeException;

/** A downloaded build that cannot be used: the website stays as it is. */
final class BuildProblem extends RuntimeException {}
