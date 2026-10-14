<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Models;

use App\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\Tenant\CustomerGroupFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A named group of customers, such as "Wholesale" or "VIP", that staff put customers in by hand and promotions can be limited to.
 *
 * There are no rule-based segments and no default group: a customer is in
 * the groups staff added them to, and a guest is in none. Changes to the
 * group itself are audited; who was added or removed goes in the activity
 * log, by public ID.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name Unique in the store, ignoring case.
 * @property string|null $description For staff only.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read int|null $members_count
 * @property-read Collection<int, Customer> $members
 */
final class CustomerGroup extends Model implements AuditableContract
{
    use Auditable;

    /** @use HasFactory<CustomerGroupFactory> */
    use HasFactory;

    use HasPublicId;

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id'];

    /**
     * @return BelongsToMany<Customer, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_group_members')->withTrashed();
    }

    protected static function newFactory(): CustomerGroupFactory
    {
        return CustomerGroupFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
