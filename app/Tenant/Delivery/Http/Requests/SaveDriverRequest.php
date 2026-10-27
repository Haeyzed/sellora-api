<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Settings\Actions\FindStoreSettings;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Adding a driver, or changing their name or phone number. Needs the drivers.manage permission.
 */
final class SaveDriverRequest extends FormRequest
{
    use ActsAsStaffMember;

    private ?string $storeCountry = null;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', Driver::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:120'],
            /** The number the driver signs in with. In international format, or national format for the store's own country. Stored in E.164. */
            'phone' => [
                $required,
                'string',
                'max:30',
                // Built when validating, not here, so the store's country is only looked up for a request that sends a phone.
                fn (string $attribute, mixed $value, Closure $fail) => (new Phone)->international()->country([$this->storeCountry()])->setData($this->all())->validate($attribute, $value, $fail),
            ],
        ];
    }

    public function driverName(): ?string
    {
        return $this->has('name') ? $this->string('name')->trim()->value() : null;
    }

    /**
     * The phone number in E.164 format, or null when the request doesn't change it.
     */
    public function e164Phone(): ?string
    {
        return $this->has('phone') ? (new PhoneNumber($this->string('phone')->value(), $this->storeCountry()))->formatE164() : null;
    }

    private function storeCountry(): string
    {
        return $this->storeCountry ??= app(FindStoreSettings::class)->handle()->country_code;
    }
}
