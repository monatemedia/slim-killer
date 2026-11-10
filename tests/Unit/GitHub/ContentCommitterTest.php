<?php

declare(strict_types=1);

use SlimKiller\Content\GitBlob;
use SlimKiller\GitHub\ContentCommitter;
use SlimKiller\GitHub\GitHubSettings;
use Tests\Fakes\FakeGitHub;

const ORYX = "---\ntitle: \"The Oryx\"\n---\n";
const AUTHOR = ['name' => 'Ada Client', 'email' => 'ada@example.com'];

function repository(): FakeGitHub
{
    return new FakeGitHub([
        'content/articles/oryx.md' => ORYX,
        'content/articles/lions.md' => "---\ntitle: \"Lions\"\n---\n",
        'site/package.json' => '{}',
    ]);
}

function committer(FakeGitHub $github, string $token = 'secret'): ContentCommitter
{
    return new ContentCommitter($github, new GitHubSettings('owner/site', 'main', $token));
}

it('saves the file as one commit on top of the branch, by the person who saved', function (): void {
    $github = repository();
    $before = $github->head;
    $new = "---\ntitle: \"The Gemsbok\"\n---\n";

    $result = committer($github)->save('articles/oryx.md', $new, GitBlob::hash(ORYX), 'Change The Oryx', AUTHOR);

    expect($result->isSaved())->toBeTrue()
        ->and($result->version)->toBe(GitBlob::hash($new))
        ->and($github->head)->toBe($result->commit)
        ->and($github->commits[$github->head]['parents'])->toBe([$before])
        ->and($github->commits[$github->head]['message'])->toBe('Change The Oryx')
        ->and($github->commits[$github->head]['author'])->toBe(AUTHOR)
        ->and($github->file('content/articles/oryx.md'))->toBe($new)
        ->and($github->file('content/articles/lions.md'))->toBe("---\ntitle: \"Lions\"\n---\n")
        ->and($github->file('site/package.json'))->toBe('{}');
});

it('refuses to save over a newer version of the same page', function (): void {
    $github = repository();
    $github->commitDirectly('content/articles/oryx.md', "---\ntitle: \"Saved by someone else\"\n---\n");
    $head = $github->head;

    $result = committer($github)->save('articles/oryx.md', 'mine', GitBlob::hash(ORYX), 'Change', AUTHOR);

    expect($result->isConflict())->toBeTrue()
        ->and($result->message)->toContain('Someone saved this page after you opened it')
        ->and($result->current)->toBe("---\ntitle: \"Saved by someone else\"\n---\n")
        ->and($github->head)->toBe($head);
});

it('goes on top of another page saved at the same moment, losing neither', function (): void {
    $github = repository();
    $github->beforeRefUpdate = static fn(FakeGitHub $g) => $g->commitDirectly('content/articles/lions.md', 'other save');

    $result = committer($github)->save('articles/oryx.md', 'my save', GitBlob::hash(ORYX), 'Change', AUTHOR);

    expect($result->isSaved())->toBeTrue()
        ->and($github->file('content/articles/oryx.md'))->toBe('my save')
        ->and($github->file('content/articles/lions.md'))->toBe('other save');
});

it('notices the same page saved at the same moment, and writes nothing', function (): void {
    $github = repository();
    $github->beforeRefUpdate = static fn(FakeGitHub $g) => $g->commitDirectly('content/articles/oryx.md', 'other save');

    $result = committer($github)->save('articles/oryx.md', 'my save', GitBlob::hash(ORYX), 'Change', AUTHOR);

    expect($result->isConflict())->toBeTrue()
        ->and($github->file('content/articles/oryx.md'))->toBe('other save');
});

it('explains failures in the client\'s words', function (?int $status, string $words): void {
    $github = repository();
    $github->failWith = $status;

    $result = committer($github)->save('articles/oryx.md', 'mine', GitBlob::hash(ORYX), 'Change', AUTHOR);

    expect($result->isSaved())->toBeFalse()->and($result->isConflict())->toBeFalse()
        ->and($result->message)->toContain($words);
})->with([
    'no connection' => [0, 'could not reach GitHub'],
    'expired key' => [401, 'refused the control panel\'s key'],
    'GitHub down' => [503, 'GitHub is having trouble'],
]);

it('says saving is not set up when the settings are missing, without calling GitHub', function (): void {
    $github = repository();

    $result = committer($github, token: '')->save('articles/oryx.md', 'mine', GitBlob::hash(ORYX), 'Change', AUTHOR);

    expect($result->message)->toContain('not set up')->and($github->requests)->toBe([]);
});
