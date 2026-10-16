<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Privacy;

use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;

/**
 * A guest's orders, found by the email they were placed with, for staff answering a guest's data-protection request: exported as they stand, and anonymised on erasure once settled (OrderPrivacy).
 *
 * Only guest orders: those that joined an account are the account's, and go
 * with its own request.
 */
final readonly class GuestOrderData implements PersonalDataHandler
{
    public function __construct(private OrderPrivacy $orderPrivacy) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function export(DataSubject $subject): array
    {
        return $this->orderPrivacy->export(GuestDataSubject::orders($subject));
    }

    public function erase(DataSubject $subject): void
    {
        $this->orderPrivacy->erase(GuestDataSubject::orders($subject), null);
    }
}
