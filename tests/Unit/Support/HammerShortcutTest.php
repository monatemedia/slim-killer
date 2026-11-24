<?php

declare(strict_types=1);

use SlimKiller\Console\Commands\ShortcutCommand;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Doctor\Checks\ShortcutCheck;
use SlimKiller\Doctor\Status;
use SlimKiller\Support\Hammer;
use SlimKiller\Support\HammerShortcut;
use SlimKiller\Support\Paths;

const SHORTCUT_PHP = '/opt/alt/php84/usr/bin/php';

afterEach(function (): void {
    Hammer::useShortcut(false);
    Hammer::usePhp('php');
});

it('writes ~/bin/hammer to run this Slim Killer with this PHP, and puts ~/bin on the PATH once', function (): void {
    $home = temporaryFolder();
    file_put_contents($home . '/.bashrc', "# the account's own settings\nalias ll='ls -l'");
    $shortcut = new HammerShortcut($home, '/home/u1/slim-killer');

    $first = $shortcut->install(SHORTCUT_PHP);
    $again = $shortcut->install(SHORTCUT_PHP);
    $script = (string) file_get_contents($home . '/bin/hammer');
    $bashrc = (string) file_get_contents($home . '/.bashrc');

    expect($first)->toHaveCount(3)
        ->and($again)->toBe([])
        ->and($script)->toStartWith("#!/bin/sh\n")
        ->and($script)->toContain("exec '/opt/alt/php84/usr/bin/php' '/home/u1/slim-killer/hammer' \"\$@\"")
        ->and($bashrc)->toStartWith("# the account's own settings\nalias ll='ls -l'\n")
        ->and(substr_count($bashrc, HammerShortcut::PATH_LINE))->toBe(1)
        ->and(substr_count((string) file_get_contents($home . '/.profile'), HammerShortcut::PATH_LINE))->toBe(1)
        ->and($shortcut->installed())->toBeTrue()
        ->and($shortcut->foundAtSignIn())->toBeTrue();
    if (DIRECTORY_SEPARATOR === '/') {
        expect(fileperms($home . '/bin/hammer') & 0777)->toBe(0755);
    }
});

it('writes the shortcut again when the PHP changes, without touching the PATH line', function (): void {
    $home = temporaryFolder();
    $shortcut = new HammerShortcut($home, '/home/u1/slim-killer');
    $shortcut->install('/opt/alt/php84/usr/bin/php');

    $changed = $shortcut->install('/opt/alt/php85/usr/bin/php');

    expect($changed)->toBe(["Wrote {$home}/bin/hammer, which runs /home/u1/slim-killer/hammer with /opt/alt/php85/usr/bin/php."])
        ->and((string) file_get_contents($home . '/bin/hammer'))->toContain('php85');
});

it('quotes a path with a quote in it the way sh needs', function (): void {
    $home = temporaryFolder();
    (new HammerShortcut($home, "/home/o'brien/slim-killer"))->install(SHORTCUT_PHP);

    expect((string) file_get_contents($home . '/bin/hammer'))->toContain("'/home/o'\\''brien/slim-killer/hammer'");
});

it('is not this Slim Killer\'s shortcut when it runs another one', function (): void {
    $home = temporaryFolder();
    (new HammerShortcut($home, '/home/u1/other-site'))->install(SHORTCUT_PHP);

    expect((new HammerShortcut($home, '/home/u1/slim-killer'))->installed())->toBeFalse();
});

it('makes hints say just "hammer" once the shortcut is set up', function (): void {
    Hammer::usePhp(SHORTCUT_PHP);
    $before = Hammer::command('backup:connect');
    Hammer::useShortcut(true);

    expect($before)->toBe('/opt/alt/php84/usr/bin/php hammer backup:connect')
        ->and(Hammer::command('backup:connect'))->toBe('hammer backup:connect');
});

it('says what it changed, and what to do next', function (): void {
    $home = temporaryFolder();
    $previous = getenv('HOME');
    putenv('HOME=' . $home);
    $stream = fopen('php://memory', 'w+');

    try {
        $code = (new ShortcutCommand(new Paths('/home/u1/slim-killer'), SHORTCUT_PHP))->run(new Input('shortcut'), new Output($stream, false));
    } finally {
        putenv($previous === false ? 'HOME' : 'HOME=' . $previous);
    }
    rewind($stream);

    expect($code)->toBe(0)
        ->and(stream_get_contents($stream))->toContain('Start a new SSH session (or run: source ~/.bashrc), then: hammer doctor')
        ->and($home . '/bin/hammer')->toBeFile();
});

it('puts ~/bin on the PATH in ~/.profile when a host signs in without ~/.bashrc', function (): void {
    $home = temporaryFolder();
    file_put_contents($home . '/.bashrc', HammerShortcut::PATH_LINE . "\n");
    file_put_contents($home . '/.profile', "# the host's own settings\nexport LANG=C.UTF-8\n");
    $shortcut = new HammerShortcut($home, '/home/u1/slim-killer');
    $shortcut->install(SHORTCUT_PHP);
    file_put_contents($home . '/.profile', "# the host's own settings\nexport LANG=C.UTF-8\n");

    expect($shortcut->installed())->toBeTrue()
        ->and($shortcut->foundAtSignIn())->toBeFalse()
        ->and($shortcut->install(SHORTCUT_PHP))->toBe(["Added ~/bin to the PATH in {$home}/.profile."])
        ->and((string) file_get_contents($home . '/.profile'))->toStartWith("# the host's own settings\nexport LANG=C.UTF-8\n")
        ->and($shortcut->foundAtSignIn())->toBeTrue();
});

it('uses ~/.bash_profile when there is one, and leaves it alone when it loads ~/.bashrc', function (): void {
    $home = temporaryFolder();
    file_put_contents($home . '/.profile', "# not read: bash reads .bash_profile first\n");
    file_put_contents($home . '/.bash_profile', "if [ -f ~/.bashrc ]; then\n    . ~/.bashrc\nfi\n");
    $shortcut = new HammerShortcut($home, '/home/u1/slim-killer');

    $changed = $shortcut->install(SHORTCUT_PHP);

    expect($shortcut->signInFile())->toBe($home . '/.bash_profile')
        ->and($changed)->toHaveCount(2)
        ->and((string) file_get_contents($home . '/.bash_profile'))->not->toContain(HammerShortcut::PATH_LINE)
        ->and((string) file_get_contents($home . '/.profile'))->not->toContain(HammerShortcut::PATH_LINE)
        ->and($shortcut->foundAtSignIn())->toBeTrue();
});

it('tells the doctor to run the shortcut again when signing in does not find it', function (): void {
    $home = temporaryFolder();
    $previous = getenv('HOME');
    putenv('HOME=' . $home);

    try {
        $shortcut = new HammerShortcut($home, '/home/u1/slim-killer');
        $shortcut->install(SHORTCUT_PHP);
        file_put_contents($home . '/.profile', '');
        $results = (new ShortcutCheck(new Paths('/home/u1/slim-killer')))->check();
    } finally {
        putenv($previous === false ? 'HOME' : 'HOME=' . $previous);
    }

    expect($results[0]->status)->toBe(Status::Info)
        ->and($results[0]->title)->toBe('The hammer shortcut is not found when you sign in')
        ->and($results[0]->detail)->toContain($home . '/.profile does not put ~/bin on the PATH: run ')
        ->and(HammerShortcut::installedFor('/home/u1/slim-killer'))->toBeFalse();
});
