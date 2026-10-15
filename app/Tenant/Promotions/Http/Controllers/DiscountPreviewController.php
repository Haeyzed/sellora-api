<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Pricing\Exceptions\CurrencyNotOfferedException;
use App\Tenant\Promotions\Actions\PriceWithDiscounts;
use App\Tenant\Promotions\Http\Requests\PreviewDiscountsRequest;
use App\Tenant\Promotions\Http\Resources\DiscountedBasketResource;

/**
 * Lets staff check what a basket gets before customers do.
 */
final class DiscountPreviewController extends Controller
{
    /**
     * Preview a basket's discounts.
     *
     * Needs the promotions.view permission. Prices the lines with every
     * discount the basket gets now, for a customer or a guest, with the code
     * tried if any, exactly as checkout would; it reserves nothing.
     *
     * @throws CurrencyNotOfferedException
     */
    public function __invoke(PreviewDiscountsRequest $request, PriceWithDiscounts $priceWithDiscounts): DiscountedBasketResource
    {
        $currency = $request->currency();

        return new DiscountedBasketResource($priceWithDiscounts->handle($currency, $request->lines(), $request->destination(), $request->shipping($currency), $request->buyer(), $request->code()));
    }
}
