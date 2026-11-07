<?php

declare(strict_types=1);

return [
    'exchange_rates_overdue' => [
        'title' => 'Taxas de câmbio não atualizadas há :hours horas',
        'message' => 'Nenhuma obtenção de taxas de câmbio teve êxito há pelo menos :hours horas. As lojas deixam de oferecer as suas moedas adicionais quando as taxas têm :stale_hours horas. Verifique o fornecedor de taxas de câmbio.',
    ],
    'store_setup_failed' => [
        'title' => 'A configuração da loja :store falhou',
        'message' => 'A configuração da loja :store (:store_id) falhou após todas as tentativas. Tente novamente a partir da lista de lojas quando a causa estiver corrigida.',
    ],
    'stock_levels_mismatch' => [
        'title' => 'Os níveis de stock de :store não coincidem com o seu registo',
        'message' => 'A verificação agendada encontrou níveis de stock na loja :store (:store_id) que não coincidem com o seu registo de movimentos de stock (:count). Os detalhes estão no registo da verificação.',
    ],
    'invoice_balances_mismatch' => [
        'title' => 'As faturas de :store não coincidem com as vendas retidas',
        'message' => 'A verificação agendada encontrou faturas na loja :store (:store_id) cujas notas de crédito não batem com o dinheiro das vendas retido ou com o imposto faturado (:count). Os detalhes estão no registo da verificação.',
    ],
    'see_dashboard' => 'Inicie sessão no painel da plataforma :app para saber mais.',
];
