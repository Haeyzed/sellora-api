<?php

declare(strict_types=1);

return [
    'legal_documents' => [
        'manage' => [
            'label' => '管理法律文件',
            'description' => '起草、编辑、发布商户接受的服务条款等法律文件。',
        ],
    ],
    'stores' => [
        'view' => [
            'label' => '查看店铺',
            'description' => '查看每个商店及其状态、计划、功能授予和限制覆盖。',
        ],
        'manage' => [
            'label' => '管理店铺',
            'description' => '暂停、重新激活、关闭和恢复存储，并重试设置设置失败的存储。',
        ],
        'grant' => [
            'label' => '授予功能和限额',
            'description' => '为商店提供计划之外的模块和集成，并更改其使用限制。',
        ],
        'export' => [
            'label' => '导出店铺数据',
            'description' => '导出商店的所有数据，包括每个客户的个人数据。',
        ],
    ],
    'staff' => [
        'view' => [
            'label' => '查看团队',
            'description' => '查看团队：工作人员、他们的角色和待处理的邀请。',
        ],
        'invite' => [
            'label' => '邀请员工',
            'description' => '邀请人们加入团队，以及重新发送或取消邀请。',
        ],
        'manage' => [
            'label' => '管理员工',
            'description' => '更改员工的角色，并停用或重新激活他们。',
        ],
    ],
    'catalog' => [
        'view' => [
            'label' => '查看商品目录',
            'description' => '查看目录：品牌、类别和产品，包括草稿、存档项目和垃圾。',
        ],
        'manage' => [
            'label' => '管理商品目录',
            'description' => '创建和更改品牌、类别和产品，将它们移至垃圾箱并恢复它们。',
        ],
    ],
    'inventory' => [
        'view' => [
            'label' => '查看库存',
            'description' => '查看库存水平、位置以及每次库存变化的历史记录。',
        ],
        'adjust' => [
            'label' => '调整库存',
            'description' => '接收库存、纠正库存数量并更改每个型号的库存处理方式。',
        ],
        'costs' => [
            'label' => '单位成本',
            'description' => '查看并更改商店每个单位的成本。成本是内部成本，永远不会向客户显示。',
        ],
    ],
    'customers' => [
        'view' => [
            'label' => '查看客户',
            'description' => '查看客户、他们的个人资料、地址、员工注释和群组，包括停用和废弃的帐户。',
        ],
        'manage' => [
            'label' => '管理客户',
            'description' => '更改客户的个人资料、停用、重新激活、删除和恢复帐户、添加或删除注释以及管理客户组。',
        ],
        'tax_exemption' => [
            'label' => '免税',
            'description' => '检查客户的税号后，让客户免税或结束免税。',
        ],
        'privacy' => [
            'label' => '客户数据请求',
            'description' => '下载客户的所有个人数据，或根据客户的要求将其永久删除。',
        ],
    ],
    'promotions' => [
        'view' => [
            'label' => '查看促销',
            'description' => '查看促销活动、其代码及其使用频率，并预览购物篮将获得什么。',
        ],
        'manage' => [
            'label' => '管理促销',
            'description' => '创建、更改、激活、停用和存档促销活动，以及添加、生成或更改其代码。',
        ],
    ],
    'shipping' => [
        'view' => [
            'label' => '查看配送设置',
            'description' => '查看运输区域、方法及其费率，并预览购物篮将支付的运输费用。',
        ],
        'manage' => [
            'label' => '管理配送设置',
            'description' => '创建、更改和删除运输区域，以及创建、更改和存档运输方式及其费率。',
        ],
    ],
    'orders' => [
        'view' => [
            'label' => '查看订单',
            'description' => '查看订单、订单行、付款、历史记录和发货。',
        ],
        'manage' => [
            'label' => '管理订单',
            'description' => '取消订单、添加注释和标签，以及更正买家的联系方式和送货地址。',
        ],
        'fulfil' => [
            'label' => '履行订单',
            'description' => '创建货件、添加跟踪并标记剩余货物、准备取货或已到达的货物。',
        ],
    ],
    'invoices' => [
        'view' => [
            'label' => '查看发票',
            'description' => '查看含有买家详细信息的发票和信用凭证，并下载其 PDF 文件。',
        ],
    ],
    'payments' => [
        'view' => [
            'label' => '查看付款',
            'description' => '查看付款、退款以及支付网关的连接方式。',
        ],
        'confirm' => [
            'label' => '确认付款',
            'description' => '确认银行转账已到达或货到付款已收到，并再次向网关询问付款情况。',
        ],
        'refund' => [
            'label' => '退款',
            'description' => '通过网关或手工记录退款。',
        ],
        'manage_gateways' => [
            'label' => '管理支付网关',
            'description' => '连接、断开连接以及设置支付网关和支付方式。',
        ],
    ],
    'pricing' => [
        'view' => [
            'label' => '查看税费和货币',
            'description' => '查看税种、税率、每个变体所属的类别以及商店的货币和汇率。',
        ],
        'manage' => [
            'label' => '管理税费和货币',
            'description' => '更改税级、税率、变量的税级、商店的货币、汇率和固定价格。',
        ],
    ],
    'settings' => [
        'view' => [
            'label' => '查看设置',
            'description' => '查看商店的设置：名称、国家/地区、货币、语言、税收模式、单位和联系方式。',
        ],
        'manage' => [
            'label' => '管理设置',
            'description' => '更改商店的设置。要求员工进行两步验证的要求由业主负责。',
        ],
    ],
    'roles' => [
        'view' => [
            'label' => '查看角色',
            'description' => '查看商店的角色以及每个角色允许执行的操作。',
        ],
        'manage' => [
            'label' => '管理角色',
            'description' => '创建、重命名和删除角色，并选择每个角色允许的内容。',
        ],
    ],
    'delivery' => [
        'view' => [
            'label' => '查看送货',
            'description' => '查看商店的司机和配送。',
        ],
        'manage' => [
            'label' => '管理送货',
            'description' => '将货件分配给司机，并推进、重试、收回或取消他们的配送。',
        ],
        'cash' => [
            'manage' => [
                'label' => '记录司机现金',
                'description' => '记录司机上交的现金，绝不超过其持有的金额。',
            ],
        ],
    ],
    'drivers' => [
        'manage' => [
            'label' => '管理司机',
            'description' => '添加、更改、停用和删除司机，并向他们发放用于设置 PIN 的代码。',
        ],
    ],
    'returns' => [
        'view' => [
            'label' => '查看退货',
            'description' => '查看退货及其照片和记录，以及退货设置。',
        ],
        'manage' => [
            'label' => '管理退货',
            'description' => '发起、批准、拒绝、签收、检验和取消退货，并更改退货设置。',
        ],
        'exchange' => [
            'label' => '确认换货',
            'description' => '确认退货的换货：这会生成换货订单，并结算退回商品的价值。',
        ],
    ],
    'storefront' => [
        'view' => [
            'label' => '查看店面内容',
            'description' => '查看店铺的页面、菜单、横幅和店面设置。',
        ],
        'manage' => [
            'label' => '管理店面内容',
            'description' => '添加、更改、发布和删除店铺的页面、菜单和横幅，并更改店面设置。',
        ],
    ],
];
