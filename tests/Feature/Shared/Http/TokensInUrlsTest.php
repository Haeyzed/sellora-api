<?php

declare(strict_types=1);

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Identity\PlatformAdminInvitationNotification;
use App\Shared\Auth\PasswordResetLinkNotification;
use App\Shared\Http\EmailedLink;
use App\Tenant\Customers\CustomerEmailLinkNotification;
use App\Tenant\Customers\Enums\CustomerEmailTokenPurpose;
use App\Tenant\Customers\Enums\SocialProvider;
use App\Tenant\Customers\SocialLinkNotification;
use App\Tenant\Identity\StaffInvitationNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/*
 * Secrets never travel in a URL path or query string (CLAUDE.md section 10):
 * servers, proxies and browsers log those. Emailed links carry their token
 * after "#", which browsers never send anywhere, and the API takes tokens
 * only in a header or the request body.
 */

uses(LazilyRefreshDatabase::class);

it('puts the token, and any email, of every emailed link template in the URL fragment', function (): void {
    $templates = configTemplatesWith('{token}');

    // Password resets (3), staff and platform admin invitations, and the customer links (4).
    expect($templates)->toHaveCount(9);

    foreach ($templates as $key => $template) {
        $secrets = ['{token}' => 'the-one-time-token', ...(str_contains($template, '{email}') ? ['{email}' => 'ada@example.com'] : [])];
        $url = EmailedLink::build($template, $secrets, ['{domain}' => 'shop.example.com']);

        expect(emailedLinkFragment($url))->toMatchArray(['token' => 'the-one-time-token'], $key)
            ->and((string) parse_url($url, PHP_URL_PATH))->not->toContain('the-one-time-token', $key)
            ->and($url)->not->toContain('ada@example.com', $key);
    }
});

it('points the platform admin app\'s emailed links at FRONTEND_URL unless a template is set', function (): void {
    $auth = configFileWith('auth', ['FRONTEND_URL' => 'https://admin.sellora.example/', 'PLATFORM_ADMIN_PASSWORD_RESET_URL' => null, 'PLATFORM_ADMIN_INVITATION_URL' => null]);

    expect($auth['passwords']['platform_admins']['reset_url'])->toBe('https://admin.sellora.example/reset-password#token={token}&email={email}')
        ->and($auth['platform_admin_invitations']['accept_url'])->toBe('https://admin.sellora.example/accept-invitation#token={token}');
});

it('refuses to build a link whose template puts a secret before the fragment', function (string $template): void {
    expect(static fn (): string => EmailedLink::build($template, ['{token}' => 'the-one-time-token', '{email}' => 'ada@example.com']))
        ->toThrow(LogicException::class, 'URL fragment');
})->with([
    'in the query string' => 'https://shop.example.com/reset?token={token}#email={email}',
    'in the path' => 'https://shop.example.com/reset/{token}#email={email}',
    'with no fragment' => 'https://shop.example.com/reset?token={token}&email={email}',
    'the email in the query string' => 'https://shop.example.com/reset?email={email}#token={token}',
    'missing altogether' => 'https://shop.example.com/reset#email={email}',
]);

it('never sends an emailed link built from a template that puts its token in the query string', function (string $configKey, Closure $build): void {
    config([$configKey => 'https://shop.example.com/link?token={token}&email={email}']);

    expect($build)->toThrow(LogicException::class, 'URL fragment');
})->with([
    'a password reset' => ['auth.passwords.customers.reset_url', static fn (): PasswordResetLinkNotification => PasswordResetLinkNotification::forBroker('customers', 'the-one-time-token', 'ada@example.com')],
    'a staff invitation' => ['auth.staff_invitations.accept_url', static fn (): StaffInvitationNotification => StaffInvitationNotification::forToken('the-one-time-token', 'Ada')],
    'a platform admin invitation' => ['auth.platform_admin_invitations.accept_url', static fn (): PlatformAdminInvitationNotification => PlatformAdminInvitationNotification::forToken('the-one-time-token', 'Ada')],
    'a customer email link' => ['customers.email_verification.url', static fn (): CustomerEmailLinkNotification => CustomerEmailLinkNotification::forToken(CustomerEmailTokenPurpose::Verification, 'the-one-time-token')],
    'a social link confirmation' => ['customers.social_link.url', static fn (): SocialLinkNotification => SocialLinkNotification::forToken(SocialProvider::Google, 'the-one-time-token')],
]);

it('encodes the secrets it puts in the fragment, and fills in the domain as it is', function (): void {
    expect(EmailedLink::build('https://{domain}/reset#token={token}&email={email}', ['{token}' => 'a/b+c', '{email}' => 'ada+shop@example.com'], ['{domain}' => 'shop.example.com']))
        ->toBe('https://shop.example.com/reset#token=a%2Fb%2Bc&email=ada%2Bshop%40example.com');
});

it('has no route that takes a secret in its path', function (): void {
    // A promotion's {code} is a discount code shoppers share, not a secret.
    $secretParameters = ['token', 'ticket', 'state', 'secret', 'password', 'recovery_code', 'credentials'];
    $offending = collect(app('router')->getRoutes()->getRoutes())
        ->filter(static fn (Route $route): bool => array_intersect(array_map(mb_strtolower(...), $route->parameterNames()), $secretParameters) !== [])
        ->map(static fn (Route $route): string => $route->uri())
        ->values()
        ->all();

    expect($offending)->toBe([]);
});

it('refuses a password reset with its token in the query string, before reading it, and accepts the same token in the body', function (): void {
    $admin = PlatformAdmin::factory()->create(['email' => 'ada@sellora.test', 'password' => 'a-long-platform-password']);
    $token = Password::broker('platform_admins')->createToken($admin);
    $body = ['email' => 'ada@sellora.test', 'password' => 'a-brand-new-long-password', 'password_confirmation' => 'a-brand-new-long-password'];

    $this->postJson('/api/v1/platform/auth/password-resets?token='.$token, $body)
        ->assertBadRequest()
        ->assertJsonPath('code', 'secret_in_url');
    expect(Hash::check('a-long-platform-password', $admin->refresh()->password))->toBeTrue();

    $this->postJson('/api/v1/platform/auth/password-resets', [...$body, 'token' => $token])->assertNoContent();
    expect(Hash::check('a-brand-new-long-password', $admin->refresh()->password))->toBeTrue();
});

it('refuses every kind of secret in a query string, whatever its case', function (string $method, string $query): void {
    $this->json($method, centralUrl('/api/v1/geography/countries?'.$query))
        ->assertBadRequest()
        ->assertJsonPath('code', 'secret_in_url');
})->with([
    'a token' => ['GET', 'token=abc'],
    'a token in capitals' => ['GET', 'TOKEN=abc'],
    'an order token' => ['GET', 'order_token=abc'],
    'an order token under its header\'s name' => ['GET', 'X-Order-Token=abc'],
    'a password' => ['GET', 'password=abc'],
    'a recovery code' => ['GET', 'recovery_code=abc'],
    'a social sign-in ticket' => ['GET', 'ticket=abc'],
    'credentials' => ['GET', 'credentials%5Bsecret_key%5D=abc'],
    'a one-time code with a write' => ['POST', 'code=123456'],
    'a sign-in state with a write' => ['POST', 'state=abc'],
]);

it('lets the pages a provider sends the browser back to receive their code and state', function (): void {
    $this->getJson(centralUrl('/api/v1/geography/countries?code=NG&state=LA'))->assertOk();
});
