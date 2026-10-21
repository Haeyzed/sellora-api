<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Data;

use App\Shared\Auth\IssuedAccessToken;
use App\Tenant\Customers\Enums\SocialSignInStatus;

/**
 * How a social sign-in ended: signed in with a token, or a ticket for what the customer still has to do.
 */
final readonly class SocialSignInResult
{
    /**
     * @param  list<string>  $proofMethods  For a ticket to link an existing account: "password", and "email_link" where allowed.
     */
    public function __construct(
        public SocialSignInStatus $status,
        public ?IssuedAccessToken $token = null,
        public bool $newAccount = false,
        public ?string $ticket = null,
        public array $proofMethods = [],
        public ?string $returnPath = null,
    ) {}

    public function withReturnPath(?string $returnPath): self
    {
        return new self($this->status, $this->token, $this->newAccount, $this->ticket, $this->proofMethods, $returnPath);
    }
}
