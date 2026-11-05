<?php

declare(strict_types=1);

return [
    'legal_documents' => [
        'manage' => [
            'label' => 'Gestionar documentos legales',
            'description' => 'Redactar, editar y publicar los documentos legales que aceptan los comerciantes, como los términos de servicio.',
        ],
    ],
    'stores' => [
        'view' => [
            'label' => 'Ver tiendas',
            'description' => 'Vea cada tienda, su estado, plan, concesión de funciones y anulación de límites.',
        ],
        'manage' => [
            'label' => 'Gestionar tiendas',
            'description' => 'Suspenda, reactive, cierre y restaure tiendas, y vuelva a intentar configurar una tienda cuya configuración falló.',
        ],
        'grant' => [
            'label' => 'Conceder funciones y límites',
            'description' => 'Ofrezca a las tiendas módulos e integraciones fuera de su plan y cambie sus límites de uso.',
        ],
        'export' => [
            'label' => 'Exportar datos de tiendas',
            'description' => 'Exporte todos los datos de una tienda, incluidos los datos personales de cada cliente.',
        ],
    ],
    'staff' => [
        'view' => [
            'label' => 'Ver equipo',
            'description' => 'Vea el equipo: miembros del personal, sus roles e invitaciones pendientes.',
        ],
        'invite' => [
            'label' => 'Invitar personal',
            'description' => 'Invite a personas a unirse al equipo y reenvíe o cancele invitaciones.',
        ],
        'manage' => [
            'label' => 'Gestionar personal',
            'description' => 'Cambie los roles de los miembros del personal y desactívelos o reactívelos.',
        ],
    ],
    'catalog' => [
        'view' => [
            'label' => 'Ver catálogo',
            'description' => 'Ver el catálogo: marcas, categorías y productos, incluidos borradores, artículos archivados y basura.',
        ],
        'manage' => [
            'label' => 'Gestionar catálogo',
            'description' => 'Crea y cambia marcas, categorías y productos, muévelos a la papelera y restáuralos.',
        ],
    ],
    'inventory' => [
        'view' => [
            'label' => 'Ver inventario',
            'description' => 'Vea los niveles de existencias, las ubicaciones y el historial de cada cambio de existencias.',
        ],
        'adjust' => [
            'label' => 'Ajustar inventario',
            'description' => 'Reciba stock, corrija los recuentos de stock y cambie la forma en que se maneja el stock de cada variante.',
        ],
        'costs' => [
            'label' => 'Costes unitarios',
            'description' => 'Ver y cambiar lo que le cuesta cada unidad a la tienda. Los costos son internos y nunca se muestran a los clientes.',
        ],
    ],
    'customers' => [
        'view' => [
            'label' => 'Ver clientes',
            'description' => 'Vea clientes, sus perfiles, direcciones, notas del personal y grupos, incluidas cuentas desactivadas y eliminadas.',
        ],
        'manage' => [
            'label' => 'Gestionar clientes',
            'description' => 'Cambie los perfiles de los clientes, desactive, reactive, elimine y restaure cuentas, agregue o elimine notas y administre grupos de clientes.',
        ],
        'tax_exemption' => [
            'label' => 'Exenciones de impuestos',
            'description' => 'Haga que un cliente esté exento de impuestos o finalícelo después de verificar su identificación fiscal.',
        ],
        'privacy' => [
            'label' => 'Solicitudes de datos de clientes',
            'description' => 'Descargue todos los datos personales de un cliente o bórrelos definitivamente, a petición del cliente.',
        ],
    ],
    'promotions' => [
        'view' => [
            'label' => 'Ver promociones',
            'description' => 'Vea promociones, sus códigos y con qué frecuencia se usaron, y obtenga una vista previa de lo que obtendría en una canasta.',
        ],
        'manage' => [
            'label' => 'Gestionar promociones',
            'description' => 'Crear, modificar, activar, desactivar y archivar promociones, así como agregar, generar o cambiar sus códigos.',
        ],
    ],
    'shipping' => [
        'view' => [
            'label' => 'Ver envíos',
            'description' => 'Vea las zonas de envío, los métodos y sus tarifas, y obtenga una vista previa de lo que pagaría una cesta por el envío.',
        ],
        'manage' => [
            'label' => 'Gestionar envíos',
            'description' => 'Cree, cambie y elimine zonas de envío, y cree, cambie y archive métodos de envío y sus tarifas.',
        ],
    ],
    'orders' => [
        'view' => [
            'label' => 'Ver pedidos',
            'description' => 'Ver pedidos, sus líneas, pagos, historial y envíos.',
        ],
        'manage' => [
            'label' => 'Gestionar pedidos',
            'description' => 'Cancele pedidos, agregue notas y etiquetas, y corrija los datos de contacto y las direcciones de envío de los compradores.',
        ],
        'fulfil' => [
            'label' => 'Preparar pedidos',
            'description' => 'Cree envíos, agregue seguimiento y marque lo que queda, lo que está listo para ser recogido o lo que ha llegado.',
        ],
    ],
    'invoices' => [
        'view' => [
            'label' => 'Ver facturas',
            'description' => 'Vea facturas y notas de crédito, con los datos del comprador, y descargue sus PDFs.',
        ],
    ],
    'payments' => [
        'view' => [
            'label' => 'Ver pagos',
            'description' => 'Vea pagos, reembolsos y cómo se conectan las pasarelas de pago.',
        ],
        'confirm' => [
            'label' => 'Confirmar pagos',
            'description' => 'Confirma que llegó una transferencia bancaria o se cobró contra reembolso y pregunta nuevamente a una pasarela sobre un pago.',
        ],
        'refund' => [
            'label' => 'Reembolsar pagos',
            'description' => 'Devolución de pagos, a través de la pasarela o registrados en mano.',
        ],
        'manage_gateways' => [
            'label' => 'Gestionar pasarelas de pago',
            'description' => 'Conectar, desconectar y configurar pasarelas de pago y métodos de pago.',
        ],
    ],
    'pricing' => [
        'view' => [
            'label' => 'Ver impuestos y monedas',
            'description' => 'Vea las clases de impuestos, las tasas impositivas, en qué clase se encuentra cada variante y las monedas y tipos de cambio de la tienda.',
        ],
        'manage' => [
            'label' => 'Gestionar impuestos y monedas',
            'description' => 'Cambie las clases de impuestos, las tasas impositivas, las clases de impuestos de las variantes, las monedas de la tienda, los tipos de cambio y los precios fijos.',
        ],
    ],
    'settings' => [
        'view' => [
            'label' => 'Ver ajustes',
            'description' => 'Consulta la configuración de la tienda: nombre, país, moneda, idiomas, modo fiscal, unidades y datos de contacto.',
        ],
        'manage' => [
            'label' => 'Gestionar ajustes',
            'description' => 'Cambia la configuración de la tienda. Requerir autenticación de dos factores para las estancias del personal con el propietario.',
        ],
    ],
    'roles' => [
        'view' => [
            'label' => 'Ver roles',
            'description' => 'Consulta los roles de la tienda y lo que permite cada uno.',
        ],
        'manage' => [
            'label' => 'Gestionar roles',
            'description' => 'Crea, renombra y elimina roles, y elige lo que permite cada uno.',
        ],
    ],
    'delivery' => [
        'view' => [
            'label' => 'Ver entregas',
            'description' => 'Ver los repartidores y las entregas de la tienda.',
        ],
        'manage' => [
            'label' => 'Gestionar entregas',
            'description' => 'Dar envíos a los repartidores y avanzar, reintentar, recuperar o cancelar sus entregas.',
        ],
        'cash' => [
            'manage' => [
                'label' => 'Registrar efectivo de repartidores',
                'description' => 'Registrar el efectivo que entregan los repartidores, nunca más del que tienen.',
            ],
        ],
    ],
    'drivers' => [
        'manage' => [
            'label' => 'Gestionar repartidores',
            'description' => 'Añadir, cambiar, desactivar y borrar repartidores, y darles códigos para elegir un PIN.',
        ],
    ],
    'returns' => [
        'view' => [
            'label' => 'Ver devoluciones',
            'description' => 'Ver las devoluciones, sus fotos y su historial, y los ajustes de devoluciones.',
        ],
        'manage' => [
            'label' => 'Gestionar devoluciones',
            'description' => 'Iniciar, aprobar, rechazar, recibir, revisar y cancelar devoluciones, y cambiar los ajustes de devoluciones.',
        ],
        'exchange' => [
            'label' => 'Confirmar cambios',
            'description' => 'Confirmar el cambio de una devolución: crea su pedido de reemplazo y liquida lo que valen los artículos devueltos.',
        ],
    ],
    'storefront' => [
        'view' => [
            'label' => 'Ver el contenido de la tienda',
            'description' => 'Ver las páginas, los menús, los banners y los ajustes de la tienda online.',
        ],
        'manage' => [
            'label' => 'Gestionar el contenido de la tienda',
            'description' => 'Añadir, cambiar, publicar y quitar las páginas, los menús y los banners de la tienda, y cambiar los ajustes de la tienda online.',
        ],
    ],
];
