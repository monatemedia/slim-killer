<?php

declare(strict_types=1);

use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\Role;
use SlimKiller\Auth\UserRepository;
use Tests\Fakes\Browser;

/** Adds someone on the People page. */
function addPerson(Browser $browser, string $name, string $email, string $role = 'owner'): int
{
    $browser->get('/cp/people');

    return $browser->post('/cp/people', ['name' => $name, 'email' => $email, 'role' => $role])->getStatusCode();
}

it('lists everyone and adds someone, who gets a welcome link by email and on screen', function (): void {
    [$browser, $container, $clock, $mailer] = alertingPanel();
    enrol($browser, $container, $clock);

    expect($browser->get('/cp/')->getStatusCode())->toBe(200)
        ->and($browser->body())->toContain('href="/cp/people"');
    $browser->get('/cp/people');
    expect($browser->body())->toContain('Edward (you)')->toContain('Administrator');

    expect(addPerson($browser, 'Thandi', 'Thandi@Example.com'))->toBe(200)
        ->and($browser->body())->toContain('Thandi is added')
        ->and($browser->body())->toContain('/cp/welcome?token=')
        ->and($mailer->sent)->toHaveCount(1)
        ->and($mailer->sent[0]->to)->toBe('thandi@example.com')
        ->and($mailer->sent[0]->subject)->toBe('Acme Tours: your account on the control panel')
        ->and($mailer->sent[0]->text)->toContain('Edward added you');

    preg_match('#(/cp/welcome\?token=[a-f0-9]+)#', $mailer->sent[0]->text, $match);
    expect($browser->another(Browser::WINDOWS_CHROME)->get($match[1])->getHeaderLine('Location'))->toBe('/cp/welcome');

    $browser->get('/cp/people');
    expect($browser->body())->toContain('Thandi')->toContain('Welcome link sent, not opened yet');
    expect($container->get(UserRepository::class)->byEmail('thandi@example.com')?->role)->toBe(Role::Owner);
});

it('says what is wrong with someone to add', function (string $name, string $email, string $message): void {
    [$browser, $container, $clock] = alertingPanel();
    enrol($browser, $container, $clock);

    expect(addPerson($browser, $name, $email))->toBe(422)
        ->and($browser->body())->toContain($message);
})->with([
    ['', 'thandi@example.com', 'Type the person&#039;s name.'],
    ['Thandi', 'not an address', 'That isn&#039;t an email address.'],
    ['Thandi', '', 'Type the person&#039;s email address.'],
    ['Edward again', 'edward@example.com', 'Someone here already has that email address.'],
]);

it('resets someone, after asking, and sends them a new welcome link', function (): void {
    [$browser, $container, $clock, $mailer] = alertingPanel();
    enrol($browser, $container, $clock);
    addPerson($browser, 'Thandi', 'thandi@example.com');
    $thandi = $container->get(UserRepository::class)->byEmail('thandi@example.com');

    expect($browser->get("/cp/people/{$thandi->id}/reset")->getStatusCode())->toBe(200)
        ->and($browser->body())->toContain('Reset Thandi\'s account?');
    expect($browser->post("/cp/people/{$thandi->id}/reset", [])->getStatusCode())->toBe(200)
        ->and($browser->body())->toContain('Thandi&#039;s account is reset')
        ->and($mailer->sent[1]->subject)->toBe('Acme Tours: your account was reset')
        ->and($container->get(AuditLog::class)->recentEvents())->toContain('user_reset');
});

it('removes someone, after asking', function (): void {
    [$browser, $container, $clock] = alertingPanel();
    enrol($browser, $container, $clock);
    addPerson($browser, 'Thandi', 'thandi@example.com');
    $thandi = $container->get(UserRepository::class)->byEmail('thandi@example.com');

    $browser->get("/cp/people/{$thandi->id}/remove");
    expect($browser->body())->toContain('Remove Thandi?');
    $done = $browser->post("/cp/people/{$thandi->id}/remove", []);

    expect($done->getHeaderLine('Location'))->toBe('/cp/people?removed=Thandi')
        ->and($container->get(UserRepository::class)->byEmail('thandi@example.com'))->toBeNull()
        ->and($container->get(AuditLog::class)->recentEvents())->toContain('user_removed');
    $browser->get('/cp/people?removed=Thandi');
    expect($browser->body())->toContain('Thandi can no longer sign in.');
});

it('lets a site owner manage site owners, but not administrators or themself', function (): void {
    [$browser, $container, $clock] = alertingPanel();
    $users = $container->get(UserRepository::class);
    // Edward the administrator exists; Lerato the site owner signs in.
    welcomeLink($container);
    $edward = $users->byEmail('edward@example.com');
    enrol($browser, $container, $clock, 'lerato@example.com', 'Lerato', Role::Owner);
    $lerato = $users->byEmail('lerato@example.com');

    $browser->get('/cp/people');
    expect($browser->body())->not->toContain('value="admin"')
        ->and($browser->body())->not->toContain("/cp/people/{$edward->id}/reset")
        ->and($browser->body())->toContain('Your password');

    expect($browser->get("/cp/people/{$edward->id}/remove")->getStatusCode())->toBe(403);
    expect($browser->get("/cp/people/{$lerato->id}/reset")->getStatusCode())->toBe(403)
        ->and($browser->body())->toContain('Your own password is on Preferences');

    // A crafted form can't make an administrator either.
    expect(addPerson($browser, 'Sipho', 'sipho@example.com', 'admin'))->toBe(422)
        ->and($browser->body())->toContain('Only an administrator can add an administrator.');

    expect(addPerson($browser, 'Sipho', 'sipho@example.com'))->toBe(200);
    $sipho = $users->byEmail('sipho@example.com');
    $browser->get('/cp/people');
    expect($browser->body())->toContain("/cp/people/{$sipho->id}/reset");
});

it('keeps the People page for people who are signed in', function (): void {
    [$browser, $container] = alertingPanel();
    welcomeLink($container);

    expect($browser->get('/cp/people')->getHeaderLine('Location'))->toBe('/cp/sign-in')
        ->and($browser->get('/cp/people/1/remove')->getHeaderLine('Location'))->toBe('/cp/sign-in');
});
