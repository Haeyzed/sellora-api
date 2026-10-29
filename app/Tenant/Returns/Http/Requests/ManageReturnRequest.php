<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff moving a return along with nothing more to say, such as receiving its items or calling it off. Needs the returns.manage permission.
 */
final class ManageReturnRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', OrderReturn::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
