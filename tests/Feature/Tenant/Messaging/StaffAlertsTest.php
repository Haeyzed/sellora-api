<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Retention\PurgeExpiredRecords;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Checkout\Events\PaymentHoldKeepsFailing;
use App\Tenant\Inventory\Events\StockRunningLow;
use App\Tenant\Messaging\Actions\RaiseStaffAlert;
use App\Tenant\Messaging\Data\StaffAlertData;
use App\Tenant\Messaging\Enums\StaffAlertKind;
use App\Tenant\Messaging\Jobs\SendMessage;
use App\Tenant\Messaging\Models\MessagingSettings;
use App\Tenant\Messaging\Models\StaffAlert;
use App\Tenant\Messaging\Services\StaffAlertEmailCap;
use App\Tenant\Messaging\StaffAddressee;
use App\Tenant\Orders\Events\OrderPlaced;
use App\Tenant\Payments\Events\RefundFailed;
use App\Tenant\Payments\Events\RefundNeedsReview;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\SecondConnection;

/*
 * Step 20a: staff alerts. An event that needs staff attention raises an
 * alert once it has committed: listed for the active staff holding its
 * kind's permission (and the owner), and emailed to them in their own
 * language. The same alert about the same thing is raised once (once a day
 * for kinds that may come again), even when two arrive at the same moment.
 * Past the hourly email cap alerts are only listed, with one "alerts
 * waiting" email each, except money alerts, which are always emailed. A new
 * order is always listed, and emailed while the messaging settings say so.
 */

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    ['owner' => $this->owner] = storefrontStore('messaging-store');
    $this->refunder = storefrontStaffWith($this->store, ['payments.refund']);
    $this->fulfiller = storefrontStaffWith($this->store, ['orders.fulfil']);
    $this->stockKeeper = storefrontStaffWith($this->store, ['inventory.adjust']);
    $this->nobody = storefrontStaffWith($this->store, ['orders.view']);
    $this->formerRefunder = storefrontStaffWith($this->store, ['payments.refund']);
    $this->store->run(function (): void {
        $this->formerRefunder->forceFill(['is_active' => false])->save();
        $this->refunder->forceFill(['locale' => 'fr'])->save();
    });
    Notification::fake();
});

afterEach(function (): void {
    deleteAllStores();
});

it('emails an alert to the active staff holding its permission and the owner, each in their language, and lists it for them alone', function (): void {
    $this->store->run(static fn () => RefundFailed::dispatch(alertPaymentRefund()));

    expect(alertEmailsSent())->toBe([
        [$this->owner->email, 'en', 'A refund for order #1042 failed'],
        [$this->refunder->email, 'fr', 'Un remboursement de la commande #1042 a échoué'],
    ]);

    messagingStaffRequest('GET', 'alerts', as: $this->refunder)->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.kind', 'refund_failed')
        ->assertJsonPath('data.0.is_money', true)
        ->assertJsonPath('data.0.title', 'A refund for order #1042 failed')
        ->assertJsonPath('data.0.subject', ['type' => 'payment_refund', 'id' => 'payment-refund-1'])
        ->assertJsonPath('data.0.details.order_number', 1042)
        ->assertJsonPath('data.0.details.amount', 3000)
        ->assertJsonPath('data.0.details.currency', 'NGN');
    messagingStaffRequest('GET', 'alerts', as: $this->owner)->assertOk()->assertJsonCount(1, 'data');
    messagingStaffRequest('GET', 'alerts', as: $this->fulfiller)->assertOk()->assertJsonCount(0, 'data');
    messagingStaffRequest('GET', 'alerts', as: $this->nobody)->assertOk()->assertJsonCount(0, 'data');
    messagingStaffRequest('GET', 'alerts')->assertUnauthorized();
});

it('raises the same alert about the same thing once, and a kind that may come again once a day', function (): void {
    $variant = $this->store->run(static fn (): ProductVariant => cartVariant(5000, stock: 3));

    $this->store->run(static function () use ($variant): void {
        RefundFailed::dispatch(alertPaymentRefund());
        RefundFailed::dispatch(alertPaymentRefund());
        StockRunningLow::dispatch($variant->id, 1, 5, 2);
        StockRunningLow::dispatch($variant->id, 1, 5, 1);
    });
    $this->travel(1)->days();
    $this->store->run(static fn () => StockRunningLow::dispatch($variant->id, 1, 5, 1));

    expect($this->store->run(static fn (): array => StaffAlert::query()->orderBy('id')->pluck('kind')->map(static fn (StaffAlertKind $kind): string => $kind->value)->all()))
        ->toBe(['refund_failed', 'stock_running_low', 'stock_running_low'])
        ->and(count(alertEmailsSent()))->toBe(2 + 2 + 2);
});

it('saves an alert raised twice at the same moment once: the second waits for the first, then adds nothing', function (): void {
    $alert = new StaffAlertData(StaffAlertKind::RefundFailed, 'payment_refund', 'payment-refund-1', ['order_number' => 1042]);

    $this->store->run(static function () use ($alert): void {
        $other = SecondConnection::open();
        $other->act(static fn () => app(RaiseStaffAlert::class)->handle($alert));

        expect($other->blockedStatement(static fn () => app(RaiseStaffAlert::class)->handle($alert)))->toContain('insert into "staff_alerts"');

        $other->commit();

        expect(app(RaiseStaffAlert::class)->handle($alert))->toBeNull()
            ->and(StaffAlert::query()->count())->toBe(1);
    });
});

it('holds alerts back past the hourly email cap, with one "alerts waiting" email each, but always emails money alerts and never counts them against it', function (): void {
    config(['messaging.staff_alerts.emails_per_hour' => 1]);
    $variants = $this->store->run(static fn (): array => [cartVariant(5000, stock: 3), cartVariant(6000, stock: 3), cartVariant(7000, stock: 3)]);
    $firstProduct = $this->store->run(static fn (): string => (string) $variants[0]->product->name);

    $this->store->run(static function () use ($variants): void {
        // Emailed first, yet it leaves the hour's one place to the first stock alert.
        RefundFailed::dispatch(alertPaymentRefund());

        foreach ($variants as $variant) {
            StockRunningLow::dispatch($variant->id, 1, 5, 2);
        }

        // Raised with the store over its cap, and emailed all the same.
        RefundNeedsReview::dispatch(alertPaymentRefund());
    });

    $sent = alertEmailsSent();
    expect(array_count_values(array_map(static fn (array $email): string => $email[0].' '.$email[2], $sent)))->toBe([
        $this->owner->email.' A refund for order #1042 failed' => 1,
        $this->refunder->email.' Un remboursement de la commande #1042 a échoué' => 1,
        $this->owner->email.' '.$firstProduct.' is running low' => 1,
        $this->stockKeeper->email.' '.$firstProduct.' is running low' => 1,
        $this->owner->email.' More alerts are waiting' => 1,
        $this->stockKeeper->email.' More alerts are waiting' => 1,
        $this->owner->email.' A refund for order #1042 needs review' => 1,
        $this->refunder->email.' Un remboursement de la commande #1042 est à vérifier' => 1,
    ])->and($this->store->run(static fn (): array => StaffAlert::query()->orderBy('id')->get()->map(static fn (StaffAlert $alert): bool => $alert->emailed_at !== null)->all()))
        ->toBe([true, true, false, false, true]);

    // Every alert is still listed.
    messagingStaffRequest('GET', 'alerts', as: $this->stockKeeper)->assertOk()->assertJsonCount(3, 'data');
});

it('counts emails against the cap under a lock, which a money alert never waits for', function (): void {
    [$capped, $money] = $this->store->run(static fn (): array => [
        StaffAlert::query()->create(['kind' => StaffAlertKind::DeliveryFailed, 'repeat_key' => 'a', 'subject_type' => 'delivery', 'subject_id' => 'd', 'details' => []]),
        StaffAlert::query()->create(['kind' => StaffAlertKind::RefundFailed, 'repeat_key' => 'b', 'subject_type' => 'payment_refund', 'subject_id' => 'r', 'details' => []]),
    ]);

    $this->store->run(static function () use ($capped, $money): void {
        $other = SecondConnection::open()->holdAdvisoryLock('staff-alert-email-cap');

        expect($other->blockedStatement(static fn () => app(StaffAlertEmailCap::class)->claimEmail($capped)))->toContain('pg_advisory_xact_lock')
            ->and($other->blocks(static fn () => app(StaffAlertEmailCap::class)->claimEmail($money)))->toBeFalse();
        $other->rollBack();
    });
});

it('always lists a new order for staff who fulfil orders, and emails it only while the messaging settings say so', function (): void {
    $this->store->run(static fn () => OrderPlaced::dispatch(alertOrder()));
    messagingStaffRequest('PATCH', 'settings', ['has_new_order_alert_emails' => false], as: $this->owner)->assertOk();
    $this->store->run(static fn () => OrderPlaced::dispatch(alertOrder()->forceFill(['public_id' => 'order-2', 'number' => 1043])));

    expect(array_map(static fn (array $email): string => $email[0].' '.$email[2], alertEmailsSent()))->toBe([
        $this->owner->email.' New order #1042',
        $this->fulfiller->email.' New order #1042',
    ]);
    messagingStaffRequest('GET', 'alerts', as: $this->fulfiller)->assertOk()
        ->assertJsonPath('data.*.title', ['New order #1043', 'New order #1042'])
        ->assertJsonPath('data.0.emailed_at', null);
    expect($this->store->run(static fn (): bool => MessagingSettings::current()->has_new_order_alert_emails))->toBeFalse();
});

it('raises an alert only once the change it is about has committed, and never for one rolled back', function (): void {
    // An event announced straight away (not itself held until commit), so only the listener waits.
    $this->store->run(static function (): void {
        DB::transaction(static function (): void {
            PaymentHoldKeepsFailing::dispatch(alertHold());

            expect(StaffAlert::query()->count())->toBe(0);
        });

        expect(StaffAlert::query()->count())->toBe(1);
        StaffAlert::query()->delete();
    });
    Notification::fake();

    $this->store->run(static function (): void {
        try {
            DB::transaction(static function (): void {
                PaymentHoldKeepsFailing::dispatch(alertHold());

                throw new RuntimeException('Rolled back.');
            });
        } catch (RuntimeException) {
            // The change was rolled back, as a failed request's would be.
        }
    });

    expect($this->store->run(static fn (): int => StaffAlert::query()->count()))->toBe(0)
        ->and(alertEmailsSent())->toBe([]);
});

it('lists alerts newest first by cursor, of one kind when asked, in the request\'s language', function (): void {
    $this->store->run(static function (): void {
        RefundFailed::dispatch(alertPaymentRefund());
        OrderPlaced::dispatch(alertOrder());
    });

    messagingStaffRequest('GET', 'alerts?per_page=1', as: $this->owner)->assertOk()
        ->assertJsonPath('data.*.kind', ['new_order'])
        ->assertJsonPath('meta.per_page', 1);
    messagingStaffRequest('GET', 'alerts?kind=refund_failed', as: $this->owner)->assertOk()->assertJsonPath('data.*.kind', ['refund_failed']);
    messagingStaffRequest('GET', 'alerts?kind=everything', as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('kind');
    test()->withHeader('Accept-Language', 'de');
    messagingStaffRequest('GET', 'alerts?kind=refund_failed', as: $this->owner)->assertOk()->assertJsonPath('data.0.title', 'Eine Erstattung für Bestellung #1042 ist fehlgeschlagen');
    test()->flushHeaders();
});

it('never shows one store\'s alerts in another', function (): void {
    $this->store->run(static fn () => RefundFailed::dispatch(alertPaymentRefund()));
    ['owner' => $otherOwner] = storefrontStore('other-alerts');

    messagingStaffRequest('GET', 'alerts', as: $otherOwner, subdomain: 'other-alerts')->assertOk()->assertJsonCount(0, 'data');
});

it('queues an alert only for active staff, and looks them up again when it is sent: nobody deactivated after it was queued gets it', function (): void {
    Queue::fake();

    $this->store->run(function (): void {
        RefundFailed::dispatch(alertPaymentRefund());

        expect(Queue::pushed(SendMessage::class)->map(static fn (SendMessage $job): ?int => $job->addressee instanceof StaffAddressee ? $job->addressee->staffMemberId : null)->sort()->values()->all())
            ->toBe([$this->owner->id, $this->refunder->id]);

        $this->refunder->forceFill(['is_active' => false])->save();

        foreach (Queue::pushed(SendMessage::class) as $job) {
            app()->call([$job, 'handle']);
        }
    });

    expect(alertEmailsSent())->toBe([[$this->owner->email, 'en', 'A refund for order #1042 failed']]);
});

it('deletes alerts in the daily purge once they are older than their retention period', function (): void {
    $this->store->run(static function (): void {
        RefundFailed::dispatch(alertPaymentRefund());
        StaffAlert::query()->update(['created_at' => CarbonImmutable::now()->subDays(config()->integer('retention.periods.staff_alerts') + 1)]);
        OrderPlaced::dispatch(alertOrder());

        app()->call([new PurgeExpiredRecords, 'handle']);

        expect(StaffAlert::query()->pluck('subject_type')->all())->toBe(['order']);
    });
});
