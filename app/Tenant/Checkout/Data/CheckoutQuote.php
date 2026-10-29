<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Data;

use App\Shared\Money\Money;
use App\Tenant\Cart\Data\PricedCart;
use App\Tenant\Checkout\Contracts\CheckoutPaymentMethod;
use App\Tenant\Checkout\Enums\PaymentMethodKind;
use App\Tenant\Checkout\Models\Checkout;
use LogicException;

/**
 * What a shopper is shown before placing their order (Step 16): the lines with their discounts, shipping, tax and total in the quote's currency at the rate it keeps, the ways to pay, and anything still to sort out.
 *
 * Its hash covers everything that would be charged, so placing the order can
 * prove nothing changed since it was shown.
 */
final readonly class CheckoutQuote
{
    /**
     * @param  list<OfferedPaymentMethod>  $paymentMethods  Every way to pay offered: core's, then modules'.
     * @param  OfferedPaymentMethod|null  $paymentMethod  Core's method chosen to pay what is left, while it is offered.
     * @param  array<string, Money>  $moduleCovers  What each chosen module method covers, by key, in the order applied.
     * @param  list<CheckoutIssue>  $issues  What stops it becoming an order; none when it can.
     * @param  bool  $taxIdRequired  Whether the store requires the buyer's tax ID.
     * @param  array{email: string, sms: string}  $consentWording  The marketing consent wording to show, in the shopper's language, never pre-ticked.
     * @param  array<string, CheckoutPaymentMethod>  $offeredBy  The extension that offered each payment method, by key: what holds, commits and releases its money.
     */
    public function __construct(
        public Checkout $checkout,
        public PricedCart $priced,
        public array $paymentMethods,
        public ?OfferedPaymentMethod $paymentMethod,
        public array $moduleCovers,
        public array $issues,
        public bool $taxIdRequired,
        public array $consentWording,
        public string $hash,
        public array $offeredBy = [],
    ) {}

    /**
     * The offer a key names, and the extension that made it.
     *
     * @return array{OfferedPaymentMethod, CheckoutPaymentMethod}
     *
     * @throws LogicException When the key wasn't offered, which a quote with no issues never has.
     */
    public function offer(string $key): array
    {
        foreach ($this->paymentMethods as $offer) {
            if ($offer->key === $key && isset($this->offeredBy[$key])) {
                return [$offer, $this->offeredBy[$key]];
            }
        }

        throw new LogicException("The payment method \"{$key}\" wasn't offered.");
    }

    /**
     * What the order would come to.
     *
     * @throws LogicException When nothing can be bought, which a quote with no issues never is.
     */
    public function total(): Money
    {
        return $this->priced->basket?->priced->total() ?? throw new LogicException('Nothing in the cart can be bought.');
    }

    /**
     * What is left to pay once the chosen module methods have covered their part.
     */
    public function amountDue(): Money
    {
        $due = $this->total();

        foreach ($this->moduleCovers as $covered) {
            $due = $due->subtract($covered);
        }

        return $due;
    }

    /**
     * The modules' methods the shopper chose, in the order applied, less any left with nothing to cover: those hold nothing and aren't chosen. One that isn't offered stays, as its issue says.
     *
     * @return list<string>
     */
    public function chosenModulePaymentMethods(): array
    {
        $offeredModules = [];

        foreach ($this->paymentMethods as $offer) {
            if ($offer->kind === PaymentMethodKind::Module) {
                $offeredModules[$offer->key] = true;
            }
        }

        return array_values(array_filter($this->checkout->module_payment_methods, fn (string $key): bool => isset($this->moduleCovers[$key]) || ! isset($offeredModules[$key])));
    }

    /**
     * Whether it may be placed as it stands.
     */
    public function canBePlaced(): bool
    {
        return $this->issues === [];
    }
}
