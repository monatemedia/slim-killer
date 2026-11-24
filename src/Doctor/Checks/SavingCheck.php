<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\Checks;

use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\HostCheck;
use SlimKiller\GitHub\ContentOrigin;
use SlimKiller\GitHub\GitHubSettings;

/**
 * Where saves go: GITHUB_REPOSITORY, and on a developer's computer, whether that is the
 * repository the content came from. A mismatch fails, because a save would land in
 * another site.
 */
final readonly class SavingCheck implements HostCheck
{
    public function __construct(
        private GitHubSettings $settings,
        private ?string $contentRepository = null,
    ) {}

    public function check(): array
    {
        if (! $this->settings->isComplete()) {
            return [CheckResult::info('Saving isn\'t set up', 'GITHUB_REPOSITORY, GITHUB_BRANCH and GITHUB_TOKEN in .env; until then the control panel shows pages but can\'t save them')];
        }
        $repository = $this->settings->repository;
        if ($this->contentRepository === null) {
            return [CheckResult::pass('Saves go to ' . $repository, 'branch ' . $this->settings->branch)];
        }
        if (ContentOrigin::same($this->contentRepository, $repository)) {
            return [CheckResult::pass('Saves go to ' . $repository, 'the repository this content came from')];
        }

        return [CheckResult::fail(
            'Saves would go to another site',
            "the content comes from {$this->contentRepository}, but GITHUB_REPOSITORY is {$repository}",
            'Point CONTENT_DIR and GITHUB_REPOSITORY in .env at the same site. Until then every save is refused.',
        )];
    }
}
