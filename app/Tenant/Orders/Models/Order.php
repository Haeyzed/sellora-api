<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Models;

use App\Shared\Concerns\HasPublicId;
use App\Shared\Money\Decimal;
use App\Shared\Money\DecimalCast;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Shared\Money\TaxMode;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Orders\Data\OrderContact;
use App\Tenant\Orders\Enums\CancellationReason;
use App\Tenant\Orders\Enums\FulfilmentStatus;
use App\Tenant\Orders\Enums\OrderAddressKind;
use App\Tenant\Orders\Enums\OrderPaymentStatus;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Pricing\Enums\ExchangeRateSource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * The record of a sale (section 13): what was bought, at what price, with which discounts and taxes, shipped how, and its three statuses.
 *
 * Everything about the sale was copied when it was placed and never changes:
 * the database refuses it. Only its statuses, their timestamps, its refund
 * flag and the guest-to-customer link (once) change; its contact details and
 * note only during erasure. Its number (#1042) is for people to read, never
 * to find it by. Amounts are in its currency, in whole minor units.
 *
 * @property int $id
 * @property string $public_id
 * @property int $number Shown as "#1042"; per store, never used to look an order up.
 * @property string $checkout_id The checkout it was placed from.
 * @property OrderStatus $status
 * @property OrderPaymentStatus $payment_status Moved only by Payments.
 * @property bool $is_test Placed with a test-mode payment method; never changes. Only test payments pay it, and it never counts as a sale.
 * @property bool $pay_on_delivery Paid in cash on delivery; never changes. It may ship before it is paid, and stops expiring once a shipment exists.
 * @property FulfilmentStatus $fulfilment_status
 * @property bool $refund_due Paid, but cancelled: the payment is owed back.
 * @property int|null $customer_id Null for a guest order not linked to an account.
 * @property string|null $contact_email In lower case, as given at checkout; null once anonymised.
 * @property string|null $contact_name
 * @property string|null $contact_phone
 * @property string $locale The language its names were copied in.
 * @property string $currency
 * @property string $base_currency The store's base currency when it was placed.
 * @property Decimal $exchange_rate Base amount × rate = amount in its currency.
 * @property Decimal|null $market_rate
 * @property Decimal $margin_percent
 * @property ExchangeRateSource $rate_source
 * @property CarbonImmutable|null $rate_as_of
 * @property TaxMode $tax_mode
 * @property string $tax_calculated_by "rate_table", or a country plug-in's feature key.
 * @property string $tax_country_code Where tax was worked out to.
 * @property string|null $tax_state_code
 * @property string|null $tax_postal_code
 * @property Money $subtotal Unit prices × quantities, before discounts.
 * @property Money $discount Off the lines.
 * @property Money $shipping Before free-shipping discounts.
 * @property Money $shipping_discount
 * @property Money $tax Every line's tax and shipping's.
 * @property Money $total What the buyer pays.
 * @property Money $base_total The total in the base currency at its rate.
 * @property string|null $customer_note
 * @property CarbonImmutable $placed_at
 * @property CarbonImmutable $payment_due_at Cancelled if still unpaid after this.
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $fulfilled_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CancellationReason|null $cancellation_reason
 * @property string|null $cancelled_by_type Who cancelled it, by morph name; null when the sweep did.
 * @property string|null $cancelled_by_id
 * @property bool $anonymise_when_settled Its buyer's data was erased while it was still being fulfilled: anonymised once settled.
 * @property CarbonImmutable|null $anonymised_at When its buyer's personal data was removed; its amounts, tax and country stay.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Customer|null $customer
 * @property-read Collection<int, OrderLine> $lines
 * @property-read Collection<int, OrderDiscount> $discounts
 * @property-read Collection<int, OrderTax> $taxes
 * @property-read OrderShipping|null $shippingLine
 * @property-read Collection<int, OrderAddress> $addresses
 * @property-read Collection<int, OrderEvent> $events
 * @property-read Collection<int, OrderContactCorrection> $contactCorrections
 * @property-read Collection<int, OrderNote> $notes
 * @property-read Collection<int, OrderTag> $tags
 * @property-read Collection<int, Shipment> $shipments
 */
final class Order extends Model implements AuditableContract
{
    use Auditable;
    use HasPublicId;

    /** Times are saved with their offset, so a time in any timezone means the same moment. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * Contact details and the note are personal data that change only in erasure: never copied into audits.
     *
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id', 'contact_email', 'contact_name', 'contact_phone', 'customer_note', 'updated_at'];

    /**
     * The order's number as people see it, such as "#1042".
     */
    public function displayNumber(): string
    {
        return '#'.$this->number;
    }

    /**
     * The address of the kind given as it stands: the latest correction, or as placed; null when it has none.
     */
    public function currentAddress(OrderAddressKind $kind): ?OrderAddress
    {
        return $this->addresses->where('kind', $kind)->sortByDesc('version')->first();
    }

    /**
     * Whether it can still be paid for: open, and unpaid or paid only in part.
     */
    public function awaitsPayment(): bool
    {
        return $this->status === OrderStatus::Open && in_array($this->payment_status, [OrderPaymentStatus::Unpaid, OrderPaymentStatus::PartiallyPaid], true);
    }

    /**
     * The contact details as they stand: staff's latest correction, or as given at checkout.
     */
    public function currentContact(): OrderContact
    {
        $correction = $this->contactCorrections->sortByDesc('id')->first();

        return $correction === null
            ? new OrderContact($this->contact_email, $this->contact_name, $this->contact_phone, false)
            : new OrderContact($correction->email, $correction->name, $correction->phone, true);
    }

    /**
     * Staff's corrections of its contact details, oldest first.
     *
     * @return HasMany<OrderContactCorrection, $this>
     */
    public function contactCorrections(): HasMany
    {
        return $this->hasMany(OrderContactCorrection::class)->orderBy('id');
    }

    /**
     * @return HasMany<OrderNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(OrderNote::class);
    }

    /**
     * @return HasMany<OrderTag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(OrderTag::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class)->orderBy('position');
    }

    /**
     * @return HasMany<OrderDiscount, $this>
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(OrderDiscount::class)->orderBy('id');
    }

    /**
     * @return HasMany<OrderTax, $this>
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(OrderTax::class)->orderBy('id');
    }

    /**
     * How it was to be shipped, and what that cost; none when nothing ships.
     *
     * @return HasOne<OrderShipping, $this>
     */
    public function shippingLine(): HasOne
    {
        return $this->hasOne(OrderShipping::class);
    }

    /**
     * Every version of its addresses.
     *
     * @return HasMany<OrderAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class)->orderBy('kind')->orderBy('version');
    }

    /**
     * Its shipments, cancelled ones included.
     *
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class)->orderBy('id');
    }

    /**
     * @return HasMany<OrderEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => OrderStatus::class,
            'payment_status' => OrderPaymentStatus::class,
            'is_test' => 'boolean',
            'pay_on_delivery' => 'boolean',
            'fulfilment_status' => FulfilmentStatus::class,
            'refund_due' => 'boolean',
            'customer_id' => 'integer',
            'exchange_rate' => DecimalCast::class.':12',
            'market_rate' => DecimalCast::class.':12',
            'margin_percent' => DecimalCast::class.':4',
            'rate_source' => ExchangeRateSource::class,
            'rate_as_of' => 'immutable_datetime',
            'tax_mode' => TaxMode::class,
            'subtotal' => MoneyCast::class.':currency',
            'discount' => MoneyCast::class.':currency',
            'shipping' => MoneyCast::class.':currency',
            'shipping_discount' => MoneyCast::class.':currency',
            'tax' => MoneyCast::class.':currency',
            'total' => MoneyCast::class.':currency',
            'base_total' => MoneyCast::class.':base_currency',
            'placed_at' => 'immutable_datetime',
            'payment_due_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'fulfilled_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'cancellation_reason' => CancellationReason::class,
            'anonymise_when_settled' => 'boolean',
            'anonymised_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
