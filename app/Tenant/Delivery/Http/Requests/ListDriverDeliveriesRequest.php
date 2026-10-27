<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use App\Shared\Http\CursorListRequest;

/**
 * A page of the signed-in driver's own deliveries, newest first.
 */
final class ListDriverDeliveriesRequest extends CursorListRequest
{
    use ActsAsDriver;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** Only deliveries still under way (true: assigned, picked up, on the way, or failed and not yet resolved) or only finished ones (false); both when left out. */
            'open' => ['sometimes', 'boolean'],
        ];
    }

    public function openFilter(): ?bool
    {
        return $this->has('open') ? $this->boolean('open') : null;
    }
}
