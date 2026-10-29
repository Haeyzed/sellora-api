<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Privacy;

use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use App\Tenant\Orders\Privacy\GuestDataSubject;

/**
 * A guest's returns, of the orders placed with their email: exported as they stand. On erasure their note and photos go with their order's anonymisation, so nothing is done here.
 */
final readonly class GuestReturnData implements PersonalDataHandler
{
    public function __construct(private ReturnPrivacy $returnPrivacy) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function export(DataSubject $subject): array
    {
        return $this->returnPrivacy->export(GuestDataSubject::orders($subject));
    }

    public function erase(DataSubject $subject): void {}
}
