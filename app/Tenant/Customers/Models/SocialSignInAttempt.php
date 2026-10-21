<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Models;

use App\Tenant\Customers\Enums\SocialProvider;
use App\Tenant\Customers\Enums\SocialSignInIntent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A social sign-in the storefront started (Step 10b), keyed by the hash of its one-time state; the central relay keeps only where to send the answer.
 *
 * Holds whether it signs in or links, the customer linking, the PKCE
 * verifier (encrypted), the nonce and where the storefront returns. It is
 * used once, and expires.
 *
 * @property int $id
 * @property string $state_hash
 * @property SocialProvider $provider
 * @property SocialSignInIntent $intent
 * @property int|null $customer_id The customer linking a provider; null for a sign-in.
 * @property string $code_verifier
 * @property string $nonce
 * @property string|null $return_path
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $consumed_at
 * @property CarbonImmutable $created_at
 */
final class SocialSignInAttempt extends Model
{
    public const null UPDATED_AT = null;

    protected $hidden = [
        'state_hash',
        'code_verifier',
        'nonce',
    ];

    /**
     * The value stored for a state.
     */
    public static function hash(string $state): string
    {
        return hash('sha256', $state);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => SocialProvider::class,
            'intent' => SocialSignInIntent::class,
            'customer_id' => 'integer',
            'code_verifier' => 'encrypted',
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
