<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Resources;

use App\Shared\Auth\AccessTokenResource;
use App\Tenant\Customers\Data\SocialSignInResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * How a social sign-in ended: signed in with a token, or what the customer still has to do, with a ticket for it.
 *
 * @property-read SocialSignInResult $resource
 */
final class SocialSignInResultResource extends JsonResource
{
    public function __construct(private readonly SocialSignInResult $result)
    {
        parent::__construct($result);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** "signed_in"; "link_account": an account already has this email, so prove it is yours (proof_methods) with the ticket; "email_required": the provider gave no email, so give one with the ticket. */
            'status' => $this->result->status->value,
            /** The bearer token, once signed in; null otherwise. */
            'token' => $this->result->token === null ? null : new AccessTokenResource($this->result->token),
            /** Whether a new account was made. */
            'new_account' => $this->result->newAccount,
            /** For "link_account" and "email_required": send it back with the next step. Works once, for 10 minutes. */
            'ticket' => $this->result->ticket,
            /** For "link_account": "password" (the account's password), and "email_link" (a link emailed to the account) when allowed. */
            'proof_methods' => $this->result->proofMethods,
            /** The storefront path given when the sign-in started, if any. */
            'return_path' => $this->result->returnPath,
        ];
    }
}
