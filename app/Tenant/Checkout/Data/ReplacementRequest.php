<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Data;

use App\Shared\Money\Money;
use App\Tenant\Customers\Data\AddressSnapshot;
use App\Tenant\Inventory\Data\StockHolder;
use App\Tenant\Orders\Enums\OrderAddressKind;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Promotions\Data\DiscountBuyer;
use App\Tenant\Promotions\Data\RedemptionHolder;
use App\Tenant\Shipping\Models\ShippingMethod;

/**
 * What a replacement order is made from (Step 18c): the order being exchanged, the return it replaces, the replacement lines, what the returned items are worth, and how it is shipped.
 *
 * The buyer, their addresses, the currency and the test flag all come from
 * the order being exchanged; nothing is taken from a shopper's request.
 */
final readonly class ReplacementRequest
{
    /**
     * @param  Order  $original  The order the returned items came from.
     * @param  int  $returnId  The return it replaces.
     * @param  string  $returnPublicId  The return's public ID: its stock and promotion holds, its lock and its allowances are named after it.
     * @param  string  $returnType  The return's morph name, for the holds.
     * @param  list<ReplacementLine>  $lines
     * @param  Money  $returnedValue  What the returned units cost the buyer after their discounts, in the order's currency: the most the exchange credits.
     * @param  ShippingMethod|null  $shippingMethod  How it is sent; null when nothing ships.
     * @param  bool  $chargesShipping  Whether the buyer pays for the delivery; when not, it is taken off as an allowance.
     * @param  string  $allowanceLabel  What the buyer sees for an allowance, in the order's language.
     */
    public function __construct(
        public Order $original,
        public int $returnId,
        public string $returnPublicId,
        public string $returnType,
        public array $lines,
        public Money $returnedValue,
        public ?ShippingMethod $shippingMethod,
        public bool $chargesShipping,
        public string $allowanceLabel,
    ) {}

    /**
     * Where the original order is sent now, after any correction; null when nothing was sent or it was anonymised.
     */
    public function shippingAddress(): ?AddressSnapshot
    {
        return $this->addressOf(OrderAddressKind::Shipping);
    }

    /**
     * The original order's billing address now, or where it was sent when it has none.
     */
    public function billingAddress(): ?AddressSnapshot
    {
        return $this->addressOf(OrderAddressKind::Billing) ?? $this->shippingAddress();
    }

    /**
     * The buyer the promotions are worked out for: the original order's customer, or its guest.
     */
    public function discountBuyer(): DiscountBuyer
    {
        $customer = $this->original->loadMissing('customer')->customer;

        return $customer === null ? DiscountBuyer::guest($this->original->contact_email) : DiscountBuyer::customer($customer);
    }

    /**
     * What the replacement's promotion uses are held by until the order takes them over: the return.
     */
    public function redemptionHolder(): RedemptionHolder
    {
        return new RedemptionHolder($this->returnType, $this->returnPublicId);
    }

    /**
     * What the replacement's stock is held by until the order takes it over: the return.
     */
    public function stockHolder(): StockHolder
    {
        return new StockHolder($this->returnType, $this->returnPublicId);
    }

    private function addressOf(OrderAddressKind $kind): ?AddressSnapshot
    {
        $address = $this->original->loadMissing('addresses')->currentAddress($kind);

        return $address === null || $address->line1 === null ? null : AddressSnapshot::fromArray($address->details());
    }
}
