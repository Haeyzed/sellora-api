<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Actions;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerNote;
use App\Tenant\Customers\Services\CustomerActivity;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Support\Facades\DB;

/**
 * Staff add a note about a customer, or delete one. Notes are never edited; the activity log records who added or deleted one, not what it said.
 */
final readonly class ManageCustomerNotes
{
    public function __construct(private CustomerActivity $customerActivity) {}

    public function add(StaffMember $actor, Customer $customer, string $body): CustomerNote
    {
        return DB::transaction(function () use ($actor, $customer, $body): CustomerNote {
            $note = $customer->notes()->make(['body' => $body]);
            $note->author_staff_id = $actor->id;
            $note->save();

            $this->customerActivity->record($actor, $customer, 'customer_note_added', 'Added a note to');

            return $note;
        });
    }

    public function delete(StaffMember $actor, CustomerNote $note): void
    {
        DB::transaction(function () use ($actor, $note): void {
            $note->delete();
            $this->customerActivity->record($actor, $note->customer, 'customer_note_deleted', 'Deleted a note on');
        });
    }
}
