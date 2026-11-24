<?php

declare(strict_types=1);

use SlimKiller\Doctor\Checks\MailCheck;
use SlimKiller\Mail\Letter;
use SlimKiller\Mail\PhpMailer;
use SlimKiller\Mail\SmtpMailer;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\AppEnv;
use Tests\Fakes\FakeSmtpConnection;
use Tests\Fakes\FrozenClock;

const SMTP_SECRET = 'correct horse battery staple';

function smtpConfig(int $port = 465): AppConfig
{
    return AppConfig::fromEnvironment([
        'MAIL_FROM' => 'website@example.com',
        'SMTP_HOST' => 'smtp.example.com',
        'SMTP_PORT' => (string) $port,
        'SMTP_PASSWORD' => SMTP_SECRET,
    ]);
}

function smtpMailer(FakeSmtpConnection $connection, int $port = 465): SmtpMailer
{
    return new SmtpMailer($connection, smtpConfig($port), FrozenClock::at('2026-11-24T02:00:00+00:00'));
}

function testLetter(string $text = 'Hello'): Letter
{
    return new Letter('owner@example.com', 'website@example.com', 'Visits on 23 November', $text, '<p>' . $text . '</p>');
}

it('signs in as MAIL_FROM and sends the letter, on port 465', function (): void {
    $server = FakeSmtpConnection::accepting();

    $sent = smtpMailer($server)->send(testLetter());

    expect($sent)->toBeTrue()
        ->and($server->opened)->toBe('smtp.example.com:465')
        ->and($server->tls)->toBeFalse()
        ->and(array_slice($server->written, 0, 7))->toBe([
            'EHLO example.com',
            'AUTH LOGIN',
            base64_encode('website@example.com'),
            base64_encode(SMTP_SECRET),
            'MAIL FROM:<website@example.com>',
            'RCPT TO:<owner@example.com>',
            'DATA',
        ])
        ->and($server->written)->toContain('To: owner@example.com')
        ->and($server->written)->toContain('Subject: =?UTF-8?B?' . base64_encode('Visits on 23 November') . '?=')
        ->and($server->written)->toContain('Date: Tue, 24 Nov 2026 02:00:00 +0000')
        ->and(array_slice($server->written, -2))->toBe(['.', 'QUIT'])
        ->and($server->closed)->toBeTrue();
});

it('starts encryption with STARTTLS on port 587', function (): void {
    $server = new FakeSmtpConnection([
        '220 ready',
        '250 hello',
        '220 go ahead',
        '250 hello again',
        '334 user',
        '334 pass',
        '235 ok',
        '250 ok', '250 ok', '354 go', '250 queued', '221 bye',
    ]);

    expect(smtpMailer($server, 587)->send(testLetter()))->toBeTrue()
        ->and($server->tls)->toBeTrue()
        ->and(array_slice($server->written, 0, 3))->toBe(['EHLO example.com', 'STARTTLS', 'EHLO example.com']);
});

it('says a refused sign-in in plain words, never with the password', function (): void {
    $server = new FakeSmtpConnection(['220 ready', '250 hello', '334 user', '334 pass', '535 5.7.8 Authentication failed']);
    $mailer = smtpMailer($server);

    expect($mailer->send(testLetter()))->toBeFalse()
        ->and($mailer->lastProblem())->toBe('smtp.example.com did not accept the sign-in for website@example.com (535 5.7.8 Authentication failed). Check SMTP_USER and SMTP_PASSWORD.')
        ->and($mailer->lastProblem())->not->toContain(SMTP_SECRET)
        ->and($server->written)->not->toContain('MAIL FROM:<website@example.com>')
        ->and($server->closed)->toBeTrue();
});

it('says when the mail server cannot be reached', function (): void {
    $mailer = smtpMailer(new FakeSmtpConnection([], reachable: false));

    expect($mailer->send(testLetter()))->toBeFalse()
        ->and($mailer->lastProblem())->toBe('Could not reach smtp.example.com on port 465: Connection refused.');
});

it('doubles a dot at the start of a line, so the server does not take it as the end', function (): void {
    $server = FakeSmtpConnection::accepting();

    smtpMailer($server)->send(new Letter('owner@example.com', 'website@example.com', 'Dots', "line one\n.\nline three"));
    $data = array_slice($server->written, array_search('DATA', $server->written, true) + 1, -2);

    expect($data)->each->not->toBe('.')
        ->and(base64_decode(implode('', array_slice($data, array_search('', $data, true) + 1))))->toBe("line one\n.\nline three");
});

it('tells the doctor how email leaves the website', function (): void {
    $through = (new MailCheck(smtpMailer(new FakeSmtpConnection(['220 ready', '250 hello', '334 u', '334 p', '235 ok', '221 bye'])), smtpConfig()))->check();
    $refused = (new MailCheck(smtpMailer(new FakeSmtpConnection(['220 ready', '250 hello', '334 u', '334 p', '535 no'])), smtpConfig()))->check();
    $host = (new MailCheck(new PhpMailer(), new AppConfig(mailFrom: 'website@example.com', env: AppEnv::Staging)))->check();

    expect($through[0]->status->value)->toBe('pass')
        ->and($through[0]->title)->toBe('Email goes through smtp.example.com')
        ->and($through[0]->detail)->toBe('signed in as website@example.com')
        ->and($refused[0]->status->value)->toBe('fail')
        ->and($host[0]->status->value)->toBe('info')
        ->and($host[0]->detail)->toContain('fails DMARC')
        ->and((new MailCheck(new PhpMailer(), new AppConfig()))->check())->toBe([]);
});
