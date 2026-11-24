<?php

declare(strict_types=1);

use SlimKiller\Console\Commands\DoctorCommand;
use SlimKiller\Console\Commands\SetupLinkCommand;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Doctor\Checks\PhpVersionCheck;
use SlimKiller\Doctor\Doctor;
use SlimKiller\Doctor\ReportStore;
use SlimKiller\Doctor\WebProbe\WebProbe;
use SlimKiller\Http\Response;
use SlimKiller\Setup\SetupLink;
use SlimKiller\Support\AppConfig;
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
    );

    $stream = fopen('php://memory', 'w+');
    $code = $command->run(Input::fromArgv(['hammer', 'doctor', ...$argv]), new Output($stream, false));
    rewind($stream);

    return [$code, (string) stream_get_contents($stream)];
}

/** @return array{0: int, 1: string} */
function runSetupLink(Paths $paths, array $argv = [], ?AppConfig $config = null): array
{
    $clock = FrozenClock::at('2026-11-09 12:00:00');
    $command = new SetupLinkCommand(new SetupLink($paths, $clock), new ReportStore($paths, $clock), $config ?? new AppConfig());

    $stream = fopen('php://memory', 'w+');
    $code = $command->run(Input::fromArgv(['hammer', 'setup:link', ...$argv]), new Output($stream, false));
    rewind($stream);

    return [$code, (string) stream_get_contents($stream)];
}

it('saves the report and points to setup:link, without handing out a link itself', function (): void {
    $paths = new Paths(temporaryFolder());
    [$code, $output] = runDoctor($paths, []);
    $clock = FrozenClock::at('2026-11-09 12:00:00');

    expect($code)->toBe(0)
        ->and($output)->not->toContain('token=')
        ->and($output)->toContain('To see this report in a browser: hammer setup:link')
        ->and($output)->toContain('1 passed, 1 warning, 0 failed')
        ->and($paths->storage(SetupLink::FILE))->not->toBeFile()
        ->and((new ReportStore($paths, $clock))->latest()?->report->sections)->toHaveCount(3);
});

it('hands out a private link to the setup page with setup:link', function (): void {
    $paths = new Paths(temporaryFolder());
    runDoctor($paths, []);
    [$code, $output] = runSetupLink($paths, [], new AppConfig(url: 'https://example.com'));

    preg_match('#https://example\.com/cp/setup\?token=([0-9a-f]{32})#', $output, $match);

    expect($code)->toBe(0)
        ->and($match)->toHaveCount(2)
        ->and((new SetupLink($paths, FrozenClock::at('2026-11-09 12:00:00')))->accepts($match[1] ?? ''))->toBeTrue()
        ->and($output)->not->toContain('hasn\'t run yet');
});

it('says to run the doctor first when there is no report for the setup page', function (): void {
    [, $output] = runSetupLink(new Paths(temporaryFolder()), ['--url=https://example.com/']);

    expect($output)->toContain('https://example.com/cp/setup?token=')
        ->and($output)->toContain('Run hammer doctor first');
});
