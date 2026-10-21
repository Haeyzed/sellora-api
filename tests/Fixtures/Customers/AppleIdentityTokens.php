<?php

declare(strict_types=1);

namespace Tests\Fixtures\Customers;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use RuntimeException;

/**
 * Apple's signing keys for tests: a key pair made here, its public half published as Apple's key set (to TestAppleProvider, so nothing is fetched from Apple), and identity tokens signed with it, or with another key.
 */
final class AppleIdentityTokens
{
    private const string KEY_ID = 'test-apple-key';

    private static ?string $privateKey = null;

    private static ?string $publicKey = null;

    /**
     * The test key, published as Apple publishes its keys (a JSON Web Key Set).
     *
     * @return array<string, mixed>
     */
    public static function keySet(): array
    {
        [, $public] = self::keyPair();
        $details = openssl_pkey_get_details(openssl_pkey_get_public($public) ?: throw new RuntimeException('Bad key.'));

        if (! is_array($details)) {
            throw new RuntimeException('Bad key.');
        }

        $encode = static fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return ['keys' => [[
            'kty' => 'RSA', 'kid' => self::KEY_ID, 'use' => 'sig', 'alg' => 'RS256',
            'n' => $encode($details['rsa']['n']), 'e' => $encode($details['rsa']['e']),
        ]]];
    }

    /**
     * An identity token as Apple signs it, for this audience, with these claims.
     *
     * @param  array<string, mixed>  $claims
     */
    public static function token(string $audience, string $subject, array $claims, bool $forged = false): string
    {
        [$private, $public] = $forged ? self::freshPair() : self::keyPair();
        $config = Configuration::forAsymmetricSigner(new Sha256, InMemory::plainText($private), InMemory::plainText($public));
        // The provider checks the token's times against the system clock, not the test's.
        $now = new DateTimeImmutable;

        $builder = $config->builder()
            ->issuedBy('https://appleid.apple.com')
            ->permittedFor($audience)
            ->relatedTo($subject)
            ->issuedAt($now)
            ->expiresAt($now->modify('+10 minutes'))
            ->withHeader('kid', self::KEY_ID);

        foreach ($claims as $name => $value) {
            $builder = $builder->withClaim($name, $value);
        }

        return $builder->getToken($config->signer(), $config->signingKey())->toString();
    }

    /**
     * @return array{non-empty-string, non-empty-string}
     */
    private static function keyPair(): array
    {
        if (self::$privateKey === null || self::$publicKey === null) {
            [self::$privateKey, self::$publicKey] = self::freshPair();
        }

        return [self::$privateKey, self::$publicKey];
    }

    /**
     * @return array{non-empty-string, non-empty-string}
     */
    private static function freshPair(): array
    {
        // PHP's OpenSSL on Windows needs its openssl.cnf named to make keys; elsewhere the system's is found.
        $config = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, ...(is_file($config) ? ['config' => $config] : [])];
        $key = openssl_pkey_new($options) ?: throw new RuntimeException('No key: '.openssl_error_string());
        openssl_pkey_export($key, $private, null, is_file($config) ? ['config' => $config] : []);
        $details = openssl_pkey_get_details($key);

        if (! is_string($private) || $private === '' || ! is_array($details) || ! is_string($details['key']) || $details['key'] === '') {
            throw new RuntimeException('No key.');
        }

        return [$private, $details['key']];
    }
}
