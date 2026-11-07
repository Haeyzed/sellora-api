<?php

declare(strict_types=1);

/*
 * Staff alerts (Tenant\Messaging): what needs a staff member's attention,
 * in their alert list and their email. :order is an order number such as
 * "#1042"; :amount is formatted for the language.
 */

return [
    'new_order' => [
        'title' => 'New order :order',
        'message' => 'Order :order was placed for :amount.',
    ],
    'payment_needs_review' => [
        'title' => 'A payment for order :order needs review',
        'message' => 'A payment of :amount for order :order did not match what was expected. Check it before fulfilling the order.',
    ],
    'payment_unresolved' => [
        'title' => 'A payment for order :order is unresolved',
        'message' => 'The gateway has not settled the payment of :amount for order :order. Check it with the gateway.',
    ],
    'payment_overpaid' => [
        'title' => 'Order :order was overpaid',
        'message' => 'The buyer paid :amount more than order :order costs. Refund the difference.',
    ],
    'payment_disputed' => [
        'title' => 'A payment was disputed',
        'message' => 'A buyer disputed a payment made through :gateway. Answer the dispute in the gateway\'s dashboard before its deadline.',
    ],
    'payment_gateway_needs_reconnecting' => [
        'title' => 'Reconnect :gateway',
        'message' => ':gateway refused the store\'s keys. Payments through it stay pending until you reconnect it.',
    ],
    'order_paid_after_cancellation' => [
        'title' => 'Cancelled order :order was paid',
        'message' => 'A payment arrived for order :order after it was cancelled. The order stays cancelled; refund the payment.',
    ],
    'order_refunded_beyond_paid' => [
        'title' => 'Order :order was refunded more than was paid',
        'message' => 'The refunds for order :order add up to more than the buyer paid. Check the order\'s refunds.',
    ],
    'refund_needs_review' => [
        'title' => 'A refund for order :order needs review',
        'message' => 'The refund of :amount for order :order needs checking: the gateway\'s answer was unclear.',
    ],
    'refund_failed' => [
        'title' => 'A refund for order :order failed',
        'message' => 'The refund of :amount for order :order failed. Refund the buyer another way, or try again.',
    ],
    'refund_still_pending' => [
        'title' => 'A refund for order :order is still pending',
        'message' => 'The refund of :amount for order :order has been waiting at the gateway for days. Check it with the gateway.',
    ],
    'refund_succeeded_after_failing' => [
        'title' => 'A failed refund for order :order went through',
        'message' => 'The refund of :amount for order :order, reported as failed, went through after all. If you refunded again, the buyer was refunded twice.',
    ],
    'payment_hold_keeps_failing' => [
        'title' => 'A payment for order :order cannot be settled',
        'message' => 'Settling the :method payment of :amount for order :order keeps failing. It is tried again every hour.',
    ],
    'order_payments_keep_failing' => [
        'title' => 'The payments of order :order cannot be recorded',
        'message' => 'Recording the payments of order :order keeps failing. It is tried again every hour.',
    ],
    'committed_payment_refund_failed' => [
        'title' => 'Money for cancelled order :order cannot be given back',
        'message' => 'The :method payment of :amount for cancelled order :order could not be given back. It is tried again every hour, and the order still owes the refund.',
    ],
    'exchange_settlement_refused' => [
        'title' => 'An exchange for order :order was not settled',
        'message' => 'The replacement order :order was paid, but settling its return was refused (:reason). Settle it again from the return.',
    ],
    'cash_collection_overdue' => [
        'title' => 'Cash for order :order is not recorded',
        'message' => 'Order :order was delivered, but the :amount in cash has not been recorded as collected.',
    ],
    'delivery_failed' => [
        'title' => 'A delivery for order :order failed',
        'message' => 'The driver could not deliver order :order. Arrange another delivery or contact the buyer.',
    ],
    'stock_running_low' => [
        'title' => ':product is running low',
        'message' => 'Only :available left of :product (SKU :sku).',
    ],
    'variant_sold_out' => [
        'title' => ':product is sold out',
        'message' => ':product (SKU :sku) is sold out.',
    ],
    'return_requested' => [
        'title' => 'Return requested for order :order',
        'message' => 'A buyer asked to return items from order :order.',
    ],
    'waiting' => [
        'title' => 'More alerts are waiting',
        'message' => 'More alerts came in this hour than are emailed. See them all in your dashboard.',
    ],
    'see_dashboard' => 'See it in your store dashboard.',
];
