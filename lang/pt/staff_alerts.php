<?php

declare(strict_types=1);

return [
    'new_order' => [
        'title' => 'Novo pedido :order',
        'message' => 'O pedido :order foi feito no valor de :amount.',
    ],
    'payment_needs_review' => [
        'title' => 'Um pagamento do pedido :order precisa de revisão',
        'message' => 'Um pagamento de :amount do pedido :order não correspondeu ao esperado. Verifique-o antes de preparar o pedido.',
    ],
    'payment_unresolved' => [
        'title' => 'Um pagamento do pedido :order não foi resolvido',
        'message' => 'O gateway não concluiu o pagamento de :amount do pedido :order. Verifique-o com o gateway.',
    ],
    'payment_overpaid' => [
        'title' => 'O pedido :order foi pago a mais',
        'message' => 'O comprador pagou :amount a mais do que custa o pedido :order. Reembolse a diferença.',
    ],
    'payment_disputed' => [
        'title' => 'Um pagamento foi contestado',
        'message' => 'Um comprador contestou um pagamento feito por :gateway. Responda à contestação no painel do gateway antes do prazo.',
    ],
    'payment_gateway_needs_reconnecting' => [
        'title' => 'Reconecte :gateway',
        'message' => ':gateway recusou as chaves da loja. Os pagamentos por ele ficam pendentes até que o reconecte.',
    ],
    'order_paid_after_cancellation' => [
        'title' => 'O pedido cancelado :order foi pago',
        'message' => 'Chegou um pagamento para o pedido :order depois de ser cancelado. O pedido continua cancelado; reembolse o pagamento.',
    ],
    'order_refunded_beyond_paid' => [
        'title' => 'O pedido :order foi reembolsado acima do valor pago',
        'message' => 'Os reembolsos do pedido :order somam mais do que o comprador pagou. Verifique os reembolsos do pedido.',
    ],
    'refund_needs_review' => [
        'title' => 'Um reembolso do pedido :order precisa de revisão',
        'message' => 'O reembolso de :amount do pedido :order precisa de verificação: a resposta do gateway não foi clara.',
    ],
    'refund_failed' => [
        'title' => 'Um reembolso do pedido :order falhou',
        'message' => 'O reembolso de :amount do pedido :order falhou. Reembolse o comprador de outra forma ou tente novamente.',
    ],
    'refund_still_pending' => [
        'title' => 'Um reembolso do pedido :order continua pendente',
        'message' => 'O reembolso de :amount do pedido :order está há dias à espera no gateway. Verifique-o com o gateway.',
    ],
    'refund_succeeded_after_failing' => [
        'title' => 'Um reembolso falhado do pedido :order foi concluído',
        'message' => 'O reembolso de :amount do pedido :order, dado como falhado, acabou por ser concluído. Se reembolsou novamente, o comprador foi reembolsado duas vezes.',
    ],
    'payment_hold_keeps_failing' => [
        'title' => 'Não é possível concluir um pagamento do pedido :order',
        'message' => 'A conclusão do pagamento :method de :amount do pedido :order continua a falhar. É tentada de novo a cada hora.',
    ],
    'order_payments_keep_failing' => [
        'title' => 'Não é possível registar os pagamentos do pedido :order',
        'message' => 'O registo dos pagamentos do pedido :order continua a falhar. É tentado de novo a cada hora.',
    ],
    'committed_payment_refund_failed' => [
        'title' => 'Não é possível devolver o dinheiro do pedido cancelado :order',
        'message' => 'Não foi possível devolver o pagamento :method de :amount do pedido cancelado :order. É tentado de novo a cada hora, e o pedido continua a dever o reembolso.',
    ],
    'exchange_settlement_refused' => [
        'title' => 'Uma troca do pedido :order não foi liquidada',
        'message' => 'O pedido de substituição :order foi pago, mas a liquidação da sua devolução foi recusada (:reason). Liquide-a novamente a partir da devolução.',
    ],
    'cash_collection_overdue' => [
        'title' => 'O dinheiro do pedido :order não foi registado',
        'message' => 'O pedido :order foi entregue, mas os :amount em dinheiro não foram registados como recebidos.',
    ],
    'delivery_failed' => [
        'title' => 'Uma entrega do pedido :order falhou',
        'message' => 'O estafeta não conseguiu entregar o pedido :order. Organize outra entrega ou contacte o comprador.',
    ],
    'stock_running_low' => [
        'title' => 'O stock de :product está a acabar',
        'message' => 'Restam apenas :available de :product (SKU :sku).',
    ],
    'variant_sold_out' => [
        'title' => ':product esgotou',
        'message' => ':product (SKU :sku) esgotou.',
    ],
    'return_requested' => [
        'title' => 'Devolução pedida para o pedido :order',
        'message' => 'Um comprador pediu para devolver artigos do pedido :order.',
    ],
    'waiting' => [
        'title' => 'Há mais alertas à sua espera',
        'message' => 'Chegaram nesta hora mais alertas do que os enviados por e-mail. Veja-os todos no seu painel.',
    ],
    'see_dashboard' => 'Veja-o no painel da sua loja.',
];
