<?php

declare(strict_types=1);

return [
    'staff' => [
        'subject' => ':inviter convidou você para se juntar à equipe da loja',
        'reason' => ':inviter convidou você para ajudar a administrar a loja deles. Aceite escolher sua senha e fazer login.',
        'action' => 'Aceite o convite',
        'expiry' => 'Este link funciona uma vez e expira em :days dias.',
        'ignore' => 'Se você não esperava por isso, pode ignorar este e-mail; nenhuma conta é criada.',
    ],
    'platform_admin' => [
        'subject' => ':inviter convidou você para se juntar à equipe :app',
        'reason' => ':inviter convidou você para ajudar a administrar a plataforma :app. Aceite escolher sua senha.',
        'action' => 'Aceite o convite',
        'expiry' => 'Este link funciona uma vez e expira em :days dias.',
        'two_factor' => 'Você precisará de um aplicativo autenticador: a autenticação de dois fatores é necessária antes de poder usar sua conta.',
        'ignore' => 'Se você não esperava por isso, pode ignorar este e-mail; nenhuma conta é criada.',
    ],
];
