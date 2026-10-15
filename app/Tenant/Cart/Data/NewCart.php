<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Data;

use App\Tenant\Cart\Models\Cart;

/**
 * A cart just asked for: a guest's new one with its token, shown this once, or a customer's own (new or existing).
 */
final readonly class NewCart
{
    /**
     * @param  string|null  $token  A guest's token; it is never stored or shown again.
     * @param  bool  $created  False when a customer already had this cart.
     */
    public function __construct(
        public Cart $cart,
        public ?string $token,
        public bool $created,
    ) {}
}
