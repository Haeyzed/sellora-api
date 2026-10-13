<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Tenant\Customers\Concerns\ActsAsCustomer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A customer's answer to the consent text for one channel, as shown by "list my marketing consent" in the same language.
 */
final class AnswerMarketingConsentRequest extends FormRequest
{
    use ActsAsCustomer;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** True to agree to the wording shown, false to withdraw. */
            'granted' => ['required', 'boolean'],
        ];
    }
}
