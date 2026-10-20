<?php

declare(strict_types=1);

return [
    'offer' => [
        'subject' => ':owner 希望让您成为他们商店的所有者',
        'reason' => ':owner 已要求您接任商店的所有者。作为店主，您可以在商店中做任何事情，商店与 Sellora 的合同就归您所有。',
        'terms' => '要接受，请登录商店仪表板并接受 Sellora 的当前服务条款。在你这样做之前，一切都不会改变。',
        'action' => '审查转移',
        'expiry' => '此优惠将在 :hours 小时后过期。',
        'ignore' => '如果您不想接管商店，请忽略这封电子邮件。',
    ],
    'completed' => [
        'subject' => ':store 商店有了新主人',
        'new_owner' => '您现在是商店 :store 的所有者。',
        'previous_owner' => ':owner 现在是商店 :store 的所有者。您仅保留开始转移时选择的角色。',
        'not_you' => '如果您没想到会出现这种情况，请立即联系 Sellora 支持人员。',
    ],
];
