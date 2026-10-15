<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Http\Resources;

use App\Tenant\Cart\Models\Cart;
use App\Tenant\Cart\Models\CartLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shopper's cart: its lines, and the version to send back (If-Match) with a change.
 *
 * @property Cart $resource
 */
final class CartResource extends JsonResource
{
    /**
     * @param  string|null  $token  A new guest cart's token: in this response only, never again.
     */
    public function __construct(Cart $cart, private readonly ?string $token = null)
    {
        parent::__construct($cart);
    }

    /**
     * The relations every response needs, so none is loaded lazily.
     *
     * @return list<string>
     */
    public static function relations(): array
    {
        return ['lines.variant.product'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cart = $this->resource;
        $locale = app()->getLocale();

        return [
            'id' => $cart->public_id,
            /** Goes up with every change; send it back in If-Match (it is also the ETag) to change or remove lines. */
            'version' => $cart->version,
            /**
             * A new guest cart's token. Shown only in the response that created the cart: keep it, and send it in the X-Cart-Token header.
             */
            'token' => $this->when($this->token !== null, $this->token),
            /**
             * Its lines, oldest first.
             *
             * @var list<array{id: string, variant: array{id: string, sku: string|null, product: array{id: string, slug: string, name: string}}, quantity: int, added_at: string|null}>
             */
            'lines' => array_values($cart->lines->map(static fn (CartLine $line): array => [
                'id' => $line->public_id,
                'variant' => [
                    'id' => $line->variant->public_id,
                    'sku' => $line->variant->sku,
                    'product' => [
                        'id' => $line->variant->product->public_id,
                        'slug' => $line->variant->product->slug,
                        /** In the language asked for, or the store's default. */
                        'name' => $line->variant->product->getTranslation('name', $locale),
                    ],
                ],
                'quantity' => $line->quantity,
                'added_at' => $line->created_at?->toIso8601String(),
            ])->all()),
        ];
    }

    /**
     * Carries the cart's version as the ETag, to send back in If-Match.
     */
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setEtag((string) $this->resource->version);
    }
}
