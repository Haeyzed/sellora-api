<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Services;

use InvalidArgumentException;
use Random\Randomizer;

/**
 * Makes random promotion codes from an alphabet without look-alikes (no 0, O, 1 or I), so they are easy to read out and hopeless to guess.
 *
 * Uses the system's cryptographically secure randomness unless given
 * another randomizer (tests use a seeded one).
 */
final readonly class PromotionCodeGenerator
{
    public const string ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private Randomizer $randomizer = new Randomizer) {}

    /**
     * @return list<string> Distinct codes, each the prefix followed by $length random characters.
     *
     * @throws InvalidArgumentException When the length isn't at least 1.
     */
    public function make(int $count, int $length, string $prefix = ''): array
    {
        if ($length < 1) {
            throw new InvalidArgumentException('A generated code needs at least one random character.');
        }

        $codes = [];

        while (count($codes) < $count) {
            $codes[$prefix.$this->randomizer->getBytesFromString(self::ALPHABET, $length)] = true;
        }

        return array_map(strval(...), array_keys($codes));
    }
}
