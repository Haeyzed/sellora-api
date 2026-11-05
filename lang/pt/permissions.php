<?php

declare(strict_types=1);

return [
    'legal_documents' => [
        'manage' => [
            'label' => 'Gerenciar documentos legais',
            'description' => 'Elabore, edite e publique os documentos legais aceitos pelos comerciantes, como os termos de serviço.',
        ],
    ],
    'stores' => [
        'view' => [
            'label' => 'Ver lojas',
            'description' => 'Veja cada loja, seu status, plano, concessões de recursos e substituições de limites.',
        ],
        'manage' => [
            'label' => 'Gerenciar lojas',
            'description' => 'Suspenda, reative, feche e restaure lojas e tente configurar novamente uma loja cuja configuração falhou.',
        ],
        'grant' => [
            'label' => 'Conceder recursos e limites',
            'description' => 'Dê às lojas módulos e integrações fora do plano e altere seus limites de uso.',
        ],
        'export' => [
            'label' => 'Exportar dados das lojas',
            'description' => 'Exporte todos os dados de uma loja, incluindo os dados pessoais de cada cliente.',
        ],
    ],
    'staff' => [
        'view' => [
            'label' => 'Ver equipe',
            'description' => 'Veja a equipe: membros da equipe, suas funções e convites pendentes.',
        ],
        'invite' => [
            'label' => 'Convidar funcionários',
            'description' => 'Convide pessoas para ingressar na equipe e reenvie ou cancele convites.',
        ],
        'manage' => [
            'label' => 'Gerenciar funcionários',
            'description' => 'Altere as funções dos membros da equipe e desative-os ou reative-os.',
        ],
    ],
    'catalog' => [
        'view' => [
            'label' => 'Ver catálogo',
            'description' => 'Veja no catálogo: marcas, categorias e produtos, incluindo rascunhos, itens arquivados e lixo.',
        ],
        'manage' => [
            'label' => 'Gerenciar catálogo',
            'description' => 'Crie e altere marcas, categorias e produtos, mova-os para a lixeira e restaure-os.',
        ],
    ],
    'inventory' => [
        'view' => [
            'label' => 'Ver estoque',
            'description' => 'Veja os níveis de estoque, locais e o histórico de cada alteração de estoque.',
        ],
        'adjust' => [
            'label' => 'Ajustar estoque',
            'description' => 'Receba estoque, corrija contagens de estoque e altere a forma como o estoque de cada variante é tratado.',
        ],
        'costs' => [
            'label' => 'Custos unitários',
            'description' => 'Veja e altere quanto custa cada unidade para a loja. Os custos são internos e nunca mostrados aos clientes.',
        ],
    ],
    'customers' => [
        'view' => [
            'label' => 'Ver clientes',
            'description' => 'Veja clientes, seus perfis, endereços, notas de funcionários e grupos, incluindo contas desativadas e descartadas.',
        ],
        'manage' => [
            'label' => 'Gerenciar clientes',
            'description' => 'Altere perfis de clientes, desative, reative, descarte e restaure contas, adicione ou exclua notas e gerencie grupos de clientes.',
        ],
        'tax_exemption' => [
            'label' => 'Isenções de impostos',
            'description' => 'Tornar um cliente isento de impostos, ou encerrá-lo, após verificar seu número de identificação fiscal.',
        ],
        'privacy' => [
            'label' => 'Solicitações de dados de clientes',
            'description' => 'Baixe todos os dados pessoais de um cliente ou apague-os definitivamente, conforme solicitação do cliente.',
        ],
    ],
    'promotions' => [
        'view' => [
            'label' => 'Ver promoções',
            'description' => 'Veja as promoções, seus códigos e a frequência com que foram utilizadas, além de visualizar o que uma cesta receberia.',
        ],
        'manage' => [
            'label' => 'Gerenciar promoções',
            'description' => 'Crie, altere, ative, desative e arquive promoções, e adicione, gere ou altere seus códigos.',
        ],
    ],
    'shipping' => [
        'view' => [
            'label' => 'Ver frete',
            'description' => 'Veja zonas de envio, métodos e suas taxas e visualize quanto uma cesta pagaria pelo frete.',
        ],
        'manage' => [
            'label' => 'Gerenciar frete',
            'description' => 'Crie, altere e exclua zonas de envio e crie, altere e arquive métodos de envio e suas taxas.',
        ],
    ],
    'orders' => [
        'view' => [
            'label' => 'Ver pedidos',
            'description' => 'Consulte pedidos, suas linhas, pagamentos, histórico e remessas.',
        ],
        'manage' => [
            'label' => 'Gerenciar pedidos',
            'description' => 'Cancele pedidos, adicione notas e etiquetas e corrija os detalhes de contato e endereços de entrega dos compradores.',
        ],
        'fulfil' => [
            'label' => 'Atender pedidos',
            'description' => 'Crie remessas, adicione rastreamento e marque o que sobrou, está pronto para retirada ou chegou.',
        ],
    ],
    'invoices' => [
        'view' => [
            'label' => 'Ver faturas',
            'description' => 'Veja faturas e notas de crédito com os detalhes do comprador e baixe seus PDFs.',
        ],
    ],
    'payments' => [
        'view' => [
            'label' => 'Ver pagamentos',
            'description' => 'Veja pagamentos, reembolsos e como os gateways de pagamento estão conectados.',
        ],
        'confirm' => [
            'label' => 'Confirmar pagamentos',
            'description' => 'Confirme se uma transferência bancária chegou ou se o dinheiro na entrega foi coletado e pergunte novamente ao gateway sobre um pagamento.',
        ],
        'refund' => [
            'label' => 'Reembolsar pagamentos',
            'description' => 'Reembolse pagamentos, através do gateway ou registrados manualmente.',
        ],
        'manage_gateways' => [
            'label' => 'Gerenciar gateways de pagamento',
            'description' => 'Conecte, desconecte e configure gateways e métodos de pagamento.',
        ],
    ],
    'pricing' => [
        'view' => [
            'label' => 'Ver impostos e moedas',
            'description' => 'Veja classes de impostos, alíquotas de impostos, em qual classe cada variante está inserida e as moedas e taxas de câmbio da loja.',
        ],
        'manage' => [
            'label' => 'Gerenciar impostos e moedas',
            'description' => 'Altere as classes fiscais, as taxas de imposto, as classes fiscais das variantes, as moedas da loja, as taxas de câmbio e os preços fixos.',
        ],
    ],
    'settings' => [
        'view' => [
            'label' => 'Ver configurações',
            'description' => 'Veja as configurações da loja: nome, país, moeda, idiomas, modalidade tributária, unidades e dados de contato.',
        ],
        'manage' => [
            'label' => 'Gerenciar configurações',
            'description' => 'Altere as configurações da loja. A exigência de autenticação de dois fatores para a equipe fica com o proprietário.',
        ],
    ],
    'roles' => [
        'view' => [
            'label' => 'Ver funções',
            'description' => 'Veja as funções da loja e o que cada uma permite.',
        ],
        'manage' => [
            'label' => 'Gerenciar funções',
            'description' => 'Crie, renomeie e exclua funções e escolha o que cada uma permite.',
        ],
    ],
    'delivery' => [
        'view' => [
            'label' => 'Ver entregas',
            'description' => 'Ver os motoristas e as entregas da loja.',
        ],
        'manage' => [
            'label' => 'Gerenciar entregas',
            'description' => 'Dar envios aos motoristas e fazer avançar, repetir, recolher ou cancelar as suas entregas.',
        ],
        'cash' => [
            'manage' => [
                'label' => 'Registrar dinheiro dos entregadores',
                'description' => 'Registar o dinheiro que os motoristas entregam, nunca mais do que têm.',
            ],
        ],
    ],
    'drivers' => [
        'manage' => [
            'label' => 'Gerenciar entregadores',
            'description' => 'Adicionar, alterar, desativar e apagar motoristas, e dar-lhes códigos para escolher um PIN.',
        ],
    ],
    'returns' => [
        'view' => [
            'label' => 'Ver devoluções',
            'description' => 'Ver as devoluções, as suas fotos e histórico, e as definições de devolução.',
        ],
        'manage' => [
            'label' => 'Gerenciar devoluções',
            'description' => 'Iniciar, aprovar, rejeitar, receber, inspecionar e cancelar devoluções, e alterar as definições de devolução.',
        ],
        'exchange' => [
            'label' => 'Confirmar trocas',
            'description' => 'Confirmar a troca de uma devolução: cria o pedido de substituição e liquida o valor dos artigos devolvidos.',
        ],
    ],
    'storefront' => [
        'view' => [
            'label' => 'Ver o conteúdo da vitrine',
            'description' => 'Ver as páginas, os menus, os banners e as configurações da vitrine da loja.',
        ],
        'manage' => [
            'label' => 'Gerir o conteúdo da vitrine',
            'description' => 'Adicionar, alterar, publicar e remover as páginas, os menus e os banners da loja, e alterar as configurações da vitrine.',
        ],
    ],
];
