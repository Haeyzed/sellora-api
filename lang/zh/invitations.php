<?php

declare(strict_types=1);

return [
    'staff' => [
        'subject' => ':inviter 邀请您加入他们商店的团队',
        'reason' => ':inviter 邀请您帮助经营他们的商店。接受选择您的密码并登录。',
        'action' => '接受邀请',
        'expiry' => '此链接有效一次，并在 :days 天后过期。',
        'ignore' => '如果您没有预料到这一点，您可以忽略这封电子邮件；没有创建帐户。',
    ],
    'platform_admin' => [
        'subject' => ':inviter 邀请您加入 Sellora 团队',
        'reason' => ':inviter 邀请您帮助运行 Sellora 平台。接受选择您的密码。',
        'action' => '接受邀请',
        'expiry' => '此链接有效一次，并在 :days 天后过期。',
        'two_factor' => '您将需要一个身份验证器应用程序：在使用您的帐户之前需要进行两步身份验证。',
        'ignore' => '如果您没有预料到这一点，您可以忽略这封电子邮件；没有创建帐户。',
    ],
];
