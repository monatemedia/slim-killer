<?php

declare(strict_types=1);

use SlimKiller\Console\Commands\DoctorCommand;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Doctor\Checks\PhpVersionCheck;
use SlimKiller\Doctor\Doctor;
use SlimKiller\Doctor\ReportStore;
use SlimKiller\Doctor\WebProbe\WebProbe;
use SlimKiller\Http\Response;
use SlimKiller\Setup\SetupLink;
use SlimKiller\Support\Paths;
use Tests\Fakes\FakeHttpClient;
use Tests\Fakes\FrozenClock;

/** @return array{0: int, 1: string} */
function runDoctor(Paths $paths, array $argv): array
{
    $clock = FrozenClock::at('2026-11-09 12:00:00');
    $command = new DoctorCommand(
        new Doctor(environment(), new WebProbe(FakeHttpClient::always(new Response(500, ''))), [new PhpVersionCheck()], []),
        new ReportStore($paths, $clock),
        new SetupLink($paths, $clock),
    );

    $stream = fopen('php://memory', 'w+');
    $code = $command->run(Input::fromArgv(['hammer', 'doctor', ...$argv]), new Output($stream, false));
    rewind($stream);

    return [$code, (string) stream_get_contents($stream)];
}

it('saves the report and ends with a private link to the setup page', function (): void {
    $paths = new Paths(temporaryFolder());
    [$code, $output] = runDoctor($paths, []);

    preg_match('#/cp/setup\?token=([0-9a-f]{32})#', $output, $match);
    $clock = FrozenClock::at('2026-11-09 12:00:00');

    expect($code)->toBe(0)
        ->and($match)->toHaveCount(2)
        ->and((new SetupLink($paths, $clock))->accepts($match[1] ?? ''))->toBeTrue()
        ->and((new ReportStore($paths, $clock))->latest()?->report->sections)->toHaveCount(3);
});

it('gives the full address of the setup page when it knows the website', function (): void {
    [, $output] = runDoctor(new Paths(temporaryFolder()), ['--url=https://example.com/', '--webroot=' . temporaryFolder()]);

    expect($output)->toContain('https://example.com/cp/setup?token=');
});
