<?php

declare(strict_types=1);

return [
    'exchange_rates_overdue' => [
        'title' => 'Taux de change non actualisés depuis :hours heures',
        'message' => 'Aucune récupération des taux de change n\'a réussi depuis au moins :hours heures. Les boutiques cessent de proposer leurs devises supplémentaires lorsque les taux ont :stale_hours heures. Vérifiez le fournisseur de taux de change.',
    ],
    'store_setup_failed' => [
        'title' => 'La configuration de la boutique :store a échoué',
        'message' => 'La configuration de la boutique :store (:store_id) a échoué après toutes les tentatives. Relancez-la depuis la liste des boutiques une fois la cause corrigée.',
    ],
    'stock_levels_mismatch' => [
        'title' => 'Les niveaux de stock de :store ne correspondent pas à leur registre',
        'message' => 'Le contrôle planifié a trouvé des niveaux de stock dans la boutique :store (:store_id) qui ne correspondent pas à son registre des mouvements de stock (:count). Les détails figurent dans le journal du contrôle.',
    ],
    'invoice_balances_mismatch' => [
        'title' => 'Les factures de :store ne correspondent pas aux ventes conservées',
        'message' => 'Le contrôle planifié a trouvé des factures dans la boutique :store (:store_id) dont les avoirs ne concordent pas avec l\'argent des ventes conservé ou la taxe facturée (:count). Les détails figurent dans le journal du contrôle.',
    ],
    'see_dashboard' => 'Connectez-vous au tableau de bord de la plateforme :app pour en savoir plus.',
];
