<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Resources;

use App\Tenant\Storefront\Models\Menu;
use App\Tenant\Storefront\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A menu as staff see it: its key, its name and, when shown on its own, all its links.
 *
 * @property Menu $resource
 */
final class MenuResource extends JsonResource
{
    /** What to load for one menu with all its links: everything a link may go to, at both levels, the trash included, so staff see every link as they saved it. */
    public const array RELATIONS = [
        'items.page', 'items.category', 'items.brand', 'items.product',
        'items.children.page', 'items.children.category', 'items.children.brand', 'items.children.product',
    ];

    public function __construct(Menu $menu)
    {
        parent::__construct($menu);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $menu = $this->resource;

        return [
            'id' => $menu->public_id,
            /** What the storefront asks for the menu by, such as "main". */
            'key' => $menu->key,
            /** The menu's name for staff. */
            'name' => $menu->name,
            /** Its top-level links in order, each with the links under it; only when one menu is shown. */
            'items' => $this->whenLoaded('items', static fn (): array => array_values($menu->items->map(static fn (MenuItem $item): MenuItemResource => new MenuItemResource($item))->all())),
            'created_at' => $menu->created_at?->toIso8601String(),
            'updated_at' => $menu->updated_at?->toIso8601String(),
        ];
    }
}
