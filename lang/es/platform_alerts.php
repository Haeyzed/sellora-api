<?php

declare(strict_types=1);

return [
    'exchange_rates_overdue' => [
        'title' => 'Tipos de cambio sin actualizar desde hace :hours horas',
        'message' => 'Ninguna obtención de tipos de cambio ha tenido éxito en al menos :hours horas. Las tiendas dejan de ofrecer sus monedas adicionales cuando los tipos tienen :stale_hours horas. Revisa el proveedor de tipos de cambio.',
    ],
    'store_setup_failed' => [
        'title' => 'La configuración de la tienda :store ha fallado',
        'message' => 'La configuración de la tienda :store (:store_id) ha fallado tras todos los reintentos. Vuelve a intentarla desde la lista de tiendas cuando se corrija la causa.',
    ],
    'stock_levels_mismatch' => [
        'title' => 'Los niveles de stock de :store no coinciden con su registro',
        'message' => 'La comprobación programada encontró niveles de stock en la tienda :store (:store_id) que no coinciden con su registro de movimientos de stock (:count). Los detalles están en el registro de la comprobación.',
    ],
    'invoice_balances_mismatch' => [
        'title' => 'Las facturas de :store no coinciden con las ventas conservadas',
        'message' => 'La comprobación programada encontró facturas en la tienda :store (:store_id) cuyas notas de crédito no cuadran con el dinero de las ventas conservado o el impuesto facturado (:count). Los detalles están en el registro de la comprobación.',
    ],
    'see_dashboard' => 'Inicia sesión en el panel de la plataforma :app para ver más.',
];
