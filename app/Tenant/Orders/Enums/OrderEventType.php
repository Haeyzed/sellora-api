<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Enums;

/**
 * Something that happened to an order, kept in its history (order_events), which is never changed.
 */
enum OrderEventType: string
{
    case Placed = 'placed';

    case Paid = 'paid';

    /** Its payment status moved other than to paid in full: partially paid, partially refunded or refunded. */
    case PaymentStatusChanged = 'payment_status_changed';

    /** A payment arrived after the order was cancelled: it stays cancelled, and the payment is owed back. */
    case PaidAfterCancellation = 'paid_after_cancellation';
    case RefundedBeyondPaid = 'refunded_beyond_paid';

    /** Its invoice was issued, once it was paid in full. */
    case InvoiceIssued = 'invoice_issued';

    /** A credit note was issued against its invoice, for part of a refund that reduced the sale. */
    case CreditNoteIssued = 'credit_note_issued';

    case Cancelled = 'cancelled';

    case Fulfilled = 'fulfilled';

    case Completed = 'completed';

    /** A guest order joined the account whose verified email it was placed with. */
    case LinkedToCustomer = 'linked_to_customer';

    case ContactCorrected = 'contact_corrected';

    case AddressCorrected = 'address_corrected';

    case TagsChanged = 'tags_changed';

    case ShipmentCreated = 'shipment_created';

    case ShipmentReady = 'shipment_ready';

    case ShipmentShipped = 'shipment_shipped';

    case ShipmentDelivered = 'shipment_delivered';

    case ShipmentCancelled = 'shipment_cancelled';

    /** One of the store's own deliveries came back undelivered (Step 17). */
    case ShipmentReturned = 'shipment_returned';

    /** The order's contact details and addresses were anonymised; its amounts, tax and country stay. */
    case Anonymised = 'anonymised';
}
