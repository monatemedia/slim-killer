<?php

declare(strict_types=1);

use SlimKiller\Doctor\WebProbe\WebProbe;
use SlimKiller\Doctor\WebProbe\WebTarget;
use SlimKiller\Http\Response;
use Tests\Fakes\FakeHttpClient;

/**
 * A fake website: answers the probe the way a real host would, and records what
 * existed on disk at the moment it was asked.
 */
function fakeWebsite(string $webRoot, array $probeReport, int $rewriteStatus = 200, int $listingStatus = 403): FakeHttpClient
{
    return new FakeHttpClient(function (string $url) use ($webRoot, $probeReport, $rewriteStatus, $listingStatus): Response {
        $folders = glob($webRoot . '/_slimkiller-probe-*') ?: [];
        expect($folders)->toHaveCount(1); // the probe folder exists while it is being read

        return match (true) {
            str_contains($url, '/probe.php') => new Response(200, json_encode(['probe' => 'slim-killer', ...$probeReport])),
            str_contains($url, '/rewrite-test') => new Response($rewriteStatus, $rewriteStatus === 200 ? json_encode(['probe' => 'slim-killer', 'rewrite' => true]) : ''),
            str_contains($url, '/listing/') => new Response($listingStatus, ''),
            default => new Response(404, ''),
        };
    });
}

it('reads the website PHP, checks rewrites and listings, and removes the probe', function (): void {
    $webRoot = temporaryFolder();
    $http = fakeWebsite($webRoot, ['php_version' => '8.4.27', 'sapi' => 'litespeed', 'extensions' => ['curl']]);

    $outcome = (new WebProbe($http))->probe(WebTarget::from('https://example.com/', $webRoot));

    expect($outcome->environment?->phpVersion())->toBe('8.4.27')
        ->and($outcome->environment?->sapi())->toBe('litespeed')
        ->and(statuses($outcome->results))->toBe(['pass', 'pass'])
        ->and($http->requested[0])->toStartWith('https://example.com/_slimkiller-probe-')
        ->and(glob($webRoot . '/_slimkiller-probe-*'))->toBe([]);
});

it('writes a probe that only answers to its own random key', function (): void {
    $webRoot = temporaryFolder();
    $seen = '';
    $http = new FakeHttpClient(function (string $url) use ($webRoot, &$seen): Response {
        $files = glob($webRoot . '/_slimkiller-probe-*/probe.php') ?: [];
        $seen = (string) file_get_contents($files[0]);

        return new Response(404, '');
    });

    (new WebProbe($http))->probe(WebTarget::from('https://example.com', $webRoot));

    preg_match('/key=([0-9a-f]+)/', $http->requested[0], $m);
    expect($seen)->toContain("\$key = '{$m[1]}'")
        ->and($seen)->not->toContain('__PROBE_KEY__');
});

it('fails clearly when rewrites and listing protection do not work', function (): void {
    $webRoot = temporaryFolder();
    $http = fakeWebsite($webRoot, ['php_version' => '8.4.27'], rewriteStatus: 404, listingStatus: 200);

    $outcome = (new WebProbe($http))->probe(WebTarget::from('https://example.com', $webRoot));

    expect(statuses($outcome->results))->toBe(['fail', 'fail']);
});

it('explains when the website does not run the probe at all', function (): void {
    $webRoot = temporaryFolder();
    $outcome = (new WebProbe(FakeHttpClient::always(new Response(404, ''))))->probe(WebTarget::from('https://example.com', $webRoot));

    expect($outcome->environment)->toBeNull()
        ->and($outcome->results[0]->hint)->toContain($webRoot)
        ->and(glob($webRoot . '/_slimkiller-probe-*'))->toBe([]);
});

it('refuses a web root that does not exist', function (): void {
    $outcome = (new WebProbe(FakeHttpClient::always(new Response(200, ''))))->probe(WebTarget::from('https://example.com', '/nope/not/here'));

    expect(statuses($outcome->results))->toBe(['fail']);
});
