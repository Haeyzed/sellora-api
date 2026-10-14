<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Customers\Enums\CustomerDataExportStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's export of every piece of personal data the store holds about them, as a JSON file on a private disk.
 *
 * Only the customer downloads it, and only while their email is verified;
 * it is deleted, file first, once it expires (retention) or when they are erased.
 *
 * @property int $id
 * @property string $public_id
 * @property int $customer_id
 * @property CustomerDataExportStatus $status
 * @property string $disk
 * @property string|null $path
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $expires_at
 * @property-read Customer $customer
 */
final class CustomerDataExport extends Model
{
    use HasPublicId;

    public const null UPDATED_AT = null;

    protected $fillable = [
        'status',
        'disk',
        'path',
        'completed_at',
        'expires_at',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function isDownloadable(): bool
    {
        return $this->status === CustomerDataExportStatus::Ready && $this->path !== null && $this->expires_at?->isFuture() === true;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomerDataExportStatus::class,
            'created_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
