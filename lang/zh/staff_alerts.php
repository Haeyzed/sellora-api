<?php

declare(strict_types=1);

return [
    'new_order' => [
        'title' => '新订单 :order',
        'message' => '订单 :order 已下单，金额 :amount。',
    ],
    'payment_needs_review' => [
        'title' => '订单 :order 的一笔付款需要核查',
        'message' => '订单 :order 的一笔 :amount 付款与预期不符。请在处理订单前核查。',
    ],
    'payment_unresolved' => [
        'title' => '订单 :order 的一笔付款尚未解决',
        'message' => '支付网关尚未完成订单 :order 的 :amount 付款。请向网关核实。',
    ],
    'payment_overpaid' => [
        'title' => '订单 :order 多付了款',
        'message' => '买家比订单 :order 的金额多付了 :amount。请退还差额。',
    ],
    'payment_disputed' => [
        'title' => '一笔付款被提出争议',
        'message' => '一位买家对通过 :gateway 的付款提出了争议。请在截止日期前在网关后台回复。',
    ],
    'payment_gateway_needs_reconnecting' => [
        'title' => '请重新连接 :gateway',
        'message' => ':gateway 拒绝了店铺的密钥。在重新连接之前，通过它的付款将保持待处理。',
    ],
    'order_paid_after_cancellation' => [
        'title' => '已取消的订单 :order 收到付款',
        'message' => '订单 :order 取消后收到一笔付款。订单仍为已取消；请退还该付款。',
    ],
    'order_refunded_beyond_paid' => [
        'title' => '订单 :order 的退款超过了已付金额',
        'message' => '订单 :order 的退款总额超过了买家支付的金额。请核查该订单的退款。',
    ],
    'refund_needs_review' => [
        'title' => '订单 :order 的一笔退款需要核查',
        'message' => '订单 :order 的 :amount 退款需要核查：网关的回复不明确。',
    ],
    'refund_failed' => [
        'title' => '订单 :order 的一笔退款失败',
        'message' => '订单 :order 的 :amount 退款失败。请通过其他方式退款给买家，或重试。',
    ],
    'refund_still_pending' => [
        'title' => '订单 :order 的一笔退款仍在处理中',
        'message' => '订单 :order 的 :amount 退款已在网关等待数天。请向网关核实。',
    ],
    'refund_succeeded_after_failing' => [
        'title' => '订单 :order 的失败退款已完成',
        'message' => '订单 :order 被报告为失败的 :amount 退款最终完成了。如果您再次退款，买家已收到两次退款。',
    ],
    'payment_hold_keeps_failing' => [
        'title' => '订单 :order 的一笔付款无法结算',
        'message' => '订单 :order 的 :amount :method 付款结算持续失败。每小时会重试一次。',
    ],
    'order_payments_keep_failing' => [
        'title' => '订单 :order 的付款无法记录',
        'message' => '订单 :order 的付款记录持续失败。每小时会重试一次。',
    ],
    'committed_payment_refund_failed' => [
        'title' => '已取消订单 :order 的款项无法退回',
        'message' => '已取消订单 :order 的 :amount :method 付款未能退回。每小时会重试一次，该订单仍欠退款。',
    ],
    'exchange_settlement_refused' => [
        'title' => '订单 :order 的一次换货未结算',
        'message' => '替换订单 :order 已付款，但其退货的结算被拒绝（:reason）。请从退货中重新结算。',
    ],
    'cash_collection_overdue' => [
        'title' => '订单 :order 的现金未记录',
        'message' => '订单 :order 已送达，但 :amount 现金尚未记录为已收取。',
    ],
    'delivery_failed' => [
        'title' => '订单 :order 配送失败',
        'message' => '司机未能配送订单 :order。请安排再次配送或联系买家。',
    ],
    'stock_running_low' => [
        'title' => ':product 库存不足',
        'message' => ':product（SKU :sku）只剩 :available 件。',
    ],
    'variant_sold_out' => [
        'title' => ':product 已售罄',
        'message' => ':product（SKU :sku）已售罄。',
    ],
    'return_requested' => [
        'title' => '订单 :order 申请退货',
        'message' => '一位买家申请退回订单 :order 中的商品。',
    ],
    'waiting' => [
        'title' => '还有更多提醒等待查看',
        'message' => '本小时收到的提醒超过了邮件发送的数量。请在后台查看全部提醒。',
    ],
    'see_dashboard' => '请在店铺后台查看。',
];
