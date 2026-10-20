<?php

declare(strict_types=1);

return [
    'reset' => '您的密码已重置。',
    'sent' => '我们已通过电子邮件发送您的密码重置链接。',
    'throttled' => '请等待后再重试。',
    'token' => '此密码重置令牌无效。',
    'user' => '我们找不到使用该电子邮件地址的用户。',
    'notification' => [
        'subject' => '重置您的 :app 密码',
        'reason' => '您收到这封电子邮件是因为我们收到了您帐户的密码重置请求。',
        'action' => '选择新密码',
        'expiry' => '此链接将在 :minutes 分钟后过期。',
        'ignore' => '如果您没有要求重置密码，您可以忽略这封电子邮件；您的密码保持不变。',
    ],
];
