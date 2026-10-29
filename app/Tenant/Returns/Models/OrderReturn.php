<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Models;

use App\Shared\Concerns\HasPublicId;
use App\Shared\Media\PrivateImages;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Returns\Enums\ReturnReason;
use App\Tenant\Returns\Enums\ReturnResolution;
use App\Tenant\Returns\Enums\ReturnStatus;
use App\Tenant\Returns\Services\ReturnPhotos;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A buyer, or staff for them, sending units of an order's lines back to be refunded (Step 18). Named OrderReturn because "return" is a PHP keyword.
 *
 * @property int $id
 * @property string $public_id
 * @property int $order_id
 * @property int $sequence Which return of the order this is, from 1.
 * @property ReturnStatus $status
 * @property ReturnResolution $resolution
 * @property ReturnReason $reason
 * @property string|null $note What the buyer or staff added about the reason; personal data.
 * @property bool $is_test Copied from its order.
 * @property bool $keep_items Approved without the items coming back.
 * @property string|null $outside_window_reason Why staff started it after the window closed.
 * @property string|null $undelivered_reason Why staff started it for units never marked delivered.
 * @property string|null $rejection_reason
 * @property int|null $stock_location_id Where its units were inspected.
 * @property string|null $requested_by_type Null for a guest.
 * @property string|null $requested_by_id
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $received_at
 * @property CarbonImmutable|null $inspected_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $anonymised_at When its note and photos were cleared with its order's personal data.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Order $order
 * @property-read StockLocation|null $stockLocation
 * @property-read Collection<int, ReturnLine> $lines
 * @property-read Collection<int, ReturnStatusChange> $statusChanges
 */
final class OrderReturn extends Model implements HasMedia
{
    use HasPublicId;
    use InteractsWithMedia;

    protected $table = 'returns';

    protected $dateFormat = 'Y-m-d H:i:sP';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(ReturnPhotos::COLLECTION)->useDisk(PrivateImages::disk(ReturnPhotos::KIND));
    }

    /**
     * How it is shown: its order's number and its own, such as "#1042-R1".
     */
    public function displayNumber(): string
    {
        return $this->order->displayNumber().'-R'.$this->sequence;
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    /**
     * @return HasMany<ReturnLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ReturnLine::class, 'return_id')->orderBy('id');
    }

    /**
     * @return HasMany<ReturnStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(ReturnStatusChange::class, 'return_id')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'sequence' => 'integer',
            'status' => ReturnStatus::class,
            'resolution' => ReturnResolution::class,
            'reason' => ReturnReason::class,
            'is_test' => 'boolean',
            'keep_items' => 'boolean',
            'stock_location_id' => 'integer',
            'requested_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'inspected_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'anonymised_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
