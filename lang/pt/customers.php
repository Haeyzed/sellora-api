<?php

declare(strict_types=1);

return [
    'email_verification' => [
        'subject' => 'Confirme seu endereço de e-mail',
        'reason' => 'Confirme que este é o seu endereço de e-mail, para que você possa ver seus pedidos anteriores e baixar seus dados.',
        'action' => 'Confirmar meu e-mail',
        'expiry' => 'Este link funciona uma vez e expira em :hours horas.',
        'ignore' => 'Se você não criou uma conta, pode ignorar este e-mail.',
    ],
    'email_change' => [
        'subject' => 'Confirme seu novo endereço de e-mail',
        'reason' => 'Você pediu para usar este endereço para sua conta. Abra este link enquanto estiver conectado para fazer a alteração.',
        'action' => 'Use este e-mail',
        'expiry' => 'Este link funciona uma vez e expira em :minutes minutos.',
        'ignore' => 'Se você não solicitou isso, pode ignorar este e-mail; nada muda.',
    ],
    'marketing_consent' => [
        'email' => 'Envie-me novidades e ofertas de :store por email. Posso cancelar a assinatura a qualquer momento.',
        'sms' => 'Envie-me novidades e ofertas de :store por mensagem de texto. Posso cancelar a assinatura a qualquer momento.',
    ],
    'email_changed' => [
        'subject' => 'O endereço de e-mail da sua conta foi alterado',
        'reason' => 'O endereço de e-mail da sua conta foi alterado e todos os outros dispositivos foram desconectados. Os e-mails sobre sua conta agora vão para o novo endereço.',
        'help' => 'Se você não fez essa alteração, entre em contato com a loja imediatamente.',
    ],
];
