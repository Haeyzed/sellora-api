<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Tenant\Customers\Concerns\ActsAsCustomer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The parts of their profile a customer changes themselves. Only the fields sent change; null clears an optional one.
 */
final class UpdateCustomerProfileRequest extends FormRequest
{
    use ActsAsCustomer;
    use ValidatesCustomerProfile;
}
