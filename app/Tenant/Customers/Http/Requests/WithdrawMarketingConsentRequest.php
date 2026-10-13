<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Tenant\Customers\Enums\MarketingChannel;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff recording that a customer withdrew their consent for a channel, for example by phone. Needs customers.manage.
 */
final class WithdrawMarketingConsentRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', Customer::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::enum(MarketingChannel::class)],
        ];
    }

    public function channel(): MarketingChannel
    {
        return MarketingChannel::from($this->string('channel')->value());
    }
}
