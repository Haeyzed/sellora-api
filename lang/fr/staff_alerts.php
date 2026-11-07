<?php

declare(strict_types=1);

return [
    'new_order' => [
        'title' => 'Nouvelle commande :order',
        'message' => 'La commande :order a été passée pour :amount.',
    ],
    'payment_needs_review' => [
        'title' => 'Un paiement de la commande :order est à vérifier',
        'message' => 'Un paiement de :amount pour la commande :order ne correspond pas à ce qui était attendu. Vérifiez-le avant de traiter la commande.',
    ],
    'payment_unresolved' => [
        'title' => 'Un paiement de la commande :order n\'est pas résolu',
        'message' => 'La passerelle n\'a pas réglé le paiement de :amount pour la commande :order. Vérifiez-le auprès de la passerelle.',
    ],
    'payment_overpaid' => [
        'title' => 'La commande :order a été trop payée',
        'message' => 'L\'acheteur a payé :amount de plus que le prix de la commande :order. Remboursez la différence.',
    ],
    'payment_disputed' => [
        'title' => 'Un paiement est contesté',
        'message' => 'Un acheteur a contesté un paiement effectué via :gateway. Répondez à la contestation dans le tableau de bord de la passerelle avant son échéance.',
    ],
    'payment_gateway_needs_reconnecting' => [
        'title' => 'Reconnectez :gateway',
        'message' => ':gateway a refusé les clés de la boutique. Les paiements par ce moyen restent en attente jusqu\'à sa reconnexion.',
    ],
    'order_paid_after_cancellation' => [
        'title' => 'La commande annulée :order a été payée',
        'message' => 'Un paiement est arrivé pour la commande :order après son annulation. La commande reste annulée ; remboursez le paiement.',
    ],
    'order_refunded_beyond_paid' => [
        'title' => 'La commande :order a été remboursée au-delà du montant payé',
        'message' => 'Les remboursements de la commande :order dépassent ce que l\'acheteur a payé. Vérifiez les remboursements de la commande.',
    ],
    'refund_needs_review' => [
        'title' => 'Un remboursement de la commande :order est à vérifier',
        'message' => 'Le remboursement de :amount pour la commande :order est à vérifier : la réponse de la passerelle n\'était pas claire.',
    ],
    'refund_failed' => [
        'title' => 'Un remboursement de la commande :order a échoué',
        'message' => 'Le remboursement de :amount pour la commande :order a échoué. Remboursez l\'acheteur autrement, ou réessayez.',
    ],
    'refund_still_pending' => [
        'title' => 'Un remboursement de la commande :order est toujours en attente',
        'message' => 'Le remboursement de :amount pour la commande :order attend depuis des jours chez la passerelle. Vérifiez-le auprès de la passerelle.',
    ],
    'refund_succeeded_after_failing' => [
        'title' => 'Un remboursement échoué de la commande :order a abouti',
        'message' => 'Le remboursement de :amount pour la commande :order, signalé comme échoué, a finalement abouti. Si vous avez remboursé à nouveau, l\'acheteur a été remboursé deux fois.',
    ],
    'payment_hold_keeps_failing' => [
        'title' => 'Un paiement de la commande :order ne peut pas être réglé',
        'message' => 'Le règlement du paiement :method de :amount pour la commande :order échoue sans cesse. Il est retenté toutes les heures.',
    ],
    'order_payments_keep_failing' => [
        'title' => 'Les paiements de la commande :order ne peuvent pas être enregistrés',
        'message' => 'L\'enregistrement des paiements de la commande :order échoue sans cesse. Il est retenté toutes les heures.',
    ],
    'committed_payment_refund_failed' => [
        'title' => 'L\'argent de la commande annulée :order ne peut pas être rendu',
        'message' => 'Le paiement :method de :amount pour la commande annulée :order n\'a pas pu être rendu. Il est retenté toutes les heures, et la commande doit toujours le remboursement.',
    ],
    'exchange_settlement_refused' => [
        'title' => 'Un échange pour la commande :order n\'a pas été réglé',
        'message' => 'La commande de remplacement :order a été payée, mais le règlement de son retour a été refusé (:reason). Réglez-le à nouveau depuis le retour.',
    ],
    'cash_collection_overdue' => [
        'title' => 'L\'espèce de la commande :order n\'est pas enregistrée',
        'message' => 'La commande :order a été livrée, mais les :amount en espèces n\'ont pas été enregistrés comme encaissés.',
    ],
    'delivery_failed' => [
        'title' => 'Une livraison de la commande :order a échoué',
        'message' => 'Le livreur n\'a pas pu livrer la commande :order. Organisez une autre livraison ou contactez l\'acheteur.',
    ],
    'stock_running_low' => [
        'title' => 'Le stock de :product s\'épuise',
        'message' => 'Il ne reste que :available de :product (SKU :sku).',
    ],
    'variant_sold_out' => [
        'title' => ':product est épuisé',
        'message' => ':product (SKU :sku) est épuisé.',
    ],
    'return_requested' => [
        'title' => 'Retour demandé pour la commande :order',
        'message' => 'Un acheteur a demandé à retourner des articles de la commande :order.',
    ],
    'waiting' => [
        'title' => 'D\'autres alertes vous attendent',
        'message' => 'Plus d\'alertes sont arrivées cette heure-ci que ce qui est envoyé par e-mail. Retrouvez-les toutes dans votre tableau de bord.',
    ],
    'see_dashboard' => 'Consultez-la dans le tableau de bord de votre boutique.',
];
