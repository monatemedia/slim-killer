<?php

declare(strict_types=1);

use SlimKiller\Doctor\Checks\DisabledFunctionsCheck;
use SlimKiller\Doctor\Checks\ExtensionsCheck;
use SlimKiller\Doctor\Checks\ImageSupportCheck;
use SlimKiller\Doctor\Checks\LimitsCheck;
use SlimKiller\Doctor\Checks\PhpVersionCheck;
use SlimKiller\Doctor\Requirements;

// --- PHP version -------------------------------------------------------------------

it('passes PHP 8.4', function (): void {
    expect(statuses((new PhpVersionCheck())->check(environment())))->toBe(['pass']);
});

it('fails PHP 8.3 and tells the command line to use another binary', function (): void {
    [$result] = (new PhpVersionCheck())->check(environment(['php_version' => '8.3.36']));

    expect($result->status->value)->toBe('fail')
        ->and($result->hint)->toContain('full path');
});

it('fails an old website PHP and points to the hosting control panel', function (): void {
    [$result] = (new PhpVersionCheck())->check(environment(['php_version' => '8.2.1', 'sapi' => 'litespeed'], 'Website'));

    expect($result->status->value)->toBe('fail')
        ->and($result->hint)->toContain('control panel');
});

// --- Extensions --------------------------------------------------------------------

it('passes when every extension is there', function (): void {
    expect(statuses((new ExtensionsCheck())->check(environment())))->toBe(['pass']);
});

it('fails each missing required extension by name', function (): void {
    $extensions = array_diff(array_keys(Requirements::REQUIRED_EXTENSIONS), ['zip', 'gd']);
    $results = (new ExtensionsCheck())->check(environment(['extensions' => [...$extensions, 'sodium', 'intl']]));

    $failed = array_values(array_filter($results, fn($r) => $r->status->value === 'fail'));

    expect($failed)->toHaveCount(2)
        ->and($failed[0]->title)->toContain('zip')
        ->and($failed[1]->title)->toContain('gd');
});

it('only warns when sodium is missing, naming the alternative', function (): void {
    $results = (new ExtensionsCheck())->check(environment([
        'extensions' => [...array_keys(Requirements::REQUIRED_EXTENSIONS), 'intl'],
    ]));

    expect(statuses($results))->toBe(['pass', 'warn'])
        ->and($results[1]->detail)->toContain('openssl');
});

// --- Disabled functions ------------------------------------------------------------

it('does not mind exec and friends being disabled', function (): void {
    $results = (new DisabledFunctionsCheck())->check(environment([
        'disabled_functions' => ['exec', 'shell_exec', 'proc_open', 'symlink'],
    ]));

    expect(statuses($results))->toBe(['pass', 'info']);
});

it('fails when a function Slim Killer needs is disabled', function (): void {
    [$result] = (new DisabledFunctionsCheck())->check(environment(['disabled_functions' => ['curl_exec']]));

    expect($result->status->value)->toBe('fail')->and($result->detail)->toBe('curl_exec');
});

// --- Limits ------------------------------------------------------------------------

it('accepts an unlimited command line', function (): void {
    expect(statuses((new LimitsCheck())->check(environment())))->toBe(['pass', 'pass']);
});

it('checks upload size only on the website', function (): void {
    $website = environment(['sapi' => 'litespeed', 'ini' => [
        'memory_limit' => '512M',
        'max_execution_time' => '300',
        'upload_max_filesize' => '2M',
        'post_max_size' => '8M',
    ]], 'Website');

    $results = (new LimitsCheck())->check($website);

    expect(statuses($results))->toBe(['pass', 'pass', 'warn'])
        ->and($results[2]->detail)->toBe('2 MB, recommended 10 MB');
});

it('warns about a short time limit', function (): void {
    $results = (new LimitsCheck())->check(environment(['ini' => [
        'memory_limit' => '64M',
        'max_execution_time' => '10',
    ]]));

    expect(statuses($results))->toBe(['warn', 'warn']);
});

// --- Images ------------------------------------------------------------------------

it('warns, with the JPEG fallback, when WebP cannot be written', function (): void {
    [$result] = (new ImageSupportCheck())->check(environment(['webp_write' => false]));

    expect($result->status->value)->toBe('warn')->and($result->detail)->toContain('JPEG');
});

it('mentions AVIF when it is there', function (): void {
    expect(statuses((new ImageSupportCheck())->check(environment(['avif' => true]))))->toBe(['pass', 'info']);
});
