<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Privacy;

use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use App\Tenant\Customers\Models\CustomerNote;

/**
 * What staff wrote about the customer. The customer's export includes every note (section 3.3), without who wrote it: a staff member's internal ID is the store's, not the customer's.
 */
final readonly class CustomerNoteData implements PersonalDataHandler
{
    /**
     * @return list<array{id: string, body: string, created_at: string}>
     */
    public function export(DataSubject $subject): array
    {
        $customer = CustomerDataSubject::customer($subject);

        if ($customer === null) {
            return [];
        }

        return array_values($customer->notes()->orderBy('id')->get()->map(static fn (CustomerNote $note): array => [
            'id' => $note->public_id,
            'body' => $note->body,
            'created_at' => $note->created_at->toIso8601String(),
        ])->all());
    }

    public function erase(DataSubject $subject): void
    {
        CustomerDataSubject::customer($subject)?->notes()->delete();
    }
}
