<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Resources;

use App\Shared\Auth\TwoFactor\TwoFactorSetup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A new authenticator secret to add to an authenticator app, shown once.
 *
 * @property TwoFactorSetup $resource
 */
final class TwoFactorSetupResource extends JsonResource
{
    public function __construct(TwoFactorSetup $twoFactorSetup)
    {
        parent::__construct($twoFactorSetup);
    }

    /**
     * @return array{secret: string, setup_url: string}
     */
    public function toArray(Request $request): array
    {
        return [
            /** For typing into the app by hand when the QR code can't be scanned. */
            'secret' => $this->resource->secret,
            /** An otpauth:// link; show it as a QR code for the app to scan. */
            'setup_url' => $this->resource->setupUrl,
        ];
    }
}
