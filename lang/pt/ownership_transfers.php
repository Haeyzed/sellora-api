<?php

declare(strict_types=1);

return [
    'offer' => [
        'subject' => ':owner quer fazer de você o dono da loja deles',
        'reason' => ':owner pediu que você assumisse o cargo de proprietário da loja. Como proprietário você pode fazer tudo na loja, e o contrato da loja com a Sellora passa a ser seu.',
        'terms' => 'Para aceitar, faça login no painel da loja e aceite os termos de serviço atuais do Sellora. Nada muda até que você faça isso.',
        'action' => 'Revise a transferência',
        'expiry' => 'Esta oferta expira em :hours horas.',
        'ignore' => 'Se você não quiser assumir o controle da loja, ignore este e-mail.',
    ],
    'completed' => [
        'subject' => 'A loja :store tem novo dono',
        'new_owner' => 'Agora você é o proprietário da loja :store.',
        'previous_owner' => ':owner agora é o dono da loja :store. Você mantém apenas as funções que escolheu quando iniciou a transferência.',
        'not_you' => 'Se você não esperava por isso, entre em contato com o suporte do Sellora imediatamente.',
    ],
];
