<?php

declare(strict_types=1);

return [
    'legal_documents' => [
        'manage' => 'Redactar, editar y publicar los documentos legales que aceptan los comerciantes, como los términos de servicio.',
    ],
    'stores' => [
        'view' => 'Vea cada tienda, su estado, plan, concesión de funciones y anulación de límites.',
        'manage' => 'Suspenda, reactive, cierre y restaure tiendas, y vuelva a intentar configurar una tienda cuya configuración falló.',
        'grant' => 'Ofrezca a las tiendas módulos e integraciones fuera de su plan y cambie sus límites de uso.',
        'export' => 'Exporte todos los datos de una tienda, incluidos los datos personales de cada cliente.',
    ],
    'staff' => [
        'view' => 'Vea el equipo: miembros del personal, sus roles e invitaciones pendientes.',
        'invite' => 'Invite a personas a unirse al equipo y reenvíe o cancele invitaciones.',
        'manage' => 'Cambie los roles de los miembros del personal y desactívelos o reactívelos.',
    ],
    'catalog' => [
        'view' => 'Ver el catálogo: marcas, categorías y productos, incluidos borradores, artículos archivados y basura.',
        'manage' => 'Crea y cambia marcas, categorías y productos, muévelos a la papelera y restáuralos.',
    ],
    'inventory' => [
        'view' => 'Vea los niveles de existencias, las ubicaciones y el historial de cada cambio de existencias.',
        'adjust' => 'Reciba stock, corrija los recuentos de stock y cambie la forma en que se maneja el stock de cada variante.',
        'costs' => 'Ver y cambiar lo que le cuesta cada unidad a la tienda. Los costos son internos y nunca se muestran a los clientes.',
    ],
    'customers' => [
        'view' => 'Vea clientes, sus perfiles, direcciones, notas del personal y grupos, incluidas cuentas desactivadas y eliminadas.',
        'manage' => 'Cambie los perfiles de los clientes, desactive, reactive, elimine y restaure cuentas, agregue o elimine notas y administre grupos de clientes.',
        'tax_exemption' => 'Haga que un cliente esté exento de impuestos o finalícelo después de verificar su identificación fiscal.',
        'privacy' => 'Descargue todos los datos personales de un cliente o bórrelos definitivamente, a petición del cliente.',
    ],
    'promotions' => [
        'view' => 'Vea promociones, sus códigos y con qué frecuencia se usaron, y obtenga una vista previa de lo que obtendría en una canasta.',
        'manage' => 'Crear, modificar, activar, desactivar y archivar promociones, así como agregar, generar o cambiar sus códigos.',
    ],
    'shipping' => [
        'view' => 'Vea las zonas de envío, los métodos y sus tarifas, y obtenga una vista previa de lo que pagaría una cesta por el envío.',
        'manage' => 'Cree, cambie y elimine zonas de envío, y cree, cambie y archive métodos de envío y sus tarifas.',
    ],
    'orders' => [
        'view' => 'Ver pedidos, sus líneas, pagos, historial y envíos.',
        'manage' => 'Cancele pedidos, agregue notas y etiquetas, y corrija los datos de contacto y las direcciones de envío de los compradores.',
        'fulfil' => 'Cree envíos, agregue seguimiento y marque lo que queda, lo que está listo para ser recogido o lo que ha llegado.',
    ],
    'invoices' => [
        'view' => 'Vea facturas y notas de crédito, con los datos del comprador, y descargue sus PDFs.',
    ],
    'payments' => [
        'view' => 'Vea pagos, reembolsos y cómo se conectan las pasarelas de pago.',
        'confirm' => 'Confirma que llegó una transferencia bancaria o se cobró contra reembolso y pregunta nuevamente a una pasarela sobre un pago.',
        'refund' => 'Devolución de pagos, a través de la pasarela o registrados en mano.',
        'manage_gateways' => 'Conectar, desconectar y configurar pasarelas de pago y métodos de pago.',
    ],
    'pricing' => [
        'view' => 'Vea las clases de impuestos, las tasas impositivas, en qué clase se encuentra cada variante y las monedas y tipos de cambio de la tienda.',
        'manage' => 'Cambie las clases de impuestos, las tasas impositivas, las clases de impuestos de las variantes, las monedas de la tienda, los tipos de cambio y los precios fijos.',
    ],
    'settings' => [
        'view' => 'Consulta la configuración de la tienda: nombre, país, moneda, idiomas, modo fiscal, unidades y datos de contacto.',
        'manage' => 'Cambia la configuración de la tienda. Requerir autenticación de dos factores para las estancias del personal con el propietario.',
    ],
    'roles' => [
        'view' => 'Consulta los roles de la tienda y lo que permite cada uno.',
        'manage' => 'Crea, renombra y elimina roles, y elige lo que permite cada uno.',
    ],
    'delivery' => [
        'view' => 'Ver los repartidores y las entregas de la tienda.',
        'manage' => 'Dar envíos a los repartidores y avanzar, reintentar, recuperar o cancelar sus entregas.',
        'cash' => [
            'manage' => 'Registrar el efectivo que entregan los repartidores, nunca más del que tienen.',
        ],
    ],
    'drivers' => [
        'manage' => 'Añadir, cambiar, desactivar y borrar repartidores, y darles códigos para elegir un PIN.',
    ],
    'returns' => [
        'view' => 'Ver las devoluciones, sus fotos y su historial, y los ajustes de devoluciones.',
        'manage' => 'Iniciar, aprobar, rechazar, recibir, revisar y cancelar devoluciones, y cambiar los ajustes de devoluciones.',
    ],
];
