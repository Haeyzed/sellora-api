<?php

declare(strict_types=1);

return [
    'exchange_rates_overdue' => [
        'title' => '為替レートが:hours時間更新されていません',
        'message' => '少なくとも:hours時間、為替レートの取得が成功していません。レートが:stale_hours時間古くなると、ストアは追加通貨の提供を停止します。為替レートの提供元を確認してください。',
    ],
    'store_setup_failed' => [
        'title' => 'ストア:storeのセットアップに失敗しました',
        'message' => 'ストア:store（:store_id）のセットアップは、すべての再試行の後に失敗しました。原因を解消したら、ストア一覧から再試行してください。',
    ],
    'stock_levels_mismatch' => [
        'title' => ':storeの在庫数が台帳と一致しません',
        'message' => '定期チェックで、ストア:store（:store_id）に在庫移動台帳と一致しない在庫数が見つかりました（:count）。詳細はチェックのログにあります。',
    ],
    'invoice_balances_mismatch' => [
        'title' => ':storeの請求書が保持された売上と一致しません',
        'message' => '定期チェックで、ストア:store（:store_id）に、クレジットノートが保持された売上金額または請求された税額と合わない請求書が見つかりました（:count）。詳細はチェックのログにあります。',
    ],
    'see_dashboard' => '詳しくは:appのプラットフォームダッシュボードにサインインしてください。',
];
