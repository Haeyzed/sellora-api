<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Resources;

use App\Shared\Auth\DescribedPermission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A permission a role can grant, as dashboards show it: a short label, what it allows, its group, and whether granting it needs extra care. Texts are in the dashboard's language.
 *
 * @property DescribedPermission $resource
 */
final class GrantablePermissionResource extends JsonResource
{
    public function __construct(DescribedPermission $describedPermission)
    {
        parent::__construct($describedPermission);
    }

    /**
     * @return array{name: string, label: string, description: string, group: array{key: string, label: string}, is_sensitive: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            /** The permission's key, such as "orders.view": what the API takes when giving it. Never shown to people. */
            'name' => $this->resource->name,
            'label' => $this->resource->label,
            'description' => $this->resource->description,
            /** The group dashboards list it under: its domain, such as {"key": "orders", "label": "Orders and invoices"}. */
            'group' => ['key' => $this->resource->groupKey, 'label' => $this->resource->groupLabel],
            /** True when it moves money, shows personal data or internal costs, or changes who may do what: dashboards warn before granting it. */
            'is_sensitive' => $this->resource->isSensitive,
        ];
    }
}
