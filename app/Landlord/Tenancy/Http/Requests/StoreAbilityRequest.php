<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Requests;

use App\Landlord\Identity\Http\Requests\ActsAsPlatformAdmin;
use App\Landlord\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * Checks the platform admin may do something to the store in the route.
 *
 * @mixin FormRequest
 */
trait StoreAbilityRequest
{
    use ActsAsPlatformAdmin;

    abstract protected function ability(): string;

    public function authorize(): bool
    {
        return $this->actor()->can($this->ability(), $this->store());
    }

    public function store(): Tenant
    {
        $store = $this->route('store');

        return $store instanceof Tenant ? $store : throw new LogicException('This route must bind a {store}.');
    }
}
