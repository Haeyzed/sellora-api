<?php

declare(strict_types=1);

use App\Shared\Auth\PasswordResetLinkNotification;
use App\Shared\Features\Contracts\FeatureSource;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Support\Facades\Notification;
use Tests\Fixtures\Features\FakeFeatureSource;

/*
 * Step 20a: a staff member's preferred language, for what the store sends
 * them outside a request (alerts from the queue or the scheduler). They
 * choose one of the languages the dashboards are translated into, or null
 * for the store's default language; their profile shows it, and messages sent
 * to them use it.
 */

beforeEach(function (): void {
    seedWorld();
    app()->instance(FeatureSource::class, new FakeFeatureSource);
    $this->store = createStore('language-store');
    $this->staffMember = $this->store->run(static fn (): StaffMember => StaffMember::factory()->create());
    $this->token = staffTokenFor($this->staffMember, 'language-store');
});

afterEach(function (): void {
    deleteAllStores();
});

it('lets a staff member choose their language, shows it on their profile, and forgets it on null', function (): void {
    staffApiRequest('language-store', 'PUT', 'language', ['locale' => 'pt'], $this->token)->assertNoContent();
    staffApiRequest('language-store', 'GET', 'me', token: $this->token)->assertOk()->assertJsonPath('data.locale', 'pt');

    staffApiRequest('language-store', 'PUT', 'language', ['locale' => null], $this->token)->assertNoContent();
    staffApiRequest('language-store', 'GET', 'me', token: $this->token)->assertOk()->assertJsonPath('data.locale', null);
});

it('refuses a language the dashboards are not translated into, an empty request, and anyone signed out', function (): void {
    staffApiRequest('language-store', 'PUT', 'language', ['locale' => 'xx'], $this->token)->assertUnprocessable()->assertJsonValidationErrors('locale');
    staffApiRequest('language-store', 'PUT', 'language', [], $this->token)->assertUnprocessable()->assertJsonValidationErrors('locale');
    staffApiRequest('language-store', 'PUT', 'language', ['locale' => 'fr'])->assertUnauthorized();

    expect($this->store->run(fn (): ?string => $this->staffMember->fresh()?->locale))->toBeNull();
});

it('writes what is sent to them in the language they chose, and in the request\'s language until they choose one', function (): void {
    Notification::fake();
    $this->store->run(fn () => $this->staffMember->sendPasswordResetNotification('token'));
    staffApiRequest('language-store', 'PUT', 'language', ['locale' => 'fr'], $this->token)->assertNoContent();
    $this->store->run(fn () => $this->staffMember->fresh()?->sendPasswordResetNotification('token'));

    $locales = [];
    Notification::assertSentTo($this->staffMember, PasswordResetLinkNotification::class, static function (PasswordResetLinkNotification $notification, array $channels, StaffMember $notifiable, ?string $locale) use (&$locales): bool {
        $locales[] = $locale;

        return true;
    });

    // Null: written in the language current when it is sent, as before they chose.
    expect($locales)->toBe([null, 'fr']);
});
