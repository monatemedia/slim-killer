<?php

declare(strict_types=1);

namespace SlimKiller\Publishing;

use RuntimeException;
use SlimKiller\Content\ContentLocation;
use SlimKiller\GitHub\GitHubFailure;
use SlimKiller\GitHub\GitHubSettings;
use SlimKiller\Http\HttpClient;
use SlimKiller\Http\Response;
use SlimKiller\Support\Clock;
use SlimKiller\Support\Folder;
use SlimKiller\Support\Paths;
use ZipArchive;

/**
 * Fetches the newest build from the dist branch and makes it live (spike S2).
 *
 * 1. Ask GitHub which commit the dist branch is on. A build fetched before is left
 *    alone, so a rollback stays put until there is a new build.
 * 2. Download that commit as a zip, streamed to disk, and unpack it beside the releases.
 * 3. Check it: a home page, and the panel's _slim-killer/build.json.
 * 4. Move _slim-killer/ out of the release, so it is never served.
 * 5. Switch the website to the release, then refresh the panel's working copy from it.
 * 6. Keep the newest three releases.
 *
 * Any failure leaves the website on the release it was on. A lock stops two fetches
 * (the webhook and cron) running at once.
 */
final class Fetcher
{
    public const string BRANCH = 'dist';

    public const int KEEP = 3;

    private const string API = 'https://api.github.com';

    private const string PANEL_FOLDER = '_slim-killer';

    public function __construct(
        private readonly HttpClient $http,
        private readonly GitHubSettings $settings,
        private readonly Releases $releases,
        private readonly ContentLocation $location,
        private readonly Paths $paths,
        private readonly Clock $clock,
    ) {}

    public function fetch(): FetchResult
    {
        $problem = $this->problem();
        if ($problem !== null) {
            return FetchResult::failed($problem);
        }

        return $this->locked(function (): FetchResult {
            $zip = $this->paths->storage('tmp/build.zip');
            $incoming = $this->releases->folder('.incoming');

            try {
                $build = $this->distCommit();
                if ($this->releases->knows($build)) {
                    return FetchResult::unchanged($this->releases->live());
                }

                $this->download($build, $zip);
                Folder::delete($incoming);
                $this->unpack($zip, $incoming);
                $record = $this->check($incoming);

                $panel = $this->paths->storage('tmp/' . self::PANEL_FOLDER);
                Folder::delete($panel);
                Folder::move($incoming . '/' . self::PANEL_FOLDER, $panel);

                $release = new Release(
                    substr($build, 0, 12),
                    $build,
                    $record['commit'],
                    $record['builtAt'],
                    $this->clock->now()->format(DATE_ATOM),
                    $this->redirects($panel),
                );
                $this->releases->add($release, $incoming);
                $this->releases->switchTo($release);
                $copy = $this->refreshWorkingCopy($panel);
                $this->releases->prune(self::KEEP);

                return FetchResult::switched($release, 'Live: the build of ' . $release->shortCommit() . '. ' . $copy);
            } catch (GitHubFailure $failure) {
                return FetchResult::failed($this->explain($failure->response) . ' The website is unchanged.');
            } catch (BuildProblem $problem) {
                return FetchResult::failed($problem->getMessage() . ' The website is unchanged.');
            } finally {
                if (is_file($zip)) {
                    unlink($zip);
                }
                Folder::delete($incoming);
                Folder::delete($this->paths->storage('tmp/' . self::PANEL_FOLDER));
            }
        });
    }

    /** Switches to the release fetched before the live one. */
    public function rollback(): FetchResult
    {
        $problem = $this->releases->problem();
        if ($problem !== null) {
            return FetchResult::failed($problem);
        }

        return $this->locked(function (): FetchResult {
            $previous = $this->releases->previous();
            if ($previous === null) {
                return FetchResult::failed('There is no earlier release on this host to go back to.');
            }
            $this->releases->switchTo($previous);

            return FetchResult::switched($previous, 'Live: the build of ' . $previous->shortCommit()
                . ', fetched ' . $previous->fetchedAt . '. It stays live until the next new build.');
        });
    }

    private function problem(): ?string
    {
        if (! $this->settings->isComplete()) {
            return 'Publishing is not set up: GITHUB_REPOSITORY and GITHUB_TOKEN in .env are needed.';
        }

        return $this->releases->problem();
    }

    /**
     * @param callable(): FetchResult $work
     */
    private function locked(callable $work): FetchResult
    {
        $file = $this->paths->storage('fetch.lock');
        if (! is_dir(dirname($file))) {
            @mkdir(dirname($file), 0755, true);
        }
        $lock = @fopen($file, 'c');
        if ($lock === false) {
            return FetchResult::failed("Could not open {$file}. Is the storage folder writable?");
        }
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return FetchResult::busy();
        }

        try {
            return $work();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function distCommit(): string
    {
        $response = $this->request('/git/ref/heads/' . self::BRANCH);
        if ($response->status === 404) {
            throw new BuildProblem('GitHub has no build yet (no ' . self::BRANCH . ' branch), or GITHUB_REPOSITORY is wrong.');
        }
        $json = $response->ok() ? $response->json() : null;
        $object = is_array($json['object'] ?? null) ? $json['object'] : [];
        $sha = $object['sha'] ?? null;
        if (! is_string($sha) || preg_match('/^[0-9a-f]{40}$/', $sha) !== 1) {
            throw new GitHubFailure($response);
        }

        return $sha;
    }

    private function download(string $build, string $zip): void
    {
        if (! is_dir(dirname($zip))) {
            @mkdir(dirname($zip), 0755, true);
        }
        $response = $this->http->download(
            self::API . '/repos/' . $this->settings->repository . '/zipball/' . $build,
            $this->headers(),
            $zip,
            300,
        );
        if (! $response->ok()) {
            throw new GitHubFailure($response);
        }
    }

    /**
     * Unpacks GitHub's zip, dropping the folder it wraps everything in
     * ("owner-repo-abc1234/"), and refusing any name that could reach outside.
     */
    private function unpack(string $zip, string $to): void
    {
        $archive = new ZipArchive();
        if ($archive->open($zip) !== true) {
            throw new BuildProblem('The build GitHub sent is not a zip file.');
        }

        try {
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $name = str_replace('\\', '/', (string) $archive->getNameIndex($index));
                $parts = explode('/', $name);
                array_shift($parts);
                $isFolder = str_ends_with($name, '/');
                if ($isFolder) {
                    array_pop($parts);
                }
                if ($parts === []) {
                    continue;
                }
                foreach ($parts as $part) {
                    if ($part === '' || $part === '.' || $part === '..' || str_contains($part, ':')) {
                        throw new BuildProblem("The build contains a file name that is not allowed: {$name}");
                    }
                }
                $path = $to . '/' . implode('/', $parts);
                if ($isFolder) {
                    @mkdir($path, 0755, true);

                    continue;
                }
                if (! is_dir(dirname($path))) {
                    @mkdir(dirname($path), 0755, true);
                }
                $from = $archive->getStream((string) $archive->getNameIndex($index));
                $into = @fopen($path, 'wb');
                if ($from === false || $into === false) {
                    throw new BuildProblem("Could not unpack {$name}.");
                }
                stream_copy_to_stream($from, $into);
                fclose($from);
                fclose($into);
            }
        } finally {
            $archive->close();
        }
    }

    /** @return array{commit: string, builtAt: string} */
    private function check(string $release): array
    {
        if (! is_file($release . '/index.html')) {
            throw new BuildProblem('The build has no home page (index.html).');
        }
        $file = $release . '/' . self::PANEL_FOLDER . '/build.json';
        $record = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($record) || ! is_string($record['commit'] ?? null)) {
            throw new BuildProblem('The build has no ' . self::PANEL_FOLDER . '/build.json. Is the site\'s workflow up to date?');
        }

        return [
            'commit' => $record['commit'],
            'builtAt' => is_string($record['builtAt'] ?? null) ? $record['builtAt'] : '',
        ];
    }

    /**
     * The build's redirects (_slim-killer/redirects.json), if it has any.
     *
     * @return list<array{from: string, to: string}>
     */
    private function redirects(string $panel): array
    {
        $file = $panel . '/redirects.json';

        return is_file($file) ? Release::redirectsFrom(json_decode((string) file_get_contents($file), true)) : [];
    }

    /**
     * Replaces the panel's content, schemas and page addresses with the ones the build carried, so the
     * forms match the live site. Left alone when .env points them at a development folder.
     */
    private function refreshWorkingCopy(string $panel): string
    {
        $content = $this->paths->storage('site/content');
        $schemas = $this->paths->storage('site/schemas');
        if ($this->location->contentDir !== $content || $this->location->schemaDir !== $schemas) {
            return 'The working copy was left alone: CONTENT_DIR or SCHEMA_DIR points at a development folder.';
        }

        try {
            if (! is_dir(dirname($content))) {
                @mkdir(dirname($content), 0755, true);
            }
            Folder::replace($content, $panel . '/content');
            Folder::replace($schemas, $panel . '/schemas');
            $addresses = dirname($content) . '/addresses.json';
            if (is_file($panel . '/addresses.json')) {
                if (! @copy($panel . '/addresses.json', $addresses)) {
                    throw new RuntimeException("Could not write {$addresses}.");
                }
            } elseif (is_file($addresses)) {
                @unlink($addresses);
            }
        } catch (RuntimeException $exception) {
            return 'The website is live, but the working copy could not be refreshed: ' . $exception->getMessage();
        }

        return 'The working copy was refreshed.';
    }

    /** For the developer reading hammer's output (the panel says it its own way). */
    private function explain(Response $response): string
    {
        return match (true) {
            $response->status === 0 => 'Could not reach GitHub' . ($response->error !== '' ? " ({$response->error})." : '.'),
            $response->status === 401, $response->status === 403 => 'GitHub refused GITHUB_TOKEN: it has expired, or it has no access to ' . $this->settings->repository . '.',
            $response->status === 404 => 'GitHub could not find ' . $this->settings->repository . ' or its build. Check GITHUB_REPOSITORY.',
            $response->status >= 500 => "GitHub is having trouble (status {$response->status}). Try again in a few minutes.",
            default => "GitHub answered with status {$response->status}.",
        };
    }

    private function request(string $endpoint): Response
    {
        return $this->http->get(self::API . '/repos/' . $this->settings->repository . $endpoint, $this->headers());
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Accept' => 'application/vnd.github+json',
            'Authorization' => 'Bearer ' . $this->settings->token,
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
    }
}
