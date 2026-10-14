<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Resources;

use App\Shared\Auth\Data\RecoveryCodes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One-time recovery codes for signing in without the authenticator app, shown once.
 *
 * @property RecoveryCodes $resource
 */
final class RecoveryCodesResource extends JsonResource
{
    /**
     * @param  list<string>  $recoveryCodes
     */
    public function __construct(array $recoveryCodes)
    {
        parent::__construct(new RecoveryCodes($recoveryCodes));
    }

    /**
     * @return array{recovery_codes: list<string>}
     */
    public function toArray(Request $request): array
    {
        return [
            /** Each code works once. They can't be shown again, so ask the person to store them safely. */
            'recovery_codes' => $this->resource->codes,
        ];
    }
}
