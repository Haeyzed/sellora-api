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

    /** A payment arrived after the order was cancelled: it stays cancelled, and the payment is owed back. */
    case PaidAfterCancellation = 'paid_after_cancellation';

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

    /** The order's contact details and addresses were anonymised; its amounts, tax and country stay. */
    case Anonymised = 'anonymised';
}
