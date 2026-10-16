<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderNote;
use App\Tenant\Orders\Services\OrderActivity;

/**
 * Staff adding notes to an order for each other, and deleting them. Notes are never shown to the buyer and never changed.
 */
final readonly class ManageOrderNotes
{
    public function __construct(private OrderActivity $orderActivity) {}

    public function add(StaffMember $author, Order $order, string $body): OrderNote
    {
        $note = new OrderNote;
        $note->forceFill(['order_id' => $order->id, 'author_staff_id' => $author->id, 'body' => trim($body)])->save();
        $this->orderActivity->record($author, $order, 'order_note_added', 'Added a note to');

        return $note->load('author');
    }

    public function delete(StaffMember $actor, Order $order, OrderNote $note): void
    {
        $note->delete();
        $this->orderActivity->record($actor, $order, 'order_note_deleted', 'Deleted a note on');
    }
}
