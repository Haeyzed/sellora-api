<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use App\Tenant\Delivery\Models\Driver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\NotIn;
use LogicException;

/**
 * A signed-in driver changing their own PIN.
 */
final class ChangeDriverPinRequest extends FormRequest
{
    use ValidatesNewDriverPin;

    /**
     * @return array<string, list<string|NotIn>>
     */
    public function rules(): array
    {
        return [
            'current_pin' => ['required', 'string'],
            /** 6 digits; easy guesses such as 123456 or 111111 are refused. */
            'pin' => [...$this->newPinRules(), 'different:current_pin'],
        ];
    }

    public function driver(): Driver
    {
        $driver = $this->user();

        if (! $driver instanceof Driver) {
            throw new LogicException('This route must be protected by the auth:driver middleware.');
        }

        return $driver;
    }
}
