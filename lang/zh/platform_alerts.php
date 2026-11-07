<?php

declare(strict_types=1);

return [
    'exchange_rates_overdue' => [
        'title' => '汇率已 :hours 小时未更新',
        'message' => '至少 :hours 小时内没有成功获取汇率。汇率超过 :stale_hours 小时后，店铺将停止提供其额外货币。请检查汇率提供方。',
    ],
    'store_setup_failed' => [
        'title' => '店铺 :store 设置失败',
        'message' => '店铺 :store（:store_id）在所有重试后设置失败。原因修复后，请在店铺列表中重试。',
    ],
    'stock_levels_mismatch' => [
        'title' => ':store 的库存水平与其台账不符',
        'message' => '定期检查发现店铺 :store（:store_id）中有库存水平与其库存变动台账不符（:count）。详情见检查日志。',
    ],
    'invoice_balances_mismatch' => [
        'title' => ':store 的发票与保留的销售额不符',
        'message' => '定期检查发现店铺 :store（:store_id）中有发票的贷项通知单与保留的销售款或开票税额不符（:count）。详情见检查日志。',
    ],
    'see_dashboard' => '登录 :app 平台控制台了解更多。',
];
