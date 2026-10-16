<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Data;

/**
 * A webhook exactly as it arrived: its raw body, which the signature covers, and its headers.
 */
final readonly class IncomingWebhook
{
    /**
     * @param  array<string, string>  $headers  By lower-case name, first value only.
     */
    public function __construct(
        public string $body,
        public array $headers,
    ) {}

    public function header(string $name): ?string
    {
        return $this->headers[mb_strtolower($name)] ?? null;
    }
}
