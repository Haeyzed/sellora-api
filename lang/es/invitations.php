<?php

declare(strict_types=1);

return [
    'staff' => [
        'subject' => ':inviter te invitó a unirte al equipo de su tienda.',
        'reason' => ':inviter te invitó a ayudar a administrar su tienda. Acepta elegir tu contraseña e inicia sesión.',
        'action' => 'acepta la invitación',
        'expiry' => 'Este enlace funciona una vez y caduca en :days días.',
        'ignore' => 'Si no esperaba esto, puede ignorar este correo electrónico; no se crea ninguna cuenta.',
    ],
    'platform_admin' => [
        'subject' => ':inviter te invitó a unirte al equipo de :app',
        'reason' => ':inviter te invitó a ayudar a administrar la plataforma :app. Acepta para elegir tu contraseña.',
        'action' => 'acepta la invitación',
        'expiry' => 'Este enlace funciona una vez y caduca en :days días.',
        'two_factor' => 'Necesitará una aplicación de autenticación: se requiere autenticación de dos factores antes de poder usar su cuenta.',
        'ignore' => 'Si no esperaba esto, puede ignorar este correo electrónico; no se crea ninguna cuenta.',
    ],
];
