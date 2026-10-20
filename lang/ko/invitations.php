<?php

declare(strict_types=1);

return [
    'staff' => [
        'subject' => ':inviter님이 매장 팀에 합류하도록 귀하를 초대했습니다.',
        'reason' => ':inviter님이 매장 운영을 돕기 위해 귀하를 초대했습니다. 비밀번호를 선택하고 로그인하려면 동의하세요.',
        'action' => '초대를 수락하세요',
        'expiry' => '이 링크는 한 번 작동하며 :days일 후에 만료됩니다.',
        'ignore' => '이를 예상하지 못했다면 이 이메일을 무시해도 됩니다. 계정이 생성되지 않습니다.',
    ],
    'platform_admin' => [
        'subject' => ':inviter님이 귀하를 Sellora 팀에 합류하도록 초대했습니다.',
        'reason' => ':inviter님이 Sellora 플랫폼 운영을 돕기 위해 귀하를 초대했습니다. 비밀번호를 선택하려면 동의하세요.',
        'action' => '초대를 수락하세요',
        'expiry' => '이 링크는 한 번 작동하며 :days일 후에 만료됩니다.',
        'two_factor' => '인증 앱이 필요합니다. 계정을 사용하려면 2단계 인증이 필요합니다.',
        'ignore' => '이를 예상하지 못했다면 이 이메일을 무시해도 됩니다. 계정이 생성되지 않습니다.',
    ],
];
