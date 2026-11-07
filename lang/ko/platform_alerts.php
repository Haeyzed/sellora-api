<?php

declare(strict_types=1);

return [
    'exchange_rates_overdue' => [
        'title' => '환율이 :hours시간 동안 갱신되지 않았습니다',
        'message' => '최소 :hours시간 동안 환율 가져오기에 성공하지 못했습니다. 환율이 :stale_hours시간 지나면 스토어는 추가 통화 제공을 중단합니다. 환율 제공업체를 확인하세요.',
    ],
    'store_setup_failed' => [
        'title' => ':store 스토어 설정에 실패했습니다',
        'message' => ':store(:store_id) 스토어 설정이 모든 재시도 후 실패했습니다. 원인을 해결한 후 스토어 목록에서 다시 시도하세요.',
    ],
    'stock_levels_mismatch' => [
        'title' => ':store의 재고 수준이 원장과 일치하지 않습니다',
        'message' => '예약된 점검에서 :store(:store_id) 스토어의 재고 수준이 재고 이동 원장과 일치하지 않는 것을 발견했습니다(:count). 자세한 내용은 점검 로그에 있습니다.',
    ],
    'invoice_balances_mismatch' => [
        'title' => ':store의 송장이 보관된 판매와 일치하지 않습니다',
        'message' => '예약된 점검에서 :store(:store_id) 스토어에 대변 메모가 보관된 판매 금액 또는 청구된 세금과 맞지 않는 송장을 발견했습니다(:count). 자세한 내용은 점검 로그에 있습니다.',
    ],
    'see_dashboard' => '자세한 내용은 :app 플랫폼 대시보드에 로그인하세요.',
];
