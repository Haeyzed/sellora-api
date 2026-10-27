<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\NotIn;

/**
 * The rules for a PIN a driver chooses: exactly 6 digits, and none of the PINs anyone would guess first.
 */
trait ValidatesNewDriverPin
{
    /**
     * Every digit the same, or a straight run up or down.
     */
    private const array GUESSABLE_PINS = [
        '000000', '111111', '222222', '333333', '444444', '555555', '666666', '777777', '888888', '999999',
        '012345', '123456', '234567', '345678', '456789',
        '987654', '876543', '765432', '654321', '543210',
    ];

    /**
     * @return list<string|NotIn>
     */
    protected function newPinRules(): array
    {
        return ['required', 'string', 'regex:/^\d{6}$/', Rule::notIn(self::GUESSABLE_PINS)];
    }
}
