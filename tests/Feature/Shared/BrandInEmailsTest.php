<?php

declare(strict_types=1);

use App\Landlord\Identity\PlatformAdminInvitationNotification;
use App\Landlord\Tenancy\StoreClosedNotification;
use App\Shared\Tenancy\StoreExportReadyNotification;
use App\Tenant\Identity\OwnershipTransferOfferNotification;
use App\Tenant\Identity\OwnershipTransferredNotification;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notification;

/*
 * Emails that name the platform take its name from config('app.name')
 * (CLAUDE.md section 1), in every language, so a rebrand needs no
 * translation change and no message ever shows a raw ":app".
 */

it('names the platform from config in every language', function (Notification $notification): void {
    config()->set('app.name', 'Brandname Platform');
    $text = brandedEmailText($notification);

    expect($text)->toContain('Brandname Platform')->not->toContain(':app');
})->with([
    'platform admin invitation' => fn (): Notification => new PlatformAdminInvitationNotification('https://admin.example/accept', 'Ada', 3),
    'store closed' => fn (): Notification => new StoreClosedNotification('Ada Fabrics', CarbonImmutable::parse('2027-01-31'), isReminder: false),
    'store purge reminder' => fn (): Notification => new StoreClosedNotification('Ada Fabrics', CarbonImmutable::parse('2027-01-31'), isReminder: true),
    'store export ready' => fn (): Notification => new StoreExportReadyNotification('Ada Fabrics', CarbonImmutable::parse('2027-01-31'), 'https://admin.example/export'),
    'ownership transfer offer' => fn (): Notification => new OwnershipTransferOfferNotification('https://store.example/accept', 'Ada', 72),
    'ownership transferred, to the new owner' => fn (): Notification => new OwnershipTransferredNotification('Ada Fabrics', 'Grace', toNewOwner: true),
    'ownership transferred, to the previous owner' => fn (): Notification => new OwnershipTransferredNotification('Ada Fabrics', 'Grace', toNewOwner: false),
]);
