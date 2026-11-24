<?php

declare(strict_types=1);

use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\Checks\MailCheck;
use SlimKiller\Doctor\Checks\ModeCheck;
use SlimKiller\Mail\Letter;
use SlimKiller\Mail\PhpMailer;
use SlimKiller\Mail\SavedMailer;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\AppEnv;
use Tests\Fakes\FrozenClock;

it('reads APP_ENV, with everyday words for each', function (string $setting, AppEnv $env): void {
    expect(AppConfig::fromEnvironment(['APP_ENV' => $setting])->env)->toBe($env);
})->with([
    ['local', AppEnv::Local],
    ['dev', AppEnv::Local],
    ['staging', AppEnv::Staging],
    ['Test', AppEnv::Staging],
    ['production', AppEnv::Production],
    ['prod', AppEnv::Production],
]);

it('is production when APP_ENV is empty, or a word it doesn\'t know', function (): void {
    expect(AppConfig::fromEnvironment([])->env)->toBe(AppEnv::Production)
        ->and(AppConfig::fromEnvironment(['APP_ENV' => 'sandbox'])->env)->toBe(AppEnv::Production);
});

it('still reads the older settings when APP_ENV is empty', function (): void {
    $local = AppConfig::fromEnvironment(['LOCAL_WEBSITE' => '1']);
    $hidden = AppConfig::fromEnvironment(['HIDE_FROM_SEARCH' => 'true']);

    expect($local->env)->toBe(AppEnv::Local)
        ->and($local->envFromOldSettings)->toBeTrue()
        ->and($hidden->env)->toBe(AppEnv::Staging)
        ->and($hidden->hideFromSearch)->toBeTrue()
        ->and(AppConfig::fromEnvironment(['APP_ENV' => 'production', 'LOCAL_WEBSITE' => '1'])->env)->toBe(AppEnv::Production);
});

it('hides staging from search engines', function (): void {
    expect(AppConfig::fromEnvironment(['APP_ENV' => 'staging'])->hideFromSearch)->toBeTrue()
        ->and(AppConfig::fromEnvironment(['APP_ENV' => 'production'])->hideFromSearch)->toBeFalse();
});

it('weighs a doctor\'s line by where it runs: informs locally, fails in production', function (): void {
    $warn = CheckResult::warn('No backup yet', 'cron makes one each day', 'Run hammer backup.');
    $fail = CheckResult::fail('Backups have stopped');

    expect($warn->weighed(AppEnv::Local)->status->value)->toBe('info')
        ->and($warn->weighed(AppEnv::Local)->hint)->toBe('Run hammer backup.')
        ->and($fail->weighed(AppEnv::Local)->status->value)->toBe('info')
        ->and($warn->weighed(AppEnv::Staging)->status->value)->toBe('warn')
        ->and($warn->weighed(AppEnv::Production)->status->value)->toBe('fail')
        ->and(CheckResult::pass('Cron')->weighed(AppEnv::Production)->status->value)->toBe('pass');
});

it('says first where it runs, and when the older settings decide', function (): void {
    $production = (new ModeCheck(AppConfig::fromEnvironment([])))->check();
    $old = (new ModeCheck(AppConfig::fromEnvironment(['HIDE_FROM_SEARCH' => 'yes'])))->check();
    $unknown = (new ModeCheck(AppConfig::fromEnvironment(['APP_ENV' => 'sandbox'])))->check();

    expect($production)->toHaveCount(1)
        ->and($production[0]->title)->toBe('Production')
        ->and(statuses($old))->toBe(['info', 'warn'])
        ->and($old[1]->hint)->toBe('Put APP_ENV="staging" in .env, and remove the old line.')
        ->and($unknown[1]->title)->toBe('APP_ENV "sandbox" isn\'t one this knows');
});

it('saves email to a folder on a developer\'s computer, instead of sending it', function (): void {
    $folder = temporaryFolder() . '/mail';
    $mailer = new SavedMailer($folder, FrozenClock::at('2026-11-24T14:00:00+00:00'));

    $sent = $mailer->send(new Letter('owner@example.com', 'website@example.com', 'Visits on 23 November', 'Hello', '<p>Hello</p>'));
    $files = glob($folder . '/*.eml') ?: [];

    expect($sent)->toBeTrue()
        ->and(array_map(basename(...), $files))->toBe(['2026-11-24-140000-visits-on-23-november.eml'])
        ->and((string) file_get_contents($files[0]))->toContain('To: owner@example.com');
});

it('says email is saved locally, and warns about mail() only in production', function (): void {
    $local = (new MailCheck(new PhpMailer(), new AppConfig(mailFrom: 'website@example.com', env: AppEnv::Local)))->check();
    $staging = (new MailCheck(new PhpMailer(), new AppConfig(mailFrom: 'website@example.com', env: AppEnv::Staging)))->check();
    $production = (new MailCheck(new PhpMailer(), new AppConfig(mailFrom: 'website@example.com')))->check();

    expect($local[0]->title)->toBe('Email is saved, not sent')
        ->and($staging[0]->status->value)->toBe('info')
        ->and($production[0]->status->value)->toBe('warn');
});
