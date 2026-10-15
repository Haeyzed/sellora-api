<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Raised when a region is already in another zone: a country, or a state, belongs to at most one zone. Move it out of that zone first.
 */
final class ShippingRegionTakenException extends DomainException
{
    /**
     * @param  string  $region  Such as "NG" or "NG-LA"; empty when a change made at the same moment took it.
     * @param  string  $zone  The zone that has it; empty when not known.
     */
    public function __construct(private readonly string $region = '', string $zone = '', ?Throwable $previous = null)
    {
        parent::__construct(['region' => $region, 'zone' => $zone], $previous);
    }

    public function translatedMessage(): string
    {
        if ($this->region !== '') {
            return parent::translatedMessage();
        }

        $message = __('errors.shipping_region_taken_meanwhile');

        return is_string($message) ? $message : $this->errorCode();
    }

    public function errorCode(): string
    {
        return 'shipping_region_taken';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
