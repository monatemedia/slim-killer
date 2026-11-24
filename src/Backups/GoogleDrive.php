<?php

declare(strict_types=1);

namespace SlimKiller\Backups;

use SlimKiller\Http\HttpClient;
use SlimKiller\Http\Response;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\Folder;
use SlimKiller\Support\Hammer;

/**
 * The site's own Google Drive, through Google's web API: no program to install, since
 * shared hosts let PHP make web requests but not run other programs.
 *
 * - Connecting is done once, by the site's owner allowing it in a browser (OAuth, with
 *   PKCE). Google gives a refresh token, kept in storage/google-drive.json.
 * - The permission asked for is "drive.file": Slim Killer sees only the files it made
 *   itself, never anything else in that Drive.
 * - Backups go into one folder in the root of the Drive (BACKUP_FOLDER), encrypted
 *   first (BackupEncryption), and every upload is compared with Google's checksum of
 *   what arrived.
 */
final class GoogleDrive
{
    public const string AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    public const string TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const string FILES_URL = 'https://www.googleapis.com/drive/v3/files';

    public const string UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3/files';

    public const string SCOPE = 'https://www.googleapis.com/auth/drive.file';

    /**
     * Where Google sends the browser after "Allow". Nothing answers there: the page
     * fails to load, and the code is in its address, to paste into backup:connect.
     */
    public const string REDIRECT = 'http://127.0.0.1:53682/';

    private const string FOLDER_TYPE = 'application/vnd.google-apps.folder';

    private const string FORM = 'application/x-www-form-urlencoded';

    private ?string $accessToken = null;

    public function __construct(
        private readonly HttpClient $http,
        private readonly AppConfig $config,
        private readonly DriveStore $store,
    ) {}

    /** Are GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in .env? */
    public function isSetUp(): bool
    {
        return $this->config->googleClientId !== '' && $this->config->googleClientSecret !== '';
    }

    public function isConnected(): bool
    {
        return $this->isSetUp() && $this->store->refreshToken() !== '';
    }

    /** Step one of connecting: the link to open while signed in to the site's Google account. */
    public function connectLink(): string
    {
        $verifier = $this->base64url(random_bytes(48));
        $state = bin2hex(random_bytes(16));
        $this->store->startConnecting($verifier, $state);

        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => $this->config->googleClientId,
            'redirect_uri' => self::REDIRECT,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
            'code_challenge' => $this->base64url(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Step two: the address the browser landed on after "Allow" (or only its code).
     * Swaps the code for a refresh token, then finds or makes the backup folder.
     *
     * @throws BackupFailed
     */
    public function connect(string $landedOn): void
    {
        $pending = $this->store->connecting();
        if ($pending === null) {
            throw new BackupFailed('Run ' . Hammer::command('backup:connect') . ' without --code first, to get the link.');
        }

        $code = trim($landedOn);
        if (str_contains($code, '<') || str_contains($code, '>')) {
            throw new BackupFailed('Put the address the browser landed on in place of <the address>, between single quotes.');
        }
        if (str_contains($code, '/o/oauth2/')) {
            throw new BackupFailed('That is the link you opened, not the address the browser landed on. After Continue, the address bar changes to one starting ' . self::REDIRECT . '?state=: copy that one.');
        }
        if (str_contains($code, '?')) {
            parse_str((string) parse_url($code, PHP_URL_QUERY), $query);
            if (is_string($query['error'] ?? null)) {
                throw new BackupFailed("Google says: {$query['error']}. Run " . Hammer::command('backup:connect') . ' again and choose Allow.');
            }
            if (($query['state'] ?? null) !== $pending['state']) {
                throw new BackupFailed('That address is from another link. Run ' . Hammer::command('backup:connect') . ' again and use the newest link.');
            }
            $code = is_string($query['code'] ?? null) ? $query['code'] : '';
        }
        if ($code === '') {
            throw new BackupFailed('There is no code in that address. Paste the whole address from the browser\'s address bar.');
        }

        $response = $this->http->send('POST', self::TOKEN_URL, ['Content-Type' => self::FORM], http_build_query([
            'code' => $code,
            'client_id' => $this->config->googleClientId,
            'client_secret' => $this->config->googleClientSecret,
            'redirect_uri' => self::REDIRECT,
            'grant_type' => 'authorization_code',
            'code_verifier' => $pending['verifier'],
        ]));
        $data = $response->json() ?? [];
        if (! $response->ok() || ! is_string($data['refresh_token'] ?? null) || ! is_string($data['access_token'] ?? null)) {
            throw new BackupFailed('Google did not accept the code (' . $this->problem($response) . '). A code works once, for a few minutes: run backup:connect again.');
        }

        $this->store->connected($data['refresh_token']);
        $this->accessToken = $data['access_token'];
        $this->folderId();
    }

    /**
     * Uploads a backup into the folder and checks that what arrived matches.
     *
     * @throws BackupFailed
     */
    public function upload(string $path): void
    {
        $bytes = (string) @file_get_contents($path);
        $name = basename($path);
        $start = $this->startUpload($name, strlen($bytes));
        if ($start->status === 404) {
            // The folder was deleted in Drive since it was found: find or make it again.
            $this->store->forgetFolder();
            $start = $this->startUpload($name, strlen($bytes));
        }
        $session = $start->header('location');
        if (! $start->ok() || $session === '') {
            throw new BackupFailed('Google Drive did not start the upload (' . $this->problem($start) . ').');
        }

        $done = $this->call('PUT', $session, ['Content-Type' => 'application/octet-stream'], $bytes, 300);
        $file = $done->json() ?? [];
        if (! $done->ok() || ! is_string($file['id'] ?? null)) {
            throw new BackupFailed('Google Drive did not take the upload (' . $this->problem($done) . ').');
        }
        if (($file['md5Checksum'] ?? null) !== md5($bytes)) {
            $this->delete($file['id']);
            throw new BackupFailed("Google Drive's copy of {$name} did not match this one, so it was removed.");
        }
    }

    /**
     * The backups in the folder, newest first.
     *
     * @return list<array{id: string, name: string}>
     *
     * @throws BackupFailed
     */
    public function backups(): array
    {
        $files = [];
        $page = '';
        do {
            $query = [
                'q' => "'" . $this->folderId() . "' in parents and trashed = false",
                'fields' => 'nextPageToken,files(id,name)',
                'orderBy' => 'name desc',
                'pageSize' => '1000',
                'spaces' => 'drive',
            ] + ($page === '' ? [] : ['pageToken' => $page]);
            $response = $this->call('GET', self::FILES_URL . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
            $data = $response->json() ?? [];
            if (! $response->ok()) {
                throw new BackupFailed('Google Drive did not list the backups (' . $this->problem($response) . ').');
            }
            foreach (is_array($data['files'] ?? null) ? $data['files'] : [] as $file) {
                if (is_array($file) && is_string($file['id'] ?? null) && is_string($file['name'] ?? null) && preg_match(DatabaseBackup::NAME_ON_DRIVE, $file['name']) === 1) {
                    $files[] = ['id' => $file['id'], 'name' => $file['name']];
                }
            }
            $page = is_string($data['nextPageToken'] ?? null) ? $data['nextPageToken'] : '';
        } while ($page !== '');

        usort($files, static fn(array $a, array $b): int => strcmp($b['name'], $a['name']));

        return $files;
    }

    /**
     * Deletes all but the newest $keep backups, and says how many went.
     *
     * @throws BackupFailed
     */
    public function keepNewest(int $keep): int
    {
        $old = array_slice($this->backups(), max(1, $keep));
        foreach ($old as $file) {
            $this->delete($file['id']);
        }

        return count($old);
    }

    /** @throws BackupFailed */
    public function download(string $id, string $path): void
    {
        $response = $this->http->download(self::FILES_URL . '/' . rawurlencode($id) . '?alt=media', ['Authorization' => 'Bearer ' . $this->token()], $path);
        if (! $response->ok()) {
            Folder::removeFile($path);
            throw new BackupFailed('Google Drive did not send the backup (' . $this->problem($response) . ').');
        }
    }

    /** @throws BackupFailed */
    private function delete(string $id): void
    {
        $response = $this->call('DELETE', self::FILES_URL . '/' . rawurlencode($id));
        if (! $response->ok() && $response->status !== 404) {
            throw new BackupFailed('Google Drive did not delete an old backup (' . $this->problem($response) . ').');
        }
    }

    private function startUpload(string $name, int $size): Response
    {
        return $this->call('POST', self::UPLOAD_URL . '?uploadType=resumable&fields=id,name,md5Checksum', [
            'Content-Type' => 'application/json; charset=UTF-8',
            'X-Upload-Content-Type' => 'application/octet-stream',
            'X-Upload-Content-Length' => (string) $size,
        ], json_encode(['name' => $name, 'parents' => [$this->folderId()]], JSON_THROW_ON_ERROR));
    }

    /** The backup folder's id: remembered, found by name in the root, or made there. */
    private function folderId(): string
    {
        $name = $this->config->backupFolder;
        $known = $this->store->folderId($name);
        if ($known !== '') {
            return $known;
        }

        $query = "name = '" . addcslashes($name, "\\'") . "' and mimeType = '" . self::FOLDER_TYPE . "' and 'root' in parents and trashed = false";
        $found = $this->call('GET', self::FILES_URL . '?' . http_build_query(['q' => $query, 'fields' => 'files(id)', 'spaces' => 'drive'], '', '&', PHP_QUERY_RFC3986));
        $files = $found->json()['files'] ?? null;
        $id = is_array($files) && is_array($files[0] ?? null) && is_string($files[0]['id'] ?? null) ? $files[0]['id'] : '';

        if ($id === '') {
            $made = $this->call('POST', self::FILES_URL . '?fields=id', ['Content-Type' => 'application/json; charset=UTF-8'], json_encode([
                'name' => $name,
                'mimeType' => self::FOLDER_TYPE,
                'parents' => ['root'],
            ], JSON_THROW_ON_ERROR));
            $id = $made->json()['id'] ?? '';
            if (! $made->ok() || ! is_string($id) || $id === '') {
                throw new BackupFailed("Google Drive did not make the folder \"{$name}\" (" . $this->problem($made) . ').');
            }
        }
        $this->store->rememberFolder($name, $id);

        return $id;
    }

    /** @param array<string, string> $headers */
    private function call(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutSeconds = 60): Response
    {
        return $this->http->send($method, $url, ['Authorization' => 'Bearer ' . $this->token()] + $headers, $body, $timeoutSeconds);
    }

    /** A short-lived access token, from the refresh token. */
    private function token(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }
        if (! $this->isConnected()) {
            throw new BackupFailed('Google Drive is not connected. Run ' . Hammer::command('backup:connect') . '.');
        }

        $response = $this->http->send('POST', self::TOKEN_URL, ['Content-Type' => self::FORM], http_build_query([
            'client_id' => $this->config->googleClientId,
            'client_secret' => $this->config->googleClientSecret,
            'refresh_token' => $this->store->refreshToken(),
            'grant_type' => 'refresh_token',
        ]));
        $data = $response->json() ?? [];
        if ($response->ok() && is_string($data['access_token'] ?? null)) {
            return $this->accessToken = $data['access_token'];
        }
        if (($data['error'] ?? null) === 'invalid_grant') {
            throw new BackupFailed('Google no longer accepts this website\'s connection: it was removed in the Google account, or the Google Cloud app is still in Testing. Run ' . Hammer::command('backup:connect') . ' again.');
        }

        throw new BackupFailed('Google did not answer the sign-in (' . $this->problem($response) . ').');
    }

    /** What went wrong, in Google's words when it gave any. Never includes a token. */
    private function problem(Response $response): string
    {
        $data = $response->json() ?? [];
        $error = $data['error'] ?? null;
        if (is_array($error) && is_string($error['message'] ?? null)) {
            return $error['message'];
        }
        if (is_string($data['error_description'] ?? null)) {
            return $data['error_description'];
        }
        if (is_string($error)) {
            return $error;
        }

        return $response->error !== '' ? $response->error : 'HTTP ' . $response->status;
    }

    private function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
