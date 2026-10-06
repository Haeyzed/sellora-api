<?php

declare(strict_types=1);

namespace App\Shared\Privacy;

/**
 * Removes secret values from JSON records, such as an audit of a password change, before they leave the store in an export.
 *
 * Any key that names a secret (password, PIN, token, secret, two-factor,
 * recovery code, credential, API or private key) has its value replaced,
 * however deeply it is nested. A value that isn't valid JSON is replaced
 * whole, because it can't be checked.
 */
final class SecretRedactor
{
    public const string REDACTED = '[redacted]';

    private const string SECRET_KEY_PATTERN = '/password|passcode|(^|_)pin($|_)|token|secret|two_factor|recovery|credential|api_?key|private_?key/i';

    /**
     * @param  string|null  $json  A JSON column's value as stored.
     * @return mixed The decoded value with secrets replaced, or null when there was nothing or it wasn't JSON.
     */
    public function redactJson(?string $json): mixed
    {
        if ($json === null || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return self::REDACTED;
        }

        return $this->redact($decoded);
    }

    private function redact(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $redacted = [];

        foreach ($value as $key => $item) {
            $redacted[$key] = is_string($key) && preg_match(self::SECRET_KEY_PATTERN, $key) === 1
                ? self::REDACTED
                : $this->redact($item);
        }

        return $redacted;
    }
}
