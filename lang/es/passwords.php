<?php

declare(strict_types=1);

return [
    'reset' => 'Su contraseña ha sido restablecida.',
    'sent' => 'Le hemos enviado por correo electrónico el enlace para restablecer su contraseña.',
    'throttled' => 'Espere antes de volver a intentarlo.',
    'token' => 'Este token de restablecimiento de contraseña no es válido.',
    'user' => 'No podemos encontrar un usuario con esa dirección de correo electrónico.',
    'notification' => [
        'subject' => 'Restablece tu contraseña :app',
        'reason' => 'Está recibiendo este correo electrónico porque recibimos una solicitud de restablecimiento de contraseña para su cuenta.',
        'action' => 'Elija una nueva contraseña',
        'expiry' => 'Este enlace caduca en :minutes minutos.',
        'ignore' => 'Si no solicitó restablecer su contraseña, puede ignorar este correo electrónico; Tu contraseña sigue siendo la misma.',
    ],
];
