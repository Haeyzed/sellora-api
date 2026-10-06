<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Requests;

use App\Landlord\Identity\Http\Requests\ActsAsPlatformAdmin;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Http\PaginatedListRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * A page of stores, optionally by status or matching a search.
 */
final class ListStoresRequest extends PaginatedListRequest
{
    use ActsAsPlatformAdmin;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Tenant::class);
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['sometimes', Rule::enum(TenantStatus::class)],
            /** Part of the store's name, domain or owner's email. */
            'search' => ['sometimes', 'string', 'min:2', 'max:100'],
        ];
    }

    public function status(): ?TenantStatus
    {
        return $this->filled('status') ? $this->enum('status', TenantStatus::class) : null;
    }

    public function search(): ?string
    {
        return $this->filled('search') ? trim($this->string('search')->value()) : null;
    }
}
