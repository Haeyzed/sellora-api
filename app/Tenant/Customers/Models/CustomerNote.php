<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Identity\Models\StaffMember;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a staff member wrote about a customer. Notes are never edited, only added or deleted.
 *
 * The customer's own data export includes them, without who wrote them
 * (section 3.3), so staff write them knowing the customer may read them.
 *
 * @property int $id
 * @property string $public_id
 * @property int $customer_id
 * @property int|null $author_staff_id
 * @property string $body
 * @property CarbonImmutable $created_at
 * @property-read Customer $customer
 * @property-read StaffMember|null $author
 */
final class CustomerNote extends Model
{
    use HasPublicId;

    public const null UPDATED_AT = null;

    protected $fillable = [
        'body',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<StaffMember, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class, 'author_staff_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }
}
