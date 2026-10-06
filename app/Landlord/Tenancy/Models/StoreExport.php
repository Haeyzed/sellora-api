<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Models;

use App\Landlord\Tenancy\Enums\StoreExportStatus;
use App\Shared\Auth\AccountReference;
use App\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A full export of one store's data: a ZIP kept privately on the store's regional disk for 7 days, downloadable only by whoever asked for it.
 *
 * @property int $id
 * @property string $public_id
 * @property string $tenant_id
 * @property string $requested_by_type The kind of account that asked: "platform_admin" or "staff_member" (the owner).
 * @property string $requested_by_id That account's public ID.
 * @property StoreExportStatus $status As stored; see currentStatus() for one past its time.
 * @property string $disk
 * @property string|null $path
 * @property int|null $size_bytes
 * @property CarbonImmutable|null $ready_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Tenant $tenant
 */
final class StoreExport extends Model
{
    use CentralConnection;
    use HasPublicId;

    protected $fillable = [
        'disk',
        'expires_at',
    ];

    public function wasRequestedBy(AccountReference $account): bool
    {
        return $this->requested_by_type === $account->type && $this->requested_by_id === $account->publicId;
    }

    /**
     * Its status as it is now: a ready export past its time is expired, even before its file is deleted.
     */
    public function currentStatus(): StoreExportStatus
    {
        if ($this->status === StoreExportStatus::Ready && ! $this->expires_at->isFuture()) {
            return StoreExportStatus::Expired;
        }

        return $this->status;
    }

    public function isDownloadable(): bool
    {
        return $this->currentStatus() === StoreExportStatus::Ready && $this->path !== null;
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The exports one account asked for.
     *
     * @param  Builder<self>  $query
     */
    protected function scopeRequestedBy(Builder $query, AccountReference $account): void
    {
        $query->where('requested_by_type', $account->type)->where('requested_by_id', $account->publicId);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StoreExportStatus::class,
            'size_bytes' => 'integer',
            'ready_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
