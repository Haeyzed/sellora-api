<?php

declare(strict_types=1);

namespace App\Shared\Auth\TwoFactor;

use App\Shared\Auth\AttemptLockout;
use App\Shared\Auth\Exceptions\InvalidTwoFactorCodeException;
use App\Shared\Auth\Exceptions\SignInTemporarilyLockedException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Auth\TwoFactor\Contracts\TwoFactorAuthenticatable;
use App\Shared\Exceptions\DomainException;
use App\Shared\Tenancy\TenantScopedKey;
use Closure;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PragmaRX\Google2FA\Google2FA;

/**
 * Creates authenticator secrets and recovery codes, and checks the codes people type.
 *
 * - Authenticator (TOTP) codes: 6 digits that change every 30 seconds; the
 *   code just before and after the current one is accepted for clock drift.
 *   Each code works once: the last one used is recorded, and older or equal
 *   ones are refused, even by two requests racing each other.
 * - Recovery codes: one-time codes for when the phone is lost. Only their
 *   hashes are stored, and a used code is removed.
 *
 * The only class that talks to the google2fa package.
 */
final readonly class TwoFactorAuthenticator
{
    /**
     * Letters and digits that can't be mistaken for each other when copied from paper (no 0/o, 1/l/i).
     */
    private const string RECOVERY_CODE_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    /**
     * 16 characters from 31 gives about 79 bits of randomness, far beyond guessing, so a fast hash is safe.
     */
    private const int RECOVERY_CODE_LENGTH = 16;

    public function __construct(
        private Google2FA $google2fa,
        private AttemptLockout $attemptLockout,
        private ConfigRepository $config,
    ) {}

    /**
     * A new random authenticator secret (base32), to show once during setup.
     */
    public function newSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * The otpauth:// link an authenticator app reads, for the frontend to show as a QR code.
     *
     * @param  string  $accountName  The name the app shows for the account, usually its email.
     */
    public function setupUrl(string $accountName, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl($this->config->string('app.name'), $accountName, $secret);
    }

    /**
     * New recovery codes in plain text, formatted "xxxx-xxxx-xxxx-xxxx", to show once.
     *
     * @return list<string>
     */
    public function newRecoveryCodes(): array
    {
        $recoveryCodes = [];

        for ($index = 0; $index < $this->config->integer('api.two_factor.recovery_code_count'); $index++) {
            $characters = '';

            for ($position = 0; $position < self::RECOVERY_CODE_LENGTH; $position++) {
                $characters .= self::RECOVERY_CODE_ALPHABET[random_int(0, mb_strlen(self::RECOVERY_CODE_ALPHABET) - 1)];
            }

            $recoveryCodes[] = implode('-', mb_str_split($characters, 4));
        }

        return $recoveryCodes;
    }

    /**
     * The value stored for a recovery code. Case, spaces and dashes don't matter when it is typed back.
     */
    public function hashRecoveryCode(string $recoveryCode): string
    {
        return hash('sha256', (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($recoveryCode)));
    }

    /**
     * The 30-second step a code belongs to, or null when the code doesn't match the secret now (or is not newer than the last one used).
     *
     * Checks only; recording the code as used is up to the caller, as
     * verifyCode() does for existing accounts.
     */
    public function matchingTimestep(string $secret, string $code, int $lastUsedTimestep = 0): ?int
    {
        $matchedTimestep = $this->google2fa->verifyKeyNewer($secret, $code, $lastUsedTimestep);

        return is_int($matchedTimestep) ? $matchedTimestep : null;
    }

    /**
     * Checks a code against a secret and records it as used, so it can't be used again.
     */
    public function verifyCode(Model&TwoFactorAuthenticatable $account, string $secret, string $code): bool
    {
        $lastUsedTimestep = $account->getAttribute('two_factor_last_used_timestep');
        $matchedTimestep = $this->matchingTimestep($secret, $code, is_int($lastUsedTimestep) ? $lastUsedTimestep : 0);

        if ($matchedTimestep === null) {
            return false;
        }

        $recordedAsUsed = $account->newQuery()
            ->whereKey($account->getKey())
            ->where(static function (Builder $query) use ($matchedTimestep): void {
                $query->whereNull('two_factor_last_used_timestep')->orWhere('two_factor_last_used_timestep', '<', $matchedTimestep);
            })
            ->update(['two_factor_last_used_timestep' => $matchedTimestep]);

        if ($recordedAsUsed !== 1) {
            return false;
        }

        $account->forceFill(['two_factor_last_used_timestep' => $matchedTimestep])->syncOriginalAttribute('two_factor_last_used_timestep');

        return true;
    }

    /**
     * Checks the code or recovery code given at sign-in, pausing after too many wrong ones.
     *
     * Wrong codes are counted per account and are not reset by a correct
     * password, so knowing the password alone doesn't allow guessing codes.
     *
     * @throws SignInTemporarilyLockedException After too many wrong codes for this account.
     * @throws InvalidTwoFactorCodeException When the code is wrong, already used or expired.
     */
    public function ensureValidForSignIn(Model&TwoFactorAuthenticatable $account, ?string $code, ?string $recoveryCode): void
    {
        $this->ensureValid($account, $code, $recoveryCode, static fn (int $secondsUntilUnlocked): SignInTemporarilyLockedException => new SignInTemporarilyLockedException($secondsUntilUnlocked));
    }

    /**
     * Checks the code or recovery code a signed-in person gives to confirm a sensitive change, such as turning two-factor authentication off.
     *
     * Wrong codes count towards the same per-account lockout as at sign-in.
     *
     * @throws TooManyIncorrectAttemptsException After too many wrong codes for this account.
     * @throws InvalidTwoFactorCodeException When the code is wrong, already used or expired.
     */
    public function ensureValidCode(Model&TwoFactorAuthenticatable $account, ?string $code, ?string $recoveryCode): void
    {
        $this->ensureValid($account, $code, $recoveryCode, static fn (int $secondsUntilUnlocked): TooManyIncorrectAttemptsException => new TooManyIncorrectAttemptsException($secondsUntilUnlocked));
    }

    /**
     * @param  Closure(int): DomainException  $lockedException  The error to raise while the account is locked, given the seconds until it unlocks.
     *
     * @throws DomainException While the account is locked.
     * @throws InvalidTwoFactorCodeException When the code is wrong, already used or expired.
     */
    private function ensureValid(Model&TwoFactorAuthenticatable $account, ?string $code, ?string $recoveryCode, Closure $lockedException): void
    {
        $lockoutKey = TenantScopedKey::make('two-factor-lockout', $account->getMorphClass(), (string) $account->getKey());
        $secondsUntilUnlocked = $this->attemptLockout->secondsUntilUnlocked($lockoutKey);

        if ($secondsUntilUnlocked !== null) {
            throw $lockedException($secondsUntilUnlocked);
        }

        $secret = $account->getAttribute('two_factor_secret');
        $isValid = match (true) {
            ! is_string($secret) || ! $account->hasTwoFactorEnabled() => false,
            $code !== null => $this->verifyCode($account, $secret, $code),
            $recoveryCode !== null => $this->useRecoveryCode($account, $recoveryCode),
            default => false,
        };

        if (! $isValid) {
            $this->attemptLockout->recordFailure($lockoutKey);

            throw new InvalidTwoFactorCodeException($recoveryCode !== null && $code === null ? 'recovery_code' : 'code');
        }

        $this->attemptLockout->clear($lockoutKey);
    }

    /**
     * Uses up a recovery code. Two requests with the same code can't both succeed: the account row is locked while the code is removed.
     */
    private function useRecoveryCode(Model&TwoFactorAuthenticatable $account, string $recoveryCode): bool
    {
        $hashedCode = $this->hashRecoveryCode($recoveryCode);

        $remainingCodes = $account->getConnection()->transaction(static function () use ($account, $hashedCode): ?array {
            $lockedAccount = $account->newQuery()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();
            $storedCodes = $lockedAccount->getAttribute('two_factor_recovery_codes');
            $remainingCodes = [];
            $found = false;

            foreach (is_array($storedCodes) ? $storedCodes : [] as $storedCode) {
                if (! $found && is_string($storedCode) && hash_equals($storedCode, $hashedCode)) {
                    $found = true;

                    continue;
                }

                $remainingCodes[] = $storedCode;
            }

            if (! $found) {
                return null;
            }

            $lockedAccount->forceFill(['two_factor_recovery_codes' => $remainingCodes])->save();

            return $remainingCodes;
        });

        if ($remainingCodes === null) {
            return false;
        }

        $account->forceFill(['two_factor_recovery_codes' => $remainingCodes])->syncOriginalAttribute('two_factor_recovery_codes');

        return true;
    }
}
