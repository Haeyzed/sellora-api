<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Customers\Data\AddressSnapshot;
use Carbon\CarbonImmutable;
use Database\Factories\Tenant\CustomerAddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An address a customer saved, to deliver to or to bill.
 *
 * Orders copy it (snapshot()), never link it, so editing or deleting it never
 * changes a past order (section 3.3).
 *
 * @property int $id
 * @property string $public_id
 * @property int $customer_id
 * @property string|null $label Such as "Home" or "Office".
 * @property string $recipient_name
 * @property string|null $company
 * @property string $line1
 * @property string|null $line2
 * @property string|null $city
 * @property string|null $state_code The state's code within the country, from the country's states list, such as "LA".
 * @property string|null $postal_code
 * @property string $country_code ISO 3166-1 alpha-2.
 * @property string|null $phone In E.164 format.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Customer $customer
 */
final class CustomerAddress extends Model
{
    /** @use HasFactory<CustomerAddressFactory> */
    use HasFactory;

    use HasPublicId;

    protected $fillable = [
        'label',
        'recipient_name',
        'company',
        'line1',
        'line2',
        'city',
        'state_code',
        'postal_code',
        'country_code',
        'phone',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * A copy of the address as it is now, for an order to keep.
     */
    public function snapshot(): AddressSnapshot
    {
        return new AddressSnapshot(
            recipientName: $this->recipient_name,
            company: $this->company,
            line1: $this->line1,
            line2: $this->line2,
            city: $this->city,
            stateCode: $this->state_code,
            postalCode: $this->postal_code,
            countryCode: $this->country_code,
            phone: $this->phone,
        );
    }

    protected static function newFactory(): CustomerAddressFactory
    {
        return CustomerAddressFactory::new();
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
