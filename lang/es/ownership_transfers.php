<?php

declare(strict_types=1);

return [
    'offer' => [
        'subject' => ':owner quiere hacerte dueño de su tienda',
        'reason' => ':owner te ha pedido que asumas el cargo de propietario de la tienda. Como propietario, puedes hacer todo en la tienda y el contrato de la tienda con Sellora pasa a ser tuyo.',
        'terms' => 'Para aceptar, inicie sesión en el panel de la tienda y acepte los términos de servicio actuales de Sellora. Nada cambia hasta que lo hagas.',
        'action' => 'Revisar la transferencia',
        'expiry' => 'Esta oferta vence en :hours horas.',
        'ignore' => 'Si no quieres hacerte cargo de la tienda, ignora este correo electrónico.',
    ],
    'completed' => [
        'subject' => 'La tienda :store tiene nuevo dueño',
        'new_owner' => 'Ahora eres el propietario de la tienda :store.',
        'previous_owner' => ':owner ahora es el propietario de la tienda :store. Solo conservas los roles que elegiste cuando iniciaste la transferencia.',
        'not_you' => 'Si no esperaba esto, comuníquese con el soporte de Sellora de inmediato.',
    ],
];
