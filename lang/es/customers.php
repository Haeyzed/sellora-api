<?php

declare(strict_types=1);

return [
    'email_verification' => [
        'subject' => 'Confirma tu dirección de correo electrónico',
        'reason' => 'Confirma que esta es tu dirección de correo electrónico, para que puedas ver tus pedidos anteriores y descargar tus datos.',
        'action' => 'Confirmar mi correo electrónico',
        'expiry' => 'Este enlace funciona una vez y caduca en :hours horas.',
        'ignore' => 'Si no creó una cuenta, puede ignorar este correo electrónico.',
    ],
    'email_change' => [
        'subject' => 'Confirma tu nueva dirección de correo electrónico',
        'reason' => 'Solicitó utilizar esta dirección para su cuenta. Abra este enlace mientras está registrado para realizar el cambio.',
        'action' => 'Utilice este correo electrónico',
        'expiry' => 'Este enlace funciona una vez y caduca en :minutes minutos.',
        'ignore' => 'Si no solicitaste esto, puedes ignorar este correo electrónico; nada cambia.',
    ],
    'marketing_consent' => [
        'email' => 'Envíame noticias y ofertas de :store por correo electrónico. Puedo darme de baja en cualquier momento.',
        'sms' => 'Envíame noticias y ofertas de :store por mensaje de texto. Puedo darme de baja en cualquier momento.',
    ],
    'email_changed' => [
        'subject' => 'La dirección de correo electrónico de su cuenta fue cambiada.',
        'reason' => 'Se cambió la dirección de correo electrónico de su cuenta y se cerró la sesión de todos los demás dispositivos. Los correos electrónicos sobre su cuenta ahora van a la nueva dirección.',
        'help' => 'Si no realizó este cambio, comuníquese con la tienda de inmediato.',
    ],
];
