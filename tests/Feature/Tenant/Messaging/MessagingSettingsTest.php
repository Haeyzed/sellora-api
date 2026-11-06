<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Tenancy\Contracts\StoreSettingsSetup;
use App\Tenant\Messaging\Actions\ChangeMessagingSettings;
use App\Tenant\Messaging\Data\MessageRecipient;
use App\Tenant\Messaging\Data\MessagingSettingsChanges;
use App\Tenant\Messaging\Exceptions\MissingMessagingSettingsException;
use App\Tenant\Messaging\Jobs\SendMessage;
use App\Tenant\Messaging\Models\MessagingSettings;
use App\Tenant\Settings\Models\StoreSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use OwenIt\Auditing\Models\Audit;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Fixtures\Messaging\FakeAddressee;
use Tests\Fixtures\Messaging\FakeMessage;

/*
 * Step 20a: the store's messaging settings. Staff with settings.view see
 * them and with settings.manage change them: the name the store's emails
 * come from (the store's own by default), where replies go (the store's
 * contact email by default), and whether staff who fulfil orders are emailed
 * about each new order. Every email a store sends uses them, from the
 * platform's address. Every store has the row from the moment it is set up.
 */

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    ['owner' => $this->owner] = storefrontStore('messaging-store');
    $this->store->run(static fn (): bool => StoreSettings::query()->sole()->update(['name' => 'Ada Fabrics', 'contact_email' => 'hello@ada.example']));
});

afterEach(function (): void {
    deleteAllStores();
});

it('starts every store with the store\'s own name and contact email, which staff with settings.manage change and those with settings.view only see', function (): void {
    $viewer = storefrontStaffWith($this->store, ['settings.view']);
    $nobody = storefrontStaffWith($this->store, []);

    messagingStaffRequest('GET', 'settings', as: $viewer)->assertOk()->assertExactJson(['data' => [
        'sender_name' => null,
        'reply_to_email' => null,
        'has_new_order_alert_emails' => true,
        'updated_at' => $this->store->run(static fn (): string => (string) MessagingSettings::current()->updated_at?->toIso8601String()),
    ]]);
    messagingStaffRequest('GET', 'settings', as: $nobody)->assertForbidden();
    messagingStaffRequest('GET', 'settings')->assertUnauthorized();
    messagingStaffRequest('PATCH', 'settings', ['sender_name' => 'Ada'], as: $viewer)->assertForbidden();

    messagingStaffRequest('PATCH', 'settings', ['sender_name' => '  Ada at Ada Fabrics ', 'reply_to_email' => 'Orders@Ada.example', 'has_new_order_alert_emails' => false], as: $this->owner)->assertOk()
        ->assertJsonPath('data.sender_name', 'Ada at Ada Fabrics')
        ->assertJsonPath('data.reply_to_email', 'orders@ada.example')
        ->assertJsonPath('data.has_new_order_alert_emails', false);
    // Only what is sent changes; null goes back to the store's own.
    messagingStaffRequest('PATCH', 'settings', ['reply_to_email' => null], as: $this->owner)->assertOk()
        ->assertJsonPath('data.sender_name', 'Ada at Ada Fabrics')
        ->assertJsonPath('data.reply_to_email', null)
        ->assertJsonPath('data.has_new_order_alert_emails', false);

    expect($this->store->run(static fn (): array => Audit::query()->where('auditable_type', 'messaging_settings')->orderBy('id')->pluck('new_values')->all()))
        ->toBe([['sender_name' => 'Ada at Ada Fabrics', 'reply_to_email' => 'orders@ada.example', 'has_new_order_alert_emails' => false], ['reply_to_email' => null]]);
});

it('refuses a sender name too long and a reply-to that is not an email address, and reads a blank name as the store\'s own', function (): void {
    messagingStaffRequest('PATCH', 'settings', ['sender_name' => '   '], as: $this->owner)->assertOk()->assertJsonPath('data.sender_name', null);
    messagingStaffRequest('PATCH', 'settings', ['sender_name' => str_repeat('a', 81)], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('sender_name');
    messagingStaffRequest('PATCH', 'settings', ['reply_to_email' => 'not an email'], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('reply_to_email');
    messagingStaffRequest('PATCH', 'settings', ['has_new_order_alert_emails' => 'sometimes'], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('has_new_order_alert_emails');

    // The database refuses a blank name too.
    expect(fn () => $this->store->run(static fn () => DB::table('messaging_settings')->update(['sender_name' => ' '])))->toThrow(Illuminate\Database\QueryException::class);
});

it('sends the store\'s emails in its name from the platform\'s address, with replies to the store, and the platform\'s own in the platform\'s name', function (): void {
    $send = fn (): mixed => $this->store->run(static fn () => app()->call([new SendMessage(new FakeMessage, new FakeAddressee(new MessageRecipient('en', 'grace@example.com'))), 'handle']));
    $platformAddress = config()->string('mail.from.address');

    $send();
    messagingStaffRequest('PATCH', 'settings', ['sender_name' => 'Ada at Ada Fabrics', 'reply_to_email' => 'orders@ada.example'], as: $this->owner)->assertOk();
    $send();
    // An email that names its own reply-to keeps it.
    $this->store->run(static fn () => Mail::raw('Your return', static fn ($message) => $message->to('grace@example.com')->replyTo('returns@ada.example')->subject('Return')));
    Mail::raw('From the platform', static fn ($message) => $message->to('grace@example.com')->subject('Platform'));

    expect(array_map(static fn (SentMessage $sent): array => [
        array_map(static fn (Address $address): string => $address->toString(), sentEmail($sent)->getFrom()),
        array_map(static fn (Address $address): string => $address->toString(), sentEmail($sent)->getReplyTo()),
    ], sentEmails()))->toBe([
        [["\"Ada Fabrics\" <{$platformAddress}>"], ['hello@ada.example']],
        [["\"Ada at Ada Fabrics\" <{$platformAddress}>"], ['orders@ada.example']],
        [["\"Ada at Ada Fabrics\" <{$platformAddress}>"], ['returns@ada.example']],
        [['"'.config()->string('mail.from.name')."\" <{$platformAddress}>"], []],
    ]);
});

it('never shows or changes one store\'s settings from another', function (): void {
    ['owner' => $otherOwner] = storefrontStore('other-messaging');
    messagingStaffRequest('PATCH', 'settings', ['sender_name' => 'Other store'], as: $otherOwner, subdomain: 'other-messaging')->assertOk();

    messagingStaffRequest('GET', 'settings', as: $this->owner)->assertOk()->assertJsonPath('data.sender_name', null);
    test()->withToken(staffTokenFor($otherOwner, 'other-messaging'))->getJson(storeUrl('messaging-store', '/api/v1/staff/messaging/settings'))->assertUnauthorized();
    tenancy()->end();
});

it('gives a store set up before the migration its row, and a new store its row when it is set up, keeping what staff changed', function (): void {
    $migration = require database_path('migrations/tenant/2026_11_10_100000_create_messaging_settings_table.php');

    $rows = $this->store->run(static function () use ($migration): array {
        $migration->down();
        $migration->up();
        $created = MessagingSettings::current()->has_new_order_alert_emails;

        // Setting the store up again inserts nothing and changes nothing.
        app(ChangeMessagingSettings::class)->handle(new MessagingSettingsChanges(hasNewOrderAlertEmails: false));
        app(StoreSettingsSetup::class)->initialize();

        $keptAfterSetup = [MessagingSettings::current()->has_new_order_alert_emails, MessagingSettings::query()->count()];

        // A store being set up gets the row from its setup, with the defaults.
        DB::table('messaging_settings')->delete();
        app(StoreSettingsSetup::class)->initialize();
        $settings = MessagingSettings::current();

        return [$created, ...$keptAfterSetup, [$settings->sender_name, $settings->reply_to_email, $settings->has_new_order_alert_emails]];
    });

    expect($rows)->toBe([true, false, 1, [null, null, true]]);
});

it('fails loudly when the row is missing, never filling in defaults', function (): void {
    $this->store->run(static function (): void {
        DB::table('messaging_settings')->delete();

        expect(fn () => MessagingSettings::current())->toThrow(MissingMessagingSettingsException::class)
            ->and(fn () => app(ChangeMessagingSettings::class)->handle(new MessagingSettingsChanges(hasNewOrderAlertEmails: false)))->toThrow(MissingMessagingSettingsException::class)
            ->and(MessagingSettings::query()->count())->toBe(0);
    });

    messagingStaffRequest('GET', 'settings', as: $this->owner)->assertServerError();
});
