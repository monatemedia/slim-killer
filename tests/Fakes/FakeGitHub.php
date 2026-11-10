<?php

declare(strict_types=1);

namespace Tests\Fakes;

use Closure;
use SlimKiller\Content\GitBlob;
use SlimKiller\Http\HttpClient;
use SlimKiller\Http\Response;

/**
 * A GitHub repository in memory, answering the Git Data API calls the panel makes:
 * branch refs, commits, flat trees and blobs, with blob hashes computed the way Git
 * does. Tests can check the history it ends up with, make another save happen between
 * two of the panel's steps, or make GitHub fail.
 */
final class FakeGitHub implements HttpClient
{
    /** @var array<string, array{tree: string, parents: list<string>, message: string, author: mixed}> */
    public array $commits = [];

    /** @var list<string> */
    public array $requests = [];

    public string $head = '';

    /** @var array<string, array<string, string>> tree hash => path => blob hash */
    private array $trees = [];

    /** @var array<string, string> blob hash => bytes */
    private array $blobs = [];

    /** Runs once, just before the panel moves the branch: for "someone else saved meanwhile". */
    public ?Closure $beforeRefUpdate = null;

    /** Every answer has this status instead, when set (0 means GitHub cannot be reached). */
    public ?int $failWith = null;

    /** @param array<string, string> $files path => bytes */
    public function __construct(array $files)
    {
        $this->head = $this->commit($files, [], 'Start', null);
    }

    /** Commits files directly, as another person's save would. */
    public function commitDirectly(string $path, string $bytes): void
    {
        $files = $this->trees[$this->commits[$this->head]['tree']];
        $files[$path] = $this->blob($bytes);
        $this->head = $this->commitTree($this->tree($files), [$this->head], 'Another save', null);
    }

    public function file(string $path): ?string
    {
        $blob = $this->trees[$this->commits[$this->head]['tree']][$path] ?? null;

        return $blob === null ? null : $this->blobs[$blob];
    }

    public function get(string $url, array $headers = [], int $timeoutSeconds = 20): Response
    {
        return $this->send('GET', $url, $headers);
    }

    public function send(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutSeconds = 20): Response
    {
        $this->requests[] = $method . ' ' . $url;
        if ($this->failWith !== null) {
            return new Response($this->failWith, '{"message":"failure"}', $this->failWith === 0 ? 'Could not resolve host' : '');
        }
        $path = (string) preg_replace('#^https://api\.github\.com/repos/owner/site#', '', $url);
        $data = json_decode((string) $body, true);
        $data = is_array($data) ? $data : [];

        return match (true) {
            $method === 'GET' && $path === '/git/ref/heads/main' => $this->json(['object' => ['sha' => $this->head]]),
            $method === 'GET' && preg_match('#^/git/commits/(\w+)$#', $path, $m) === 1 => isset($this->commits[$m[1]])
                ? $this->json(['sha' => $m[1], 'tree' => ['sha' => $this->commits[$m[1]]['tree']]])
                : new Response(404, '{}'),
            $method === 'GET' && preg_match('#^/contents/(.+)\?ref=(\w+)$#', $path, $m) === 1 => $this->contents(rawurldecode($m[1]), $m[2]),
            $method === 'POST' && $path === '/git/blobs' => $this->json(['sha' => $this->blob((string) base64_decode((string) $data['content'], true))], 201),
            $method === 'POST' && $path === '/git/trees' => $this->json(['sha' => $this->treeOn((string) $data['base_tree'], (array) $data['tree'])], 201),
            $method === 'POST' && $path === '/git/commits' => $this->json(['sha' => $this->commitTree(
                (string) $data['tree'],
                array_values(array_map('strval', (array) $data['parents'])),
                (string) $data['message'],
                $data['author'] ?? null,
            )], 201),
            $method === 'PATCH' && $path === '/git/refs/heads/main' => $this->moveBranch((string) $data['sha']),
            default => new Response(404, '{"message":"Not Found"}'),
        };
    }

    private function contents(string $path, string $commit): Response
    {
        $blob = $this->trees[$this->commits[$commit]['tree'] ?? ''][$path] ?? null;

        if ($blob === null) {
            return new Response(404, '{"message":"Not Found"}');
        }

        // GitHub wraps the base64 at 60 characters; the panel must cope with that.
        return $this->json(['sha' => $blob, 'path' => $path, 'encoding' => 'base64', 'content' => chunk_split(base64_encode($this->blobs[$blob]), 60, "\n")]);
    }

    private function moveBranch(string $commit): Response
    {
        if ($this->beforeRefUpdate !== null) {
            $before = $this->beforeRefUpdate;
            $this->beforeRefUpdate = null;
            $before($this);
        }
        // Fast-forward only: the new commit must sit directly on the current head.
        if (! in_array($this->head, $this->commits[$commit]['parents'] ?? [], true)) {
            return new Response(422, '{"message":"Update is not a fast forward"}');
        }
        $this->head = $commit;

        return $this->json(['object' => ['sha' => $commit]]);
    }

    /** @param array<string, string> $files */
    private function commit(array $files, array $parents, string $message, mixed $author): string
    {
        $blobs = [];
        foreach ($files as $path => $bytes) {
            $blobs[$path] = $this->blob($bytes);
        }

        return $this->commitTree($this->tree($blobs), $parents, $message, $author);
    }

    /** @param list<string> $parents */
    private function commitTree(string $tree, array $parents, string $message, mixed $author): string
    {
        $sha = sha1($tree . implode(',', $parents) . $message . count($this->commits));
        $this->commits[$sha] = ['tree' => $tree, 'parents' => $parents, 'message' => $message, 'author' => $author];

        return $sha;
    }

    /** @param array<mixed> $entries */
    private function treeOn(string $base, array $entries): string
    {
        $files = $this->trees[$base] ?? [];
        foreach ($entries as $entry) {
            $files[(string) $entry['path']] = (string) $entry['sha'];
        }

        return $this->tree($files);
    }

    /** @param array<string, string> $files */
    private function tree(array $files): string
    {
        ksort($files);
        $sha = sha1((string) json_encode($files));
        $this->trees[$sha] = $files;

        return $sha;
    }

    private function blob(string $bytes): string
    {
        $sha = GitBlob::hash($bytes);
        $this->blobs[$sha] = $bytes;

        return $sha;
    }

    /** @param array<mixed> $data */
    private function json(array $data, int $status = 200): Response
    {
        return new Response($status, (string) json_encode($data));
    }
}
