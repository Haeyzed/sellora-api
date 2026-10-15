<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Data;

use App\Tenant\Catalog\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * The categories each line's product is in, with every category above them, so a promotion on "Clothing" covers "Shirts" too. Read once per basket.
 */
final readonly class LineCategories
{
    /**
     * @param  array<string, list<int>>  $byLine  Category IDs by line key.
     */
    private function __construct(private array $byLine) {}

    /**
     * @param  list<DiscountLine>  $lines
     */
    public static function of(array $lines): self
    {
        $productIds = array_values(array_unique(array_map(static fn (DiscountLine $line): int => $line->variant->product_id, $lines)));

        if ($productIds === []) {
            return new self([]);
        }

        $parents = Category::withTrashed()->pluck('parent_id', 'id')->all();
        $direct = DB::table('category_product')->whereIn('product_id', $productIds)->get(['product_id', 'category_id'])->groupBy('product_id');
        $byLine = [];

        foreach ($lines as $line) {
            $ids = [];

            foreach ($direct->get($line->variant->product_id, collect()) as $row) {
                for ($id = (int) $row->category_id, $depth = 0; $id !== 0 && $depth < 64; $id = (int) ($parents[$id] ?? 0), $depth++) {
                    $ids[$id] = true;
                }
            }

            $byLine[$line->key] = array_keys($ids);
        }

        return new self($byLine);
    }

    /**
     * @return list<int>
     */
    public function forLine(string $lineKey): array
    {
        return $this->byLine[$lineKey] ?? [];
    }
}
