<?php

declare(strict_types=1);

use SlimKiller\Publishing\WebhookSignature;
use SlimKiller\Support\AfterResponse;
use SlimKiller\Support\AppConfig;
use Tests\Fakes\RecordingAfterResponse;

const HOOK = '/cp/hooks/build-ready';
const SECRET = 'shared-between-github-and-the-host';

/** @return array{0: Tests\Fakes\Browser, 1: RecordingAfterResponse, 2: Tests\Fakes\SteppingClock} */
function hookedPanel(string $secret = SECRET): array
{
    $later = new RecordingAfterResponse();
    [$browser, , $clock] = installedPanel([
        AppConfig::class => new AppConfig(webhookSecret: $secret),
        AfterResponse::class => $later,
    ]);

    return [$browser, $later, $clock];
}

/** @return array<string, string> */
function signed(string $body, int $timestamp, string $secret = SECRET): array
{
    return [
        'Content-Type' => 'application/json',
        WebhookSignature::TIMESTAMP_HEADER => (string) $timestamp,
        WebhookSignature::SIGNATURE_HEADER => WebhookSignature::sign($secret, $timestamp, $body),
    ];
}

it('answers a signed call at once and fetches the build after the response', function (): void {
    [$browser, $later, $clock] = hookedPanel();
    $body = '{"build":"abc","commit":"def"}';

    $response = $browser->postRaw(HOOK, $body, signed($body, $clock->now()->getTimestamp()));

    expect($response->getStatusCode())->toBe(202)
        ->and($browser->body())->toContain('Fetching the build')
        ->and($later->work)->toHaveCount(1)
        ->and($response->getHeaderLine('Set-Cookie'))->toBe('');
});

it('refuses a call it cannot trust, and fetches nothing', function (callable $headers, string $message): void {
    [$browser, $later, $clock] = hookedPanel();
    $body = '{"build":"abc"}';

    $response = $browser->postRaw(HOOK, $body, $headers($body, $clock->now()->getTimestamp()));

    expect($response->getStatusCode())->toBe(401)
        ->and($browser->body())->toContain($message)
        ->and($later->work)->toBe([]);
})->with([
    'no signature' => [fn(string $body, int $now): array => ['Content-Type' => 'application/json'], 'no timestamp'],
    'the wrong secret' => [fn(string $body, int $now): array => signed($body, $now, 'guessed'), 'does not match'],
    'a changed body' => [fn(string $body, int $now): array => signed('{"build":"other"}', $now), 'does not match'],
    'an old call sent again' => [fn(string $body, int $now): array => signed($body, $now - 600), 'too old'],
]);

it('is not there until the secret is set', function (): void {
    [$browser, $later, $clock] = hookedPanel(secret: '');
    $body = '{}';

    $response = $browser->postRaw(HOOK, $body, signed($body, $clock->now()->getTimestamp(), ''));

    expect($response->getStatusCode())->toBe(404)->and($later->work)->toBe([]);
});
