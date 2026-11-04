<?php

declare(strict_types=1);

use App\Landlord\Identity\PlatformAdminInvitationNotification;
use App\Landlord\Tenancy\StoreClosedNotification;
use App\Landlord\Tenancy\StoreReadyNotification;
use App\Landlord\Tenancy\StoreRegistrationCodeNotification;
use App\Shared\Auth\PasswordResetLinkNotification;
use App\Shared\Tenancy\StoreExportReadyNotification;
use App\Tenant\Customers\CustomerEmailChangedNotification;
use App\Tenant\Customers\CustomerEmailLinkNotification;
use App\Tenant\Customers\Enums\CustomerEmailTokenPurpose;
use App\Tenant\Customers\Enums\SocialProvider;
use App\Tenant\Customers\SocialLinkNotification;
use App\Tenant\Identity\OwnershipTransferOfferNotification;
use App\Tenant\Identity\OwnershipTransferredNotification;
use App\Tenant\Identity\StaffInvitationNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Every queued notification is encrypted in the queue, so the stored job, in
 * the jobs table and in failed_jobs when it fails, never holds its link
 * token, code, email or names readably; and the worker still sends it.
 * None of these emails reads the store, so they are sent from the platform.
 */

uses(TruncatesCentralDatabase::class);

const QUEUED_EMAIL = 'ada.recipient@buyer.test';
const QUEUED_TOKEN = 'Tok3nQueuedSecretValue9f2c41';

beforeEach(function (): void {
    config(['queue.default' => 'database']);
});

dataset('queued notifications', [
    'platform admin invitation' => [fn () => PlatformAdminInvitationNotification::forToken(QUEUED_TOKEN, 'Grace Inviter'), [QUEUED_TOKEN, 'Grace Inviter'], QUEUED_TOKEN],
    'store registration code' => [fn () => new StoreRegistrationCodeNotification('48151623', 'Adas Shoes', 60), ['48151623'], '48151623'],
    'store ready' => [fn () => new StoreReadyNotification('Adas Shoes', 'https://ada.example.test/dashboard'), [], 'Adas Shoes'],
    'store closed' => [fn () => new StoreClosedNotification('Adas Shoes', CarbonImmutable::now()->addDays(90), false), [], 'Adas Shoes'],
    'social sign-in link' => [fn () => SocialLinkNotification::forToken(SocialProvider::Google, QUEUED_TOKEN), [QUEUED_TOKEN], QUEUED_TOKEN],
    'store export ready' => [fn () => new StoreExportReadyNotification('Adas Shoes', CarbonImmutable::now()->addDays(7), 'https://dashboard.example.test/exports'), [], 'Adas Shoes'],
    'customer email link' => [fn () => CustomerEmailLinkNotification::forToken(CustomerEmailTokenPurpose::Verification, QUEUED_TOKEN), [QUEUED_TOKEN], QUEUED_TOKEN],
    'customer email changed' => [fn () => new CustomerEmailChangedNotification, [], null],
    'staff invitation' => [fn () => StaffInvitationNotification::forToken(QUEUED_TOKEN, 'Grace Inviter'), [QUEUED_TOKEN, 'Grace Inviter'], QUEUED_TOKEN],
    'ownership transferred' => [fn () => new OwnershipTransferredNotification('Adas Shoes', 'Grace Newowner', false), ['Grace Newowner'], 'Grace Newowner'],
    'ownership transfer offer' => [fn () => OwnershipTransferOfferNotification::forTransfer('01JTRANSFER0000000000000000', 'Grace Owner'), ['Grace Owner'], 'Grace Owner'],
    'password reset link' => [fn () => PasswordResetLinkNotification::forBroker('customers', QUEUED_TOKEN, QUEUED_EMAIL), [QUEUED_TOKEN], QUEUED_TOKEN],
]);

it('keeps the token, code, email and names out of the queued job', function (Closure $notification, array $secrets): void {
    Notification::route('mail', QUEUED_EMAIL)->notify($notification());

    $payload = DB::table('jobs')->sole()->payload;

    expect(json_decode($payload, true)['data']['command'])->not->toStartWith('O:');

    foreach ([QUEUED_EMAIL, rawurlencode(QUEUED_EMAIL), ...$secrets] as $secret) {
        expect($payload)->not->toContain($secret);
    }
})->with('queued notifications');

it('keeps the token, code, email and names out of the job stored when it fails', function (Closure $notification, array $secrets): void {
    // Nothing listens on port 1, so sending fails at once.
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
    Notification::route('mail', QUEUED_EMAIL)->notify($notification());

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true, '--tries' => 1]);

    $payload = DB::table('failed_jobs')->sole()->payload;

    foreach ([QUEUED_EMAIL, rawurlencode(QUEUED_EMAIL), ...$secrets] as $secret) {
        expect($payload)->not->toContain($secret);
    }
})->with('queued notifications');

it('still sends the email once the worker has decrypted the job', function (Closure $notification, array $secrets, ?string $shownInEmail): void {
    Notification::route('mail', QUEUED_EMAIL)->notify($notification());

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true]);

    $sentMessages = app('mailer')->getSymfonyTransport()->messages();
    expect($sentMessages)->toHaveCount(1)
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);

    /** @var SentMessage $sentMessage */
    $sentMessage = $sentMessages->sole();
    /** @var Email $email */
    $email = $sentMessage->getOriginalMessage();

    expect($email->getTo()[0]->getAddress())->toBe(QUEUED_EMAIL);

    if ($shownInEmail !== null) {
        expect((string) $email->getTextBody())->toContain($shownInEmail);
    }
})->with('queued notifications');
