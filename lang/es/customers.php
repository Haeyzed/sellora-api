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
    'social_link' => [
        'subject' => 'Alguien está conectando un inicio de sesión a tu cuenta',
        'reason' => 'Alguien inició sesión con :provider usando esta dirección de correo y pidió conectarla a tu cuenta. Confirma solo si eras tú: una vez conectada, iniciar sesión con :provider abrirá tu cuenta.',
        'action' => 'Sí, conectar :provider',
        'expiry' => 'Este enlace funciona una vez y caduca en :minutes minutos.',
        'ignore' => 'Si no eras tú, ignora este correo: no hay nada conectado y tu cuenta sigue igual.',
    ],
    'erasure' => [
        'subject' => 'Confirma la eliminación de tu cuenta',
        'reason' => 'Solicitaste borrar tu cuenta en esta tienda. Abre este enlace mientras estás conectado para confirmar: todo lo que la tienda guarda sobre ti se elimina y esto no se puede deshacer.',
        'action' => 'Eliminar mi cuenta',
        'expiry' => 'Este enlace funciona una vez y caduca en :minutes minutos.',
        'ignore' => 'Si no solicitaste esto, ignora este correo: no se borra nada. Otra persona puede haber iniciado sesión en tu cuenta, así que cierra sesión en tus otros dispositivos.',
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
