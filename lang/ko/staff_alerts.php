<?php

declare(strict_types=1);

return [
    'new_order' => [
        'title' => '새 주문 :order',
        'message' => '주문 :order이(가) :amount에 접수되었습니다.',
    ],
    'payment_needs_review' => [
        'title' => '주문 :order의 결제를 확인해야 합니다',
        'message' => '주문 :order의 :amount 결제가 예상과 일치하지 않았습니다. 주문을 처리하기 전에 확인하세요.',
    ],
    'payment_unresolved' => [
        'title' => '주문 :order의 결제가 해결되지 않았습니다',
        'message' => '결제 게이트웨이가 주문 :order의 :amount 결제를 완료하지 않았습니다. 게이트웨이에서 확인하세요.',
    ],
    'payment_overpaid' => [
        'title' => '주문 :order이(가) 초과 결제되었습니다',
        'message' => '구매자가 주문 :order 금액보다 :amount 더 결제했습니다. 차액을 환불하세요.',
    ],
    'payment_disputed' => [
        'title' => '결제에 이의가 제기되었습니다',
        'message' => '구매자가 :gateway를 통한 결제에 이의를 제기했습니다. 기한 전에 게이트웨이 대시보드에서 답변하세요.',
    ],
    'payment_gateway_needs_reconnecting' => [
        'title' => ':gateway 다시 연결',
        'message' => ':gateway가 스토어의 키를 거부했습니다. 다시 연결할 때까지 이를 통한 결제는 보류됩니다.',
    ],
    'order_paid_after_cancellation' => [
        'title' => '취소된 주문 :order이(가) 결제되었습니다',
        'message' => '주문 :order이(가) 취소된 후 결제가 도착했습니다. 주문은 취소 상태로 유지되며, 결제를 환불하세요.',
    ],
    'order_refunded_beyond_paid' => [
        'title' => '주문 :order에서 결제액보다 많이 환불되었습니다',
        'message' => '주문 :order의 환불 합계가 구매자가 결제한 금액을 넘습니다. 주문의 환불을 확인하세요.',
    ],
    'refund_needs_review' => [
        'title' => '주문 :order의 환불을 확인해야 합니다',
        'message' => '주문 :order의 :amount 환불을 확인해야 합니다. 게이트웨이의 응답이 명확하지 않았습니다.',
    ],
    'refund_failed' => [
        'title' => '주문 :order의 환불이 실패했습니다',
        'message' => '주문 :order의 :amount 환불이 실패했습니다. 다른 방법으로 환불하거나 다시 시도하세요.',
    ],
    'refund_still_pending' => [
        'title' => '주문 :order의 환불이 아직 보류 중입니다',
        'message' => '주문 :order의 :amount 환불이 며칠째 게이트웨이에서 대기 중입니다. 게이트웨이에서 확인하세요.',
    ],
    'refund_succeeded_after_failing' => [
        'title' => '주문 :order의 실패한 환불이 완료되었습니다',
        'message' => '실패로 보고된 주문 :order의 :amount 환불이 결국 완료되었습니다. 다시 환불했다면 구매자는 두 번 환불받았습니다.',
    ],
    'payment_hold_keeps_failing' => [
        'title' => '주문 :order의 결제를 정산할 수 없습니다',
        'message' => '주문 :order의 :amount :method 결제 정산이 계속 실패하고 있습니다. 매시간 다시 시도합니다.',
    ],
    'order_payments_keep_failing' => [
        'title' => '주문 :order의 결제를 기록할 수 없습니다',
        'message' => '주문 :order의 결제 기록이 계속 실패하고 있습니다. 매시간 다시 시도합니다.',
    ],
    'committed_payment_refund_failed' => [
        'title' => '취소된 주문 :order의 금액을 돌려줄 수 없습니다',
        'message' => '취소된 주문 :order의 :amount :method 결제를 돌려주지 못했습니다. 매시간 다시 시도하며, 주문의 환불은 아직 남아 있습니다.',
    ],
    'exchange_settlement_refused' => [
        'title' => '주문 :order의 교환이 정산되지 않았습니다',
        'message' => '교환 주문 :order은(는) 결제되었지만 반품 정산이 거부되었습니다(:reason). 반품에서 다시 정산하세요.',
    ],
    'cash_collection_overdue' => [
        'title' => '주문 :order의 현금이 기록되지 않았습니다',
        'message' => '주문 :order이(가) 배송되었지만 현금 :amount이(가) 수금된 것으로 기록되지 않았습니다.',
    ],
    'delivery_failed' => [
        'title' => '주문 :order의 배송이 실패했습니다',
        'message' => '기사가 주문 :order을(를) 배송하지 못했습니다. 다른 배송을 준비하거나 구매자에게 연락하세요.',
    ],
    'stock_running_low' => [
        'title' => ':product 재고가 부족합니다',
        'message' => ':product(SKU :sku)이(가) :available개만 남았습니다.',
    ],
    'variant_sold_out' => [
        'title' => ':product 품절',
        'message' => ':product(SKU :sku)이(가) 품절되었습니다.',
    ],
    'return_requested' => [
        'title' => '주문 :order 반품 요청',
        'message' => '구매자가 주문 :order의 상품 반품을 요청했습니다.',
    ],
    'waiting' => [
        'title' => '확인할 알림이 더 있습니다',
        'message' => '이번 시간에 이메일로 보내는 수보다 많은 알림이 왔습니다. 대시보드에서 모두 확인하세요.',
    ],
    'see_dashboard' => '스토어 대시보드에서 확인하세요.',
];
