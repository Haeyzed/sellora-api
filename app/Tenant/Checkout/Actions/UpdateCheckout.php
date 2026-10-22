<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Actions;

use App\Tenant\Checkout\Data\CheckoutQuote;
use App\Tenant\Checkout\Models\Checkout;
use App\Tenant\Checkout\Services\CheckoutQuotes;
use Carbon\CarbonImmutable;

/**
 * Saves what the shopper told us at checkout (contact details, addresses, the way to ship and to pay, their tax ID, their marketing consent, a note) and returns the quote it now comes to (Step 16).
 *
 * A signed-in customer's email stays their account's. Marketing consent is
 * only ever true when the shopper explicitly said yes.
 */
final readonly class UpdateCheckout
{
    public function __construct(private CheckoutQuotes $checkoutQuotes) {}

    /**
     * @param  array<string, mixed>  $changes  Validated, by checkout column.
     */
    public function handle(Checkout $checkout, array $changes): CheckoutQuote
    {
        $checkout->forceFill([...$changes, 'last_activity_at' => CarbonImmutable::now()]);

        if ($checkout->customer_id !== null) {
            $checkout->contact_email = mb_strtolower((string) $checkout->customer?->email);
        }

        $checkout->save();

        return $this->checkoutQuotes->refresh($checkout);
    }
}
