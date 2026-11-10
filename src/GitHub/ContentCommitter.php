<?php

declare(strict_types=1);

namespace SlimKiller\GitHub;

use SlimKiller\Content\GitBlob;
use SlimKiller\Http\HttpClient;
use SlimKiller\Http\Response;

/**
 * Saves one content file as one commit, through GitHub's Git Data API (spike S1): no git
 * on the host, and the branch only ever moves forward.
 *
 * 1. Read the branch's head commit and its tree.
 * 2. Check the file's version there is the one the client opened. If not, someone else
 *    saved this page: a conflict, nothing is written, and the page as it now is comes
 *    back, so the panel can show the newer version.
 * 3. Send the new file (a blob), build a tree on top of the head's, make a commit.
 * 4. Move the branch to the commit, fast-forward only. If another save moved the branch
 *    in between, start again from 1 (a save to a different file simply goes on top).
 */
final class ContentCommitter
{
    private const string API = 'https://api.github.com';

    private const int ATTEMPTS = 3;

    public function __construct(
        private readonly HttpClient $http,
        private readonly GitHubSettings $settings,
    ) {}

    /**
     * @param string $contentPath the file's path inside content/: "articles/oryx.md"
     * @param string $openedVersion the Git blob hash of the file the client opened
     * @param array{name: string, email: string} $author
     */
    public function save(string $contentPath, string $bytes, string $openedVersion, string $message, array $author): SaveResult
    {
        if (! $this->settings->isComplete()) {
            return SaveResult::failed('Saving is not set up for this website yet. Ask Monate Media to finish the setup.');
        }
        $path = $this->settings->path($contentPath);

        try {
            for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
                $head = $this->string($this->call('GET', '/git/ref/heads/' . $this->settings->branch), 'object', 'sha');
                $tree = $this->string($this->call('GET', '/git/commits/' . $head), 'tree', 'sha');

                $current = $this->fileAt($head, $path);
                if (($current['sha'] ?? null) !== $openedVersion) {
                    return SaveResult::conflict($current['bytes'] ?? null);
                }

                $blob = $this->string($this->call('POST', '/git/blobs', [
                    'content' => base64_encode($bytes),
                    'encoding' => 'base64',
                ]), 'sha');
                $newTree = $this->string($this->call('POST', '/git/trees', [
                    'base_tree' => $tree,
                    'tree' => [['path' => $path, 'mode' => '100644', 'type' => 'blob', 'sha' => $blob]],
                ]), 'sha');
                $commit = $this->string($this->call('POST', '/git/commits', [
                    'message' => $message,
                    'tree' => $newTree,
                    'parents' => [$head],
                    'author' => $author,
                ]), 'sha');

                $moved = $this->request('PATCH', '/git/refs/heads/' . $this->settings->branch, ['sha' => $commit, 'force' => false]);
                if ($moved->ok()) {
                    return SaveResult::saved($commit, GitBlob::hash($bytes));
                }
                if ($moved->status !== 422) {
                    throw new GitHubFailure($moved);
                }
                // 422: the branch moved since step 1 (another save). Try again on top of it.
            }
        } catch (GitHubFailure $failure) {
            return SaveResult::failed($failure->describe());
        }

        return SaveResult::failed('The website was being changed by someone else at the same moment. Press Save again.');
    }

    /**
     * The file at a commit: its blob hash and its bytes, or null when it is not there.
     *
     * @return array{sha: string, bytes: string|null}|null
     */
    private function fileAt(string $commit, string $path): ?array
    {
        $response = $this->request('GET', '/contents/' . implode('/', array_map('rawurlencode', explode('/', $path))) . '?ref=' . $commit);
        if ($response->status === 404) {
            return null;
        }
        if (! $response->ok()) {
            throw new GitHubFailure($response);
        }
        $json = $response->json() ?? [];
        $sha = $json['sha'] ?? null;
        if (! is_string($sha)) {
            return null;
        }
        $content = $json['content'] ?? null;
        $bytes = ($json['encoding'] ?? null) === 'base64' && is_string($content)
            ? base64_decode(str_replace(["\n", "\r"], '', $content), true)
            : false;

        return ['sha' => $sha, 'bytes' => $bytes === false ? null : $bytes];
    }

    /** @param array<string, mixed>|null $body */
    private function call(string $method, string $endpoint, ?array $body = null): Response
    {
        $response = $this->request($method, $endpoint, $body);
        if (! $response->ok()) {
            throw new GitHubFailure($response);
        }

        return $response;
    }

    /** @param array<string, mixed>|null $body */
    private function request(string $method, string $endpoint, ?array $body = null): Response
    {
        return $this->http->send(
            $method,
            self::API . '/repos/' . $this->settings->repository . $endpoint,
            [
                'Accept' => 'application/vnd.github+json',
                'Authorization' => 'Bearer ' . $this->settings->token,
                'X-GitHub-Api-Version' => '2022-11-28',
                'Content-Type' => 'application/json',
            ],
            $body === null ? null : (string) json_encode($body, JSON_UNESCAPED_SLASHES),
        );
    }

    /** A string deep in a JSON answer ("object" → "sha"), or a failure when it is missing. */
    private function string(Response $response, string ...$keys): string
    {
        $value = $response->json();
        foreach ($keys as $key) {
            $value = is_array($value) ? ($value[$key] ?? null) : null;
        }
        if (! is_string($value) || $value === '') {
            throw new GitHubFailure($response);
        }

        return $value;
    }
}
