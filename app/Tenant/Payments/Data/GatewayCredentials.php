<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Data;

use SensitiveParameter;

/**
 * A store's decrypted credentials for one gateway, handed to the gateway for each call. Never logged, dumped or returned.
 */
final readonly class GatewayCredentials
{
    /**
     * @param  array<string, string>  $values  By field name, as the gateway's credential fields declare them.
     */
    public function __construct(#[SensitiveParameter] private array $values) {}

    public function get(string $name): string
    {
        return $this->values[$name] ?? '';
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['values' => '[hidden]'];
    }
}
