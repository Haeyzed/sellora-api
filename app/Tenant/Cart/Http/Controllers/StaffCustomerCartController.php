<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Cart\Actions\FindCart;
use App\Tenant\Cart\Actions\PriceCart;
use App\Tenant\Cart\Data\Shopper;
use App\Tenant\Cart\Exceptions\CartNotFoundException;
use App\Tenant\Cart\Http\Requests\ViewCustomerCartRequest;
use App\Tenant\Cart\Http\Resources\CartResource;
use App\Tenant\Customers\Models\Customer;

/**
 * A customer's cart as staff see it, to help the customer: read only.
 */
final class StaffCustomerCartController extends Controller
{
    /**
     * Get a customer's cart.
     *
     * Priced now, exactly as the customer sees it (their currency, their tax
     * country, their code and their groups' promotions). Needs customers.view.
     * Staff can't change a customer's cart. A customer without a cart gives
     * 404 cart_not_found.
     *
     * @throws CartNotFoundException
     */
    public function show(ViewCustomerCartRequest $request, Customer $customer, FindCart $findCart, PriceCart $priceCart): CartResource
    {
        return new CartResource($priceCart->handle($findCart->handle(Shopper::customer($customer))));
    }
}
