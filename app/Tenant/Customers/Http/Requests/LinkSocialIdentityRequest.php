<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Tenant\Customers\Concerns\ActsAsCustomer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The signed-in customer finishing connecting a provider: the state and code the storefront's callback page read from its URL fragment.
 */
final class LinkSocialIdentityRequest extends FormRequest
{
    use ActsAsCustomer;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The "state" from the URL fragment. */
            'state' => ['required', 'string', 'max:128'],
            /** The "code" from the URL fragment. */
            'code' => ['required', 'string', 'max:2048'],
        ];
    }

    public function state(): string
    {
        return $this->string('state')->value();
    }
}
