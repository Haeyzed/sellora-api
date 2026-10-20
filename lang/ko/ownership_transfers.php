<?php

declare(strict_types=1);

return [
    'offer' => [
        'subject' => ':owner이(가) 귀하를 매장 소유자로 만들고 싶어합니다.',
        'reason' => ':owner님이 귀하에게 매장 소유자 역할을 맡아달라고 요청했습니다. 소유자로서 귀하는 매장에서 모든 일을 할 수 있으며 Sellora와의 매장 계약은 귀하의 것이 됩니다.',
        'terms' => '수락하려면 매장 대시보드에 로그인하고 Sellora의 현재 서비스 약관에 동의하세요. 그렇게 하기 전까지는 아무것도 바뀌지 않습니다.',
        'action' => '전송 검토',
        'expiry' => '이 제안은 :hours시간 후에 만료됩니다.',
        'ignore' => '매장을 점유하고 싶지 않다면 이 이메일을 무시하세요.',
    ],
    'completed' => [
        'subject' => ':store 매장에 새 소유자가 있습니다',
        'new_owner' => '귀하는 이제 :store 매장의 소유자입니다.',
        'previous_owner' => ':owner은(는) 이제 :store 매장의 소유자입니다. 이전을 시작할 때 선택한 역할만 유지됩니다.',
        'not_you' => '이를 예상하지 못했다면 즉시 Sellora 지원팀에 문의하세요.',
    ],
];
