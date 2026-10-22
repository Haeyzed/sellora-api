<?php

declare(strict_types=1);

namespace App\Shared\Idempotency;

use Illuminate\Http\Request;

/**
 * The ways a guest can be known, asked in order until one names them: their order's access token, then their cart's. Each domain gives its own; this knows none of them.
 */
final readonly class GuestRequesters implements GuestRequester
{
    /** @var list<GuestRequester> */
    private array $requesters;

    public function __construct(GuestRequester ...$requesters)
    {
        $this->requesters = array_values($requesters);
    }

    public function scopeOf(Request $request): ?string
    {
        foreach ($this->requesters as $requester) {
            $scope = $requester->scopeOf($request);

            if ($scope !== null) {
                return $scope;
            }
        }

        return null;
    }
}
