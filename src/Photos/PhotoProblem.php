<?php

declare(strict_types=1);

namespace SlimKiller\Photos;

use RuntimeException;

/** An upload that cannot become a photo, explained in the client's words. */
final class PhotoProblem extends RuntimeException {}
