<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Giving an email to finish a social sign-in whose provider gave none, with the ticket it answered.
 */
final class CompleteSocialSignUpRequest extends FormRequest
{
    private const string DEFAULT_DEVICE_NAME = 'storefront';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The ticket from the sign-in's "email_required" answer. */
            'ticket' => ['required', 'string', 'max:128'],
            /** Your email; a link is sent to verify it. */
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            /** A label for the device, shown when the customer reviews where they are signed in. */
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function ticket(): string
    {
        return $this->string('ticket')->value();
    }

    /**
     * Trimmed and lower-cased, as accounts store it.
     */
    public function normalisedEmail(): string
    {
        return mb_strtolower(trim($this->string('email')->value()));
    }

    public function deviceName(): string
    {
        return $this->string('device_name', self::DEFAULT_DEVICE_NAME)->value();
    }
}
