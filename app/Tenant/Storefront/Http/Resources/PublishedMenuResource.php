<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Resources;

use App\Tenant\Storefront\Models\Menu;
use App\Tenant\Storefront\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A menu as shoppers see it: only the links that lead somewhere they may go, in order.
 *
 * @property Menu $resource
 */
final class PublishedMenuResource extends JsonResource
{
    /**
     * @param  list<MenuItem>  $shownItems  From ShownMenuItems.
     */
    public function __construct(Menu $menu, private readonly array $shownItems)
    {
        parent::__construct($menu);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** Such as "main" or "footer". */
            'key' => $this->resource->key,
            /** Its top-level links in order, each with the links under it. */
            'items' => array_map(static fn (MenuItem $item): PublishedMenuItemResource => new PublishedMenuItemResource($item), $this->shownItems),
        ];
    }
}
