<?php

declare(strict_types=1);

use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\Checks\PhpVersionCheck;
use SlimKiller\Doctor\Doctor;
use SlimKiller\Doctor\HostCheck;
use SlimKiller\Doctor\Status;
use SlimKiller\Doctor\WebProbe\WebProbe;
use SlimKiller\Doctor\WebProbe\WebTarget;
use SlimKiller\Http\Response;
use Tests\Fakes\FakeHttpClient;

function hostCheck(CheckResult ...$results): HostCheck
{
    return new class ($results) implements HostCheck {
        public function __construct(private array $results) {}

        public function check(): array
        {
            return $this->results;
        }
    };
}

it('checks the command line and the server, and says the website was not checked', function (): void {
    $doctor = new Doctor(
        environment(),
        new WebProbe(FakeHttpClient::always(new Response(500, ''))),
        [new PhpVersionCheck()],
        [hostCheck(CheckResult::pass('Storage'))],
    );

    $report = $doctor->examine();

    expect(array_map(fn($s) => explode(' (', $s->title)[0], $report->sections))->toBe(['Command line', 'Server', 'Website'])
        ->and($report->count(Status::Warn))->toBe(1)
        ->and($report->passed())->toBeTrue();
});

it('runs the same PHP checks against the website, so an old website PHP fails', function (): void {
    $webRoot = temporaryFolder();
    $http = new FakeHttpClient(fn(string $url): Response => match (true) {
        str_contains($url, '/probe.php') => new Response(200, json_encode(['probe' => 'slim-killer', 'php_version' => '8.2.0', 'sapi' => 'litespeed'])),
        str_contains($url, '/rewrite-test') => new Response(200, json_encode(['rewrite' => true])),
        default => new Response(403, ''),
    });

    $doctor = new Doctor(environment(), new WebProbe($http), [new PhpVersionCheck()], []);
    $report = $doctor->examine(WebTarget::from('https://example.com', $webRoot));

    expect($report->sections[2]->title)->toContain('PHP 8.2.0')
        ->and($report->count(Status::Fail))->toBe(1)
        ->and($report->passed())->toBeFalse();
});
