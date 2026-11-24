<?php

declare(strict_types=1);

use SlimKiller\Doctor\Checks\SavingCheck;
use SlimKiller\GitHub\ContentCommitter;
use SlimKiller\GitHub\ContentOrigin;
use SlimKiller\GitHub\GitHubSettings;
use SlimKiller\Http\Response;
use Tests\Fakes\FakeHttpClient;

/** A site checkout with its content folder, cloned from $url. Returns the content folder. */
function checkoutFrom(string $url): string
{
    $site = temporaryFolder();
    mkdir($site . '/.git', 0755, true);
    mkdir($site . '/content/articles', 0755, true);
    file_put_contents($site . '/.git/config', "[core]\n\trepositoryformatversion = 0\n[remote \"upstream\"]\n\turl = https://github.com/someone/else.git\n[remote \"origin\"]\n\turl = {$url}\n\tfetch = +refs/heads/*:refs/remotes/origin/*\n[branch \"main\"]\n\tremote = origin\n");

    return $site . '/content';
}

it('reads the repository a content folder came from, by HTTPS or SSH, from a few folders up', function (string $url): void {
    expect(ContentOrigin::repositoryOf(checkoutFrom($url)))->toBe('monatemedia/slim-killer-monate-media')
        ->and(ContentOrigin::repositoryOf(checkoutFrom($url) . '/articles'))->toBe('monatemedia/slim-killer-monate-media');
})->with([
    'https' => 'https://github.com/monatemedia/slim-killer-monate-media.git',
    'https without .git' => 'https://github.com/monatemedia/slim-killer-monate-media',
    'ssh' => 'git@github.com:monatemedia/slim-killer-monate-media.git',
]);

it('knows nothing when there is no checkout, or its origin is not on GitHub', function (): void {
    expect(ContentOrigin::repositoryOf(temporaryFolder()))->toBeNull()
        ->and(ContentOrigin::repositoryOf(checkoutFrom('https://gitlab.com/monatemedia/site.git')))->toBeNull()
        ->and(ContentOrigin::same('MonateMedia/Site', 'monatemedia/site'))->toBeTrue();
});

it('refuses a save to another site\'s repository, before asking GitHub anything', function (): void {
    $github = new FakeHttpClient(fn(): Response => new Response(500, ''));
    $committer = new ContentCommitter($github, new GitHubSettings('monatemedia/slim-killer-honey-badger', 'main', 'token'), 'monatemedia/slim-killer-monate-media');

    $result = $committer->save('home/index.md', "---\ntitle: \"Home\"\n---\n", 'abc', 'Home', ['name' => 'Edward', 'email' => 'edward@example.com']);

    expect($result->isSaved())->toBeFalse()
        ->and($result->message)->toBe('Not saved: this content comes from monatemedia/slim-killer-monate-media, but saves go to monatemedia/slim-killer-honey-badger (GITHUB_REPOSITORY in .env). Point CONTENT_DIR and GITHUB_REPOSITORY at the same site.')
        ->and($github->requested)->toBe([]);
});

it('tells the doctor where saves go, and fails when they would go to another site', function (): void {
    $settings = new GitHubSettings('monatemedia/slim-killer-monate-media', 'main', 'token');
    $line = static fn(SavingCheck $check): string => (static fn($r): string => $r->status->label() . ' ' . $r->title . ': ' . $r->detail)($check->check()[0]);

    expect($line(new SavingCheck(new GitHubSettings('', 'main', ''))))->toStartWith('INFO Saving isn\'t set up')
        ->and($line(new SavingCheck($settings)))->toBe('PASS Saves go to monatemedia/slim-killer-monate-media: branch main')
        ->and($line(new SavingCheck($settings, 'monatemedia/slim-killer-monate-media')))->toBe('PASS Saves go to monatemedia/slim-killer-monate-media: the repository this content came from')
        ->and($line(new SavingCheck($settings, 'monatemedia/slim-killer-honey-badger')))->toBe('FAIL Saves would go to another site: the content comes from monatemedia/slim-killer-honey-badger, but GITHUB_REPOSITORY is monatemedia/slim-killer-monate-media');
});
