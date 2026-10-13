<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff correcting a customer's name, phone or language. Only the fields sent change; the email is never one of them.
 */
final class UpdateCustomerAsStaffRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ValidatesCustomerProfile;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', Customer::class);
    }
}
