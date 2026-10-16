<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Data;

/**
 * One credential a store enters to connect a gateway, such as its secret key, as the gateway's integration declares it.
 */
final readonly class CredentialField
{
    /**
     * @param  string  $name  The field's name in the request and in the stored credentials, such as "secret_key".
     * @param  list<string>  $rules  Validation rules for it, after "required" and "string".
     * @param  bool  $isSecret  Never shown again, not even in part.
     */
    public function __construct(
        public string $name,
        public array $rules = [],
        public bool $isSecret = true,
    ) {}
}
