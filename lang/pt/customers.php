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
    'social_link' => [
        'subject' => 'Alguém está conectando uma forma de login à sua conta',
        'reason' => 'Alguém entrou com :provider usando este endereço de e-mail e pediu para conectá-lo à sua conta. Confirme apenas se foi você: depois de conectado, entrar com :provider abrirá sua conta.',
        'action' => 'Sim, conectar :provider',
        'expiry' => 'Este link funciona uma vez e expira em :minutes minutos.',
        'ignore' => 'Se não foi você, ignore este e-mail: nada é conectado e sua conta continua como está.',
    ],
    'erasure' => [
        'subject' => 'Confirme a exclusão da sua conta',
        'reason' => 'Você pediu para excluir sua conta nesta loja. Abra este link enquanto estiver conectado para confirmar: tudo o que a loja guarda sobre você será excluído e isso não pode ser desfeito.',
        'action' => 'Excluir minha conta',
        'expiry' => 'Este link funciona uma vez e expira em :minutes minutos.',
        'ignore' => 'Se você não pediu isso, ignore este e-mail: nada será excluído. Outra pessoa pode estar conectada à sua conta, então saia dos outros dispositivos.',
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
