<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\Checks;

use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\HostCheck;
use SlimKiller\Support\Hammer;
use SlimKiller\Support\HammerShortcut;
use SlimKiller\Support\Paths;

/** Can "hammer" be typed on its own? A convenience, so never a warning. */
final readonly class ShortcutCheck implements HostCheck
{
    public function __construct(
        private Paths $paths,
    ) {}

    public function check(): array
    {
        if (HammerShortcut::installedFor($this->paths->root())) {
            return [CheckResult::pass('The hammer shortcut', 'hammer works from any folder')];
        }

        return [CheckResult::info('No hammer shortcut', 'run ' . Hammer::command('shortcut') . ' once, to type hammer instead of the PHP\'s path')];
    }
}
