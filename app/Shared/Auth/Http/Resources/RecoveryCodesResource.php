<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One-time recovery codes for signing in without the authenticator app, shown once.
 *
 * @property list<string> $resource
 */
final class RecoveryCodesResource extends JsonResource
{
    /**
     * @param  list<string>  $recoveryCodes
     */
    public function __construct(array $recoveryCodes)
    {
        parent::__construct($recoveryCodes);
    }

    /**
     * @return array{recovery_codes: list<string>}
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var list<string> Each code works once. They can't be shown again, so ask the person to store them safely. */
            'recovery_codes' => $this->resource,
        ];
    }
}
