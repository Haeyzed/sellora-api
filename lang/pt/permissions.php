<?php

declare(strict_types=1);

return [
    'legal_documents' => [
        'manage' => 'Elabore, edite e publique os documentos legais aceitos pelos comerciantes, como os termos de serviço.',
    ],
    'stores' => [
        'view' => 'Veja cada loja, seu status, plano, concessões de recursos e substituições de limites.',
        'manage' => 'Suspenda, reative, feche e restaure lojas e tente configurar novamente uma loja cuja configuração falhou.',
        'grant' => 'Dê às lojas módulos e integrações fora do plano e altere seus limites de uso.',
        'export' => 'Exporte todos os dados de uma loja, incluindo os dados pessoais de cada cliente.',
    ],
    'staff' => [
        'view' => 'Veja a equipe: membros da equipe, suas funções e convites pendentes.',
        'invite' => 'Convide pessoas para ingressar na equipe e reenvie ou cancele convites.',
        'manage' => 'Altere as funções dos membros da equipe e desative-os ou reative-os.',
    ],
    'catalog' => [
        'view' => 'Veja no catálogo: marcas, categorias e produtos, incluindo rascunhos, itens arquivados e lixo.',
        'manage' => 'Crie e altere marcas, categorias e produtos, mova-os para a lixeira e restaure-os.',
    ],
    'inventory' => [
        'view' => 'Veja os níveis de estoque, locais e o histórico de cada alteração de estoque.',
        'adjust' => 'Receba estoque, corrija contagens de estoque e altere a forma como o estoque de cada variante é tratado.',
        'costs' => 'Veja e altere quanto custa cada unidade para a loja. Os custos são internos e nunca mostrados aos clientes.',
    ],
    'customers' => [
        'view' => 'Veja clientes, seus perfis, endereços, notas de funcionários e grupos, incluindo contas desativadas e descartadas.',
        'manage' => 'Altere perfis de clientes, desative, reative, descarte e restaure contas, adicione ou exclua notas e gerencie grupos de clientes.',
        'tax_exemption' => 'Tornar um cliente isento de impostos, ou encerrá-lo, após verificar seu número de identificação fiscal.',
        'privacy' => 'Baixe todos os dados pessoais de um cliente ou apague-os definitivamente, conforme solicitação do cliente.',
    ],
    'promotions' => [
        'view' => 'Veja as promoções, seus códigos e a frequência com que foram utilizadas, além de visualizar o que uma cesta receberia.',
        'manage' => 'Crie, altere, ative, desative e arquive promoções, e adicione, gere ou altere seus códigos.',
    ],
    'shipping' => [
        'view' => 'Veja zonas de envio, métodos e suas taxas e visualize quanto uma cesta pagaria pelo frete.',
        'manage' => 'Crie, altere e exclua zonas de envio e crie, altere e arquive métodos de envio e suas taxas.',
    ],
    'orders' => [
        'view' => 'Consulte pedidos, suas linhas, pagamentos, histórico e remessas.',
        'manage' => 'Cancele pedidos, adicione notas e etiquetas e corrija os detalhes de contato e endereços de entrega dos compradores.',
        'fulfil' => 'Crie remessas, adicione rastreamento e marque o que sobrou, está pronto para retirada ou chegou.',
    ],
    'invoices' => [
        'view' => 'Veja faturas e notas de crédito com os detalhes do comprador e baixe seus PDFs.',
    ],
    'payments' => [
        'view' => 'Veja pagamentos, reembolsos e como os gateways de pagamento estão conectados.',
        'confirm' => 'Confirme se uma transferência bancária chegou ou se o dinheiro na entrega foi coletado e pergunte novamente ao gateway sobre um pagamento.',
        'refund' => 'Reembolse pagamentos, através do gateway ou registrados manualmente.',
        'manage_gateways' => 'Conecte, desconecte e configure gateways e métodos de pagamento.',
    ],
    'pricing' => [
        'view' => 'Veja classes de impostos, alíquotas de impostos, em qual classe cada variante está inserida e as moedas e taxas de câmbio da loja.',
        'manage' => 'Altere as classes fiscais, as taxas de imposto, as classes fiscais das variantes, as moedas da loja, as taxas de câmbio e os preços fixos.',
    ],
    'settings' => [
        'view' => 'Veja as configurações da loja: nome, país, moeda, idiomas, modalidade tributária, unidades e dados de contato.',
        'manage' => 'Altere as configurações da loja. A exigência de autenticação de dois fatores para a equipe fica com o proprietário.',
    ],
    'roles' => [
        'view' => 'Veja as funções da loja e o que cada uma permite.',
        'manage' => 'Crie, renomeie e exclua funções e escolha o que cada uma permite.',
    ],
    'delivery' => [
        'view' => 'Ver os motoristas e as entregas da loja.',
        'manage' => 'Dar envios aos motoristas e fazer avançar, repetir, recolher ou cancelar as suas entregas.',
        'cash' => [
            'manage' => 'Registar o dinheiro que os motoristas entregam, nunca mais do que têm.',
        ],
    ],
    'drivers' => [
        'manage' => 'Adicionar, alterar, desativar e apagar motoristas, e dar-lhes códigos para escolher um PIN.',
    ],
];
