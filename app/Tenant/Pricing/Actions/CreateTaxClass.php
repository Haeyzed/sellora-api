<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Actions;

use App\Tenant\Pricing\Exceptions\TaxClassNameTakenException;
use App\Tenant\Pricing\Models\TaxClass;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Adds a tax class, such as "Children's clothing", which then needs rates for the countries the store sells to. Audited.
 */
final readonly class CreateTaxClass
{
    /**
     * @param  bool  $isExempt  Outside tax: never taxed, and no rates.
     *
     * @throws TaxClassNameTakenException When another class has the name, ignoring case.
     */
    public function handle(string $name, bool $isExempt = false): TaxClass
    {
        $taxClass = new TaxClass(['name' => $name, 'is_exempt' => $isExempt]);

        try {
            $taxClass->save();
        } catch (UniqueConstraintViolationException $exception) {
            throw new TaxClassNameTakenException(previous: $exception);
        }

        return $taxClass;
    }
}
