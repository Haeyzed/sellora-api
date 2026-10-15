<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Data;

use App\Tenant\Cart\Models\Cart;

/**
 * The customer's cart after a guest cart was merged into it, and the guest's lines that didn't fit or can no longer be bought.
 */
final readonly class MergedCart
{
    /**
     * @param  list<SkippedLine>  $skipped
     */
    public function __construct(
        public Cart $cart,
        public array $skipped,
    ) {}
}
