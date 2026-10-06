<?php

declare(strict_types=1);

namespace App\Shared\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Laravel\Sanctum\Contracts\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Issues and revokes sign-in tokens for any kind of account (platform admin, staff member, customer or driver).
 *
 * Every token carries exactly one ability, the guard it belongs to, and
 * expires after the lifetime in config/sanctum.php.
 */
final readonly class AccessTokenIssuer
{
    private const int MAXIMUM_DEVICE_NAME_LENGTH = 100;

    public function __construct(private ConfigRepository $config) {}

    /**
     * Signs the account in on one device and returns the token to give to it.
     */
    public function issue(HasApiTokens $account, string $guard, string $deviceName): IssuedAccessToken
    {
        $expiresAt = CarbonImmutable::now()->addMinutes($this->config->integer('sanctum.expiration'));
        $newAccessToken = $account->createToken(mb_substr($deviceName, 0, self::MAXIMUM_DEVICE_NAME_LENGTH), [$guard], $expiresAt);

        return new IssuedAccessToken($newAccessToken->plainTextToken, $expiresAt);
    }

    /**
     * Replaces the token the request used with a new one, keeping the same device name.
     */
    public function refresh(HasApiTokens $account, string $guard): IssuedAccessToken
    {
        $currentAccessToken = $account->currentAccessToken();
        $deviceName = $currentAccessToken instanceof PersonalAccessToken ? $currentAccessToken->name : 'api';

        $issuedAccessToken = $this->issue($account, $guard, $deviceName);
        $this->revokeCurrent($account);

        return $issuedAccessToken;
    }

    /**
     * Signs the account out of the device the request came from.
     */
    public function revokeCurrent(HasApiTokens $account): void
    {
        $currentAccessToken = $account->currentAccessToken();

        if ($currentAccessToken instanceof PersonalAccessToken) {
            $currentAccessToken->delete();
        }
    }

    /**
     * Signs the account out everywhere except the device the request came from, for example after a password change.
     */
    public function revokeAllExceptCurrent(HasApiTokens $account): void
    {
        $currentAccessToken = $account->currentAccessToken();
        $otherAccessTokens = $account->tokens();

        if ($currentAccessToken instanceof PersonalAccessToken) {
            $otherAccessTokens->whereKeyNot($currentAccessToken->getKey());
        }

        $otherAccessTokens->delete();
    }

    /**
     * Signs the account out everywhere, for example after a password reset or deactivation.
     */
    public function revokeAll(HasApiTokens $account): void
    {
        $account->tokens()->delete();
    }
}
