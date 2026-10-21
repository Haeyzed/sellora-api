<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What a social sign-in provider sends back to the relay page: the code and state, or an error. Anything else is ignored, so nothing in the request can choose where the browser goes next.
 */
final class SocialSignInRelayRequest extends FormRequest
{
    /**
     * Never refuses: a bad answer gets the relay's error page, not a validation response.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function state(): string
    {
        return $this->field('state', 128);
    }

    /**
     * The provider's error as sent, such as "access_denied" when the customer declined, cut to 64 characters, for the error page to show escaped; null when none.
     */
    public function providerError(): ?string
    {
        $error = $this->input('error');

        return is_string($error) && $error !== '' ? mb_substr($error, 0, 64) : null;
    }

    /**
     * What goes on to the storefront: the code and state, or the provider's error code (letters, digits and _ only).
     *
     * @return array<string, string>
     */
    public function fragment(): array
    {
        $error = $this->providerError();
        $error = $error !== null && preg_match('/^[A-Za-z0-9_]+$/', $error) === 1 ? $error : ($error === null ? null : 'provider_error');

        return array_filter([
            'state' => $this->state(),
            'code' => $error === null ? $this->field('code', 2048) : '',
            'error' => $error ?? '',
            'name' => $error === null ? $this->appleName() : '',
        ], static fn (string $value): bool => $value !== '');
    }

    /**
     * The name Apple posts the first time someone signs in ("user", unsigned JSON), passed on as display text only and never stored here; the store checks it again when the storefront sends it back. Apple's email in it is ignored: only its signed identity token is believed.
     */
    private function appleName(): string
    {
        $user = $this->field('user', 2048);
        $decoded = $user === '' ? null : json_decode($user, true);

        if (! is_array($decoded) || ! is_array($decoded['name'] ?? null)) {
            return '';
        }

        $parts = array_filter([$decoded['name']['firstName'] ?? null, $decoded['name']['lastName'] ?? null], is_string(...));
        $name = trim((string) preg_replace('/[\p{C}]+/u', '', implode(' ', $parts)));

        return mb_substr($name, 0, 120);
    }

    private function field(string $key, int $maxLength): string
    {
        $value = $this->input($key);

        return is_string($value) && strlen($value) <= $maxLength ? $value : '';
    }
}
