<?php

declare(strict_types=1);

return [
    'new_order' => [
        'title' => '新しい注文 :order',
        'message' => '注文 :order が :amount で入りました。',
    ],
    'payment_needs_review' => [
        'title' => '注文 :order の支払いの確認が必要です',
        'message' => '注文 :order の :amount の支払いが想定と一致しませんでした。注文を発送する前に確認してください。',
    ],
    'payment_unresolved' => [
        'title' => '注文 :order の支払いが未解決です',
        'message' => '決済ゲートウェイが注文 :order の :amount の支払いを完了していません。ゲートウェイで確認してください。',
    ],
    'payment_overpaid' => [
        'title' => '注文 :order が過払いになりました',
        'message' => '購入者が注文 :order の金額より :amount 多く支払いました。差額を返金してください。',
    ],
    'payment_disputed' => [
        'title' => '支払いに異議が申し立てられました',
        'message' => '購入者が :gateway での支払いに異議を申し立てました。期限までにゲートウェイの管理画面で回答してください。',
    ],
    'payment_gateway_needs_reconnecting' => [
        'title' => ':gateway を再接続してください',
        'message' => ':gateway がストアのキーを拒否しました。再接続するまで、この方法での支払いは保留のままです。',
    ],
    'order_paid_after_cancellation' => [
        'title' => 'キャンセル済みの注文 :order が支払われました',
        'message' => '注文 :order のキャンセル後に支払いが届きました。注文はキャンセルのままです。支払いを返金してください。',
    ],
    'order_refunded_beyond_paid' => [
        'title' => '注文 :order で支払額を超える返金がありました',
        'message' => '注文 :order の返金の合計が購入者の支払額を超えています。注文の返金を確認してください。',
    ],
    'refund_needs_review' => [
        'title' => '注文 :order の返金の確認が必要です',
        'message' => '注文 :order の :amount の返金を確認してください。ゲートウェイの応答が不明確でした。',
    ],
    'refund_failed' => [
        'title' => '注文 :order の返金に失敗しました',
        'message' => '注文 :order の :amount の返金に失敗しました。別の方法で返金するか、もう一度お試しください。',
    ],
    'refund_still_pending' => [
        'title' => '注文 :order の返金がまだ保留中です',
        'message' => '注文 :order の :amount の返金が数日間ゲートウェイで止まっています。ゲートウェイで確認してください。',
    ],
    'refund_succeeded_after_failing' => [
        'title' => '注文 :order の失敗した返金が完了しました',
        'message' => '失敗と報告された注文 :order の :amount の返金が、結局完了しました。再度返金した場合、購入者は二重に返金されています。',
    ],
    'payment_hold_keeps_failing' => [
        'title' => '注文 :order の支払いを確定できません',
        'message' => '注文 :order の :amount の :method 支払いの確定が失敗し続けています。1時間ごとに再試行されます。',
    ],
    'order_payments_keep_failing' => [
        'title' => '注文 :order の支払いを記録できません',
        'message' => '注文 :order の支払いの記録が失敗し続けています。1時間ごとに再試行されます。',
    ],
    'committed_payment_refund_failed' => [
        'title' => 'キャンセル済みの注文 :order の代金を返せません',
        'message' => 'キャンセル済みの注文 :order の :amount の :method 支払いを返せませんでした。1時間ごとに再試行され、注文の返金は未完了のままです。',
    ],
    'exchange_settlement_refused' => [
        'title' => '注文 :order の交換が精算されていません',
        'message' => '交換用の注文 :order は支払われましたが、その返品の精算が拒否されました（:reason）。返品から改めて精算してください。',
    ],
    'cash_collection_overdue' => [
        'title' => '注文 :order の現金が記録されていません',
        'message' => '注文 :order は配達されましたが、現金 :amount の受け取りが記録されていません。',
    ],
    'delivery_failed' => [
        'title' => '注文 :order の配達に失敗しました',
        'message' => 'ドライバーが注文 :order を配達できませんでした。再配達を手配するか、購入者に連絡してください。',
    ],
    'stock_running_low' => [
        'title' => ':product の在庫が少なくなっています',
        'message' => ':product（SKU :sku）の残りは :available だけです。',
    ],
    'variant_sold_out' => [
        'title' => ':product が売り切れました',
        'message' => ':product（SKU :sku）が売り切れました。',
    ],
    'return_requested' => [
        'title' => '注文 :order の返品依頼',
        'message' => '購入者が注文 :order の商品の返品を依頼しました。',
    ],
    'waiting' => [
        'title' => 'ほかのアラートが届いています',
        'message' => 'この1時間に、メールで送る数を超えるアラートが届きました。すべて管理画面で確認してください。',
    ],
    'see_dashboard' => 'ストアの管理画面で確認してください。',
];
