<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Exceptions;

use RuntimeException;

/**
 * Thrown by a carrier that can't quote right now. Core never shows it: the carrier's methods fall back to their backup price, or aren't offered.
 */
final class CarrierUnavailableException extends RuntimeException {}
