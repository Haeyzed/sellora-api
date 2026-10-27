<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Delivery\Enums\DeliveryFailureReason;
use App\Tenant\Delivery\Enums\DeliveryResolution;
use App\Tenant\Delivery\Enums\DeliveryStatus;
use App\Tenant\Orders\Models\Shipment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of the store's own drivers taking a shipment to its buyer.
 *
 * A shipment has at most one open delivery at a time: assigned, picked up,
 * on the way, or failed and not yet resolved. A failed one stays open, with
 * the goods still with the driver, until staff resolve it with another
 * attempt or by taking the goods back.
 *
 * @property int $id
 * @property string $public_id
 * @property int $shipment_id
 * @property int $driver_id
 * @property int $attempt From 1.
 * @property DeliveryStatus $status
 * @property DeliveryFailureReason|null $failure_reason
 * @property string|null $failure_note
 * @property string|null $recipient_name Who took the goods.
 * @property CarbonImmutable $assigned_at
 * @property CarbonImmutable|null $picked_up_at
 * @property CarbonImmutable|null $on_the_way_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $resolved_at
 * @property DeliveryResolution|null $resolution
 * @property string $assigned_by_type
 * @property string $assigned_by_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Shipment $shipment
 * @property-read Driver $driver
 * @property-read Collection<int, DeliveryStatusChange> $statusChanges
 */
final class Delivery extends Model
{
    use HasPublicId;

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * Whether the driver has the goods: picked up, on the way, or failed and not yet resolved.
     */
    public function holdsGoods(): bool
    {
        return $this->status->holdsGoods() && $this->resolved_at === null;
    }

    /**
     * Whether it is still under way: assigned, or holding the goods.
     */
    public function isOpen(): bool
    {
        return $this->status === DeliveryStatus::Assigned || $this->holdsGoods();
    }

    /**
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return HasMany<DeliveryStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(DeliveryStatusChange::class)->orderBy('id');
    }

    /**
     * Deliveries still under way: assigned, picked up, on the way, or failed and not yet resolved.
     *
     * @param  Builder<self>  $query
     */
    protected function scopeOpen(Builder $query): void
    {
        $query->where(static fn (Builder $open) => $open
            ->whereIn('status', [DeliveryStatus::Assigned->value, DeliveryStatus::PickedUp->value, DeliveryStatus::OnTheWay->value])
            ->orWhere(static fn (Builder $failed) => $failed->where('status', DeliveryStatus::Failed->value)->whereNull('resolved_at')));
    }

    /**
     * Deliveries whose driver has the goods: picked up, on the way, or failed and not yet resolved.
     *
     * @param  Builder<self>  $query
     */
    protected function scopeHoldingGoods(Builder $query): void
    {
        $query->where(static fn (Builder $holding) => $holding
            ->whereIn('status', [DeliveryStatus::PickedUp->value, DeliveryStatus::OnTheWay->value])
            ->orWhere(static fn (Builder $failed) => $failed->where('status', DeliveryStatus::Failed->value)->whereNull('resolved_at')));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shipment_id' => 'integer',
            'driver_id' => 'integer',
            'attempt' => 'integer',
            'status' => DeliveryStatus::class,
            'failure_reason' => DeliveryFailureReason::class,
            'resolution' => DeliveryResolution::class,
            'assigned_at' => 'immutable_datetime',
            'picked_up_at' => 'immutable_datetime',
            'on_the_way_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
