<?php

declare(strict_types=1);

namespace Tests\Fakes;

use SlimKiller\Backups\GoogleDrive;
use SlimKiller\Http\Response;

/**
 * Google's sign-in and Drive, as far as backups use them: a token endpoint, one root
 * folder, resumable uploads, listing, deleting and downloading. Files live in memory.
 */
final class FakeGoogle
{
    public const string SESSION = 'https://upload.example/session-1';

    /** @var array<string, array{name: string, bytes: string, parent: string}> by id */
    public array $files = [];

    /** @var array<string, string> folder id => name */
    public array $folders = [];

    public bool $failUploads = false;

    public bool $damageUploads = false;

    public bool $refuseRefresh = false;

    private int $next = 1;

    private string $uploading = '';

    public readonly FakeHttpClient $http;

    public function __construct()
    {
        $this->http = new FakeHttpClient(fn(string $url, string $method, ?string $body): Response => $this->answer($url, $method, $body));
    }

    private function answer(string $url, string $method, ?string $body): Response
    {
        if ($url === GoogleDrive::TOKEN_URL) {
            parse_str((string) $body, $form);
            if (($form['grant_type'] ?? '') === 'authorization_code') {
                return $this->json(['access_token' => 'access-1', 'refresh_token' => 'refresh-1']);
            }

            return $this->refuseRefresh
                ? new Response(400, '{"error":"invalid_grant","error_description":"Token has been expired or revoked."}')
                : $this->json(['access_token' => 'access-2']);
        }

        if (str_starts_with($url, GoogleDrive::UPLOAD_URL) && $method === 'POST') {
            $meta = json_decode((string) $body, true);
            if (! isset($this->folders[$meta['parents'][0] ?? ''])) {
                return new Response(404, '{"error":{"message":"File not found."}}');
            }
            $this->uploading = json_encode($meta, JSON_THROW_ON_ERROR);

            return new Response(200, '', '', ['location' => self::SESSION]);
        }

        if ($url === self::SESSION && $method === 'PUT') {
            if ($this->failUploads) {
                return new Response(503, '{"error":{"message":"Backend Error"}}');
            }
            $meta = json_decode($this->uploading, true);
            $id = 'file-' . $this->next++;
            $this->files[$id] = ['name' => $meta['name'], 'bytes' => (string) $body, 'parent' => $meta['parents'][0]];

            return $this->json(['id' => $id, 'name' => $meta['name'], 'md5Checksum' => $this->damageUploads ? md5('something else') : md5((string) $body)]);
        }

        if (str_starts_with($url, GoogleDrive::FILES_URL . '?') && $method === 'GET') {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $q = (string) ($query['q'] ?? '');
            if (str_contains($q, 'mimeType')) {
                preg_match("/name = '(.*?)'/", $q, $match);
                $ids = array_keys(array_filter($this->folders, static fn(string $name): bool => $name === ($match[1] ?? '')));

                return $this->json(['files' => array_map(static fn(string $id): array => ['id' => $id], $ids)]);
            }
            preg_match("/'(.*?)' in parents/", $q, $match);
            $files = [];
            foreach ($this->files as $id => $file) {
                if ($file['parent'] === ($match[1] ?? '')) {
                    $files[] = ['id' => $id, 'name' => $file['name']];
                }
            }

            return $this->json(['files' => $files]);
        }

        if ($url === GoogleDrive::FILES_URL . '?fields=id' && $method === 'POST') {
            $id = 'folder-' . $this->next++;
            $this->folders[$id] = (string) (json_decode((string) $body, true)['name'] ?? '');

            return $this->json(['id' => $id]);
        }

        if (str_starts_with($url, GoogleDrive::FILES_URL . '/')) {
            $id = rawurldecode((string) strtok(substr($url, strlen(GoogleDrive::FILES_URL . '/')), '?'));
            if ($method === 'DELETE') {
                unset($this->files[$id]);

                return new Response(204, '');
            }

            return isset($this->files[$id]) ? new Response(200, $this->files[$id]['bytes']) : new Response(404, '');
        }

        return new Response(500, 'The fake does not know ' . $method . ' ' . $url);
    }

    /** @param array<mixed> $data */
    private function json(array $data): Response
    {
        return new Response(200, json_encode($data, JSON_THROW_ON_ERROR));
    }

    /** @return list<string> the backups' names, oldest first */
    public function names(): array
    {
        $names = array_column($this->files, 'name');
        sort($names);

        return $names;
    }
}
