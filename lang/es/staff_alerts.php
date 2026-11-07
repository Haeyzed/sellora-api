<?php

declare(strict_types=1);

return [
    'new_order' => [
        'title' => 'Nuevo pedido :order',
        'message' => 'Se realizó el pedido :order por :amount.',
    ],
    'payment_needs_review' => [
        'title' => 'Un pago del pedido :order necesita revisión',
        'message' => 'Un pago de :amount del pedido :order no coincidió con lo esperado. Revísalo antes de preparar el pedido.',
    ],
    'payment_unresolved' => [
        'title' => 'Un pago del pedido :order está sin resolver',
        'message' => 'La pasarela no ha liquidado el pago de :amount del pedido :order. Compruébalo con la pasarela.',
    ],
    'payment_overpaid' => [
        'title' => 'El pedido :order se pagó de más',
        'message' => 'El comprador pagó :amount más de lo que cuesta el pedido :order. Reembolsa la diferencia.',
    ],
    'payment_disputed' => [
        'title' => 'Se disputó un pago',
        'message' => 'Un comprador disputó un pago hecho con :gateway. Responde a la disputa en el panel de la pasarela antes de su plazo.',
    ],
    'payment_gateway_needs_reconnecting' => [
        'title' => 'Vuelve a conectar :gateway',
        'message' => ':gateway rechazó las claves de la tienda. Los pagos por esa vía quedan pendientes hasta que la vuelvas a conectar.',
    ],
    'order_paid_after_cancellation' => [
        'title' => 'Se pagó el pedido cancelado :order',
        'message' => 'Llegó un pago para el pedido :order después de cancelarlo. El pedido sigue cancelado; reembolsa el pago.',
    ],
    'order_refunded_beyond_paid' => [
        'title' => 'El pedido :order se reembolsó por encima de lo pagado',
        'message' => 'Los reembolsos del pedido :order suman más de lo que pagó el comprador. Revisa los reembolsos del pedido.',
    ],
    'refund_needs_review' => [
        'title' => 'Un reembolso del pedido :order necesita revisión',
        'message' => 'El reembolso de :amount del pedido :order necesita revisión: la respuesta de la pasarela no fue clara.',
    ],
    'refund_failed' => [
        'title' => 'Falló un reembolso del pedido :order',
        'message' => 'El reembolso de :amount del pedido :order falló. Reembolsa al comprador de otra forma o inténtalo de nuevo.',
    ],
    'refund_still_pending' => [
        'title' => 'Un reembolso del pedido :order sigue pendiente',
        'message' => 'El reembolso de :amount del pedido :order lleva días esperando en la pasarela. Compruébalo con la pasarela.',
    ],
    'refund_succeeded_after_failing' => [
        'title' => 'Un reembolso fallido del pedido :order se completó',
        'message' => 'El reembolso de :amount del pedido :order, que se dio por fallido, se completó al final. Si volviste a reembolsar, el comprador recibió el reembolso dos veces.',
    ],
    'payment_hold_keeps_failing' => [
        'title' => 'No se puede liquidar un pago del pedido :order',
        'message' => 'La liquidación del pago :method de :amount del pedido :order sigue fallando. Se reintenta cada hora.',
    ],
    'order_payments_keep_failing' => [
        'title' => 'No se pueden registrar los pagos del pedido :order',
        'message' => 'El registro de los pagos del pedido :order sigue fallando. Se reintenta cada hora.',
    ],
    'committed_payment_refund_failed' => [
        'title' => 'No se puede devolver el dinero del pedido cancelado :order',
        'message' => 'No se pudo devolver el pago :method de :amount del pedido cancelado :order. Se reintenta cada hora, y el pedido sigue debiendo el reembolso.',
    ],
    'exchange_settlement_refused' => [
        'title' => 'No se liquidó un cambio del pedido :order',
        'message' => 'Se pagó el pedido de reemplazo :order, pero se rechazó la liquidación de su devolución (:reason). Vuelve a liquidarla desde la devolución.',
    ],
    'cash_collection_overdue' => [
        'title' => 'No se ha registrado el efectivo del pedido :order',
        'message' => 'El pedido :order se entregó, pero los :amount en efectivo no se han registrado como cobrados.',
    ],
    'delivery_failed' => [
        'title' => 'Falló una entrega del pedido :order',
        'message' => 'El repartidor no pudo entregar el pedido :order. Organiza otra entrega o contacta con el comprador.',
    ],
    'stock_running_low' => [
        'title' => 'Quedan pocas unidades de :product',
        'message' => 'Solo quedan :available de :product (SKU :sku).',
    ],
    'variant_sold_out' => [
        'title' => ':product está agotado',
        'message' => ':product (SKU :sku) está agotado.',
    ],
    'return_requested' => [
        'title' => 'Devolución solicitada del pedido :order',
        'message' => 'Un comprador pidió devolver artículos del pedido :order.',
    ],
    'waiting' => [
        'title' => 'Hay más alertas esperando',
        'message' => 'En esta hora llegaron más alertas de las que se envían por correo. Consúltalas todas en tu panel.',
    ],
    'see_dashboard' => 'Consúltala en el panel de tu tienda.',
];
