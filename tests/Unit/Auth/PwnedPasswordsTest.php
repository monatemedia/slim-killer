<?php

declare(strict_types=1);

use SlimKiller\Auth\PwnedPasswords;
use SlimKiller\Http\Response;
use Tests\Fakes\FakeHttpClient;

// SHA-1 of "password" is 5BAA61E4C9B93F3F0682250B6CF8331B7EE68FD8: the range is "5BAA6".
const PWNED_RANGE = "003D68EB55068C33ACE09247EE4C639306B:3\r\n"
    . "1E4C9B93F3F0682250B6CF8331B7EE68FD8:9659365\r\n"
    . "01330C689E5D64F660D6947A93AD634EF8F:0";

it('sends only the first five characters of the hash, never the password', function (): void {
    $http = FakeHttpClient::always(new Response(200, PWNED_RANGE));

    (new PwnedPasswords($http))->timesSeen('password');

    expect($http->requested)->toBe([PwnedPasswords::RANGE_URL . '5BAA6']);
});

it('says how many times a breached password was seen', function (): void {
    $seen = (new PwnedPasswords(FakeHttpClient::always(new Response(200, PWNED_RANGE))))->timesSeen('password');

    expect($seen)->toBe(9659365);
});

it('says 0 for a password not in the answer, padding included', function (): void {
    $seen = (new PwnedPasswords(FakeHttpClient::always(new Response(200, PWNED_RANGE))))->timesSeen('a password nobody has ever used, surely');

    expect($seen)->toBe(0);
});

it('says it could not check when the service is down or unreachable', function (Response $response): void {
    expect((new PwnedPasswords(FakeHttpClient::always($response)))->timesSeen('password'))->toBeNull();
})->with([
    'server error' => [new Response(503, '')],
    'no connection' => [new Response(0, '', 'Could not resolve host')],
]);
