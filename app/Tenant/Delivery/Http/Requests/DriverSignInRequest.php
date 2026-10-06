<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * A driver's phone number and PIN, as typed into the driver app.
 */
final class DriverSignInRequest extends FormRequest
{
    private const string DEFAULT_DEVICE_NAME = 'Unnamed device';

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            /** In international format with the country code, for example +2348012345678. */
            'phone' => ['required', 'string', 'max:20', (new Phone)->international()],
            'pin' => ['required', 'string', 'regex:/^\d{4,6}$/'],
            /** A label for the device, shown when staff review where the driver is signed in. */
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }

    /**
     * The phone number in E.164 format, the form drivers are stored with.
     */
    public function e164Phone(): string
    {
        return (new PhoneNumber($this->string('phone')->value()))->formatE164();
    }

    public function deviceName(): string
    {
        return $this->string('device_name', self::DEFAULT_DEVICE_NAME)->value();
    }
}
