<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\WebProbe;

use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\Environment\SnapshotEnvironment;
use SlimKiller\Http\HttpClient;
use Throwable;

/**
 * Finds out what the WEBSITE's PHP and web server do, which can differ from the
 * command line's. It places a temporary folder in the web root containing a probe
 * file (answers only to a random key), an .htaccess rewrite rule and an empty folder,
 * requests them over HTTP, and always deletes the folder again.
 */
final class WebProbe
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    public function probe(WebTarget $target): ProbeOutcome
    {
        if (! is_dir($target->webRoot) || ! is_writable($target->webRoot)) {
            return new ProbeOutcome(null, [CheckResult::fail(
                'Cannot use the web root',
                $target->webRoot . ' does not exist or is not writable',
                'Pass the folder your website is served from with --webroot, for example ~/domains/example.com/public_html',
            )]);
        }

        $name = '_slimkiller-probe-' . bin2hex(random_bytes(8));
        $folder = $target->webRoot . '/' . $name;
        $key = bin2hex(random_bytes(16));
        $base = $target->url . '/' . $name;

        try {
            $this->place($folder, $key);

            return $this->inspect($base, $key, $target);
        } catch (Throwable $exception) {
            return new ProbeOutcome(null, [CheckResult::fail('The web probe failed', $exception->getMessage())]);
        } finally {
            $this->remove($folder);
        }
    }

    private function place(string $folder, string $key): void
    {
        $template = (string) file_get_contents(__DIR__ . '/probe-template.php');

        mkdir($folder . '/listing', 0755, true);
        file_put_contents($folder . '/probe.php', str_replace('__PROBE_KEY__', $key, $template));
        file_put_contents($folder . '/listing/empty.txt', '');
        file_put_contents($folder . '/.htaccess', implode("\n", [
            '# Temporary: written and removed by `hammer doctor`.',
            'Options -Indexes',
            '<IfModule mod_rewrite.c>',
            'RewriteEngine On',
            'RewriteRule ^rewrite-test$ probe.php?rewrite=1 [L,QSA]',
            '</IfModule>',
            '',
        ]));
    }

    private function inspect(string $base, string $key, WebTarget $target): ProbeOutcome
    {
        $response = $this->http->get($base . '/probe.php?key=' . $key);
        $report = $response->json();

        if ($report === null || ! $response->ok() || ($report['probe'] ?? null) !== 'slim-killer') {
            return new ProbeOutcome(null, [CheckResult::fail(
                'The website did not run the probe',
                'HTTP ' . $response->status . ($response->error !== '' ? ', ' . $response->error : '') . ' from ' . $base . '/probe.php',
                'Check that ' . $target->url . ' is served from ' . $target->webRoot . ' and that PHP runs there.',
            )]);
        }

        $results = [];

        $rewrite = $this->http->get($base . '/rewrite-test?key=' . $key);
        $results[] = ($rewrite->json()['rewrite'] ?? false) === true
            ? CheckResult::pass('.htaccess rewrites work')
            : CheckResult::fail(
                '.htaccess rewrites do not work',
                'HTTP ' . $rewrite->status . ' from ' . $base . '/rewrite-test',
                'Slim Killer switches site releases with a rewrite rule. Ask your host to enable mod_rewrite and AllowOverride (or use LiteSpeed).',
            );

        $listing = $this->http->get($base . '/listing/');
        $results[] = in_array($listing->status, [403, 404], true)
            ? CheckResult::pass('Folder listings are blocked', 'HTTP ' . $listing->status)
            : CheckResult::fail(
                'Folder listings are visible',
                'HTTP ' . $listing->status . ' from ' . $base . '/listing/',
                'Slim Killer relies on "Options -Indexes" in .htaccess. Ask your host to allow it.',
            );

        return new ProbeOutcome(SnapshotEnvironment::fromProbe($report, 'Website'), $results);
    }

    private function remove(string $folder): void
    {
        if (! is_dir($folder)) {
            return;
        }

        foreach (['probe.php', '.htaccess', 'listing/empty.txt'] as $file) {
            @unlink($folder . '/' . $file);
        }
        @rmdir($folder . '/listing');
        @rmdir($folder);
    }
}
