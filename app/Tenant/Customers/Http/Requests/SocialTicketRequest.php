<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Asking for the link emailed to the existing account, with the ticket the social sign-in answered.
 */
final class SocialTicketRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The ticket from the sign-in's answer. */
            'ticket' => ['required', 'string', 'max:128'],
        ];
    }

    public function ticket(): string
    {
        return $this->string('ticket')->value();
    }
}
