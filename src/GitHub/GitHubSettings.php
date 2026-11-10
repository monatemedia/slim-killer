<?php

declare(strict_types=1);

namespace SlimKiller\GitHub;

/**
 * Where the client site's repository is and how the panel may write to it: the
 * repository ("owner/name"), the branch saves go to, a fine-grained token with contents
 * write on that one repository, and the folder in it that holds content/.
 */
final readonly class GitHubSettings
{
    public function __construct(
        public string $repository,
        public string $branch,
        public string $token,
        public string $contentFolder = 'content',
    ) {}

    public function isComplete(): bool
    {
        return preg_match('#^[\w.-]+/[\w.-]+$#', $this->repository) === 1 && $this->branch !== '' && $this->token !== '';
    }

    /** The path in the repository of a file in the content folder: "content/articles/oryx.md". */
    public function path(string $contentPath): string
    {
        return trim($this->contentFolder . '/' . ltrim($contentPath, '/'), '/');
    }
}
