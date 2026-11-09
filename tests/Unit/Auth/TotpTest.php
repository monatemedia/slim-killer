<?php

declare(strict_types=1);

use SlimKiller\Auth\Base32;
use SlimKiller\Auth\Totp;
use Tests\Fakes\FrozenClock;
use Tests\Fakes\SteppingClock;

// RFC 6238, appendix B: the SHA-1 secret "12345678901234567890", last six digits.
const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

it('gives the codes from the RFC 6238 test vectors', function (int $time, string $code): void {
    expect((new Totp(FrozenClock::at('@0')))->codeAt(RFC_SECRET, $time))->toBe($code);
})->with([
    [59, '287082'],
    [1111111109, '081804'],
    [1234567890, '005924'],
    [2000000000, '279037'],
]);

it('accepts the current code, and the ones either side for a slow phone', function (): void {
    $clock = new SteppingClock('@1234567890');
    $totp = new Totp($clock);

    expect($totp->match(RFC_SECRET, '005924'))->toBe(41152263)
        ->and($totp->match(RFC_SECRET, $totp->codeAt(RFC_SECRET, 1234567890 - 30)))->not->toBeNull()
        ->and($totp->match(RFC_SECRET, $totp->codeAt(RFC_SECRET, 1234567890 + 30)))->not->toBeNull()
        ->and($totp->match(RFC_SECRET, $totp->codeAt(RFC_SECRET, 1234567890 - 90)))->toBeNull();
});

it('refuses a code from a step that was already used', function (): void {
    $totp = new Totp(FrozenClock::at('@1234567890'));
    $step = $totp->match(RFC_SECRET, '005924');

    expect($totp->match(RFC_SECRET, '005924', $step))->toBeNull();
});

it('ignores spaces in a typed code, and refuses anything that is not six digits', function (): void {
    $totp = new Totp(FrozenClock::at('@1234567890'));

    expect($totp->match(RFC_SECRET, '005 924'))->not->toBeNull()
        ->and($totp->match(RFC_SECRET, '05924'))->toBeNull()
        ->and($totp->match(RFC_SECRET, 'abcdef'))->toBeNull();
});

it('round-trips base32, as authenticator apps write it', function (): void {
    $bytes = random_bytes(20);

    expect(Base32::decode(Base32::encode($bytes)))->toBe($bytes)
        ->and(Base32::encode('12345678901234567890'))->toBe(RFC_SECRET)
        ->and(Base32::decode(strtolower(implode(' ', str_split(RFC_SECRET, 4)))))->toBe('12345678901234567890');
});

it('builds the address the QR code holds', function (): void {
    expect(Totp::uri('ABCD', 'edward@example.com', 'Slim Killer'))
        ->toBe('otpauth://totp/Slim%20Killer:edward%40example.com?secret=ABCD&issuer=Slim%20Killer&algorithm=SHA1&digits=6&period=30');
});
