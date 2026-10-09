<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Events;

use App\Tenant\Catalog\Models\Product;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A product became visible to customers. Announced only once the change is saved, so listeners never see a publish that was rolled back.
 */
final class ProductPublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Product $product) {}
}
