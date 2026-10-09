<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Events;

use App\Tenant\Catalog\Models\Product;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A product stopped being visible to customers, by being archived or moved to the trash. Announced only once the change is saved.
 */
final class ProductArchived implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Product $product) {}
}
