<?php

declare(strict_types=1);

return [
    'legal_documents' => [
        'manage' => [
            'label' => 'Gérer les documents juridiques',
            'description' => 'Rédigez, éditez et publiez les documents juridiques acceptés par les commerçants, tels que les conditions de service.',
        ],
    ],
    'stores' => [
        'view' => [
            'label' => 'Voir les boutiques',
            'description' => 'Consultez chaque magasin, son statut, son plan, ses fonctionnalités accordées et ses dépassements de limites.',
        ],
        'manage' => [
            'label' => 'Gérer les boutiques',
            'description' => 'Suspendez, réactivez, fermez et restaurez les magasins, puis réessayez de configurer un magasin dont la configuration a échoué.',
        ],
        'grant' => [
            'label' => 'Accorder des fonctionnalités et des limites',
            'description' => 'Offrez aux magasins des modules et des intégrations en dehors de leur plan et modifiez leurs limites d\'utilisation.',
        ],
        'export' => [
            'label' => 'Exporter les données des boutiques',
            'description' => 'Exportez toutes les données d\'un magasin, y compris les données personnelles de chaque client.',
        ],
    ],
    'staff' => [
        'view' => [
            'label' => 'Voir l\'équipe',
            'description' => 'Voir l\'équipe : les membres du personnel, leurs rôles et les invitations en attente.',
        ],
        'invite' => [
            'label' => 'Inviter du personnel',
            'description' => 'Invitez des personnes à rejoindre l’équipe et renvoyez ou annulez les invitations.',
        ],
        'manage' => [
            'label' => 'Gérer le personnel',
            'description' => 'Modifiez les rôles des membres du personnel et désactivez-les ou réactivez-les.',
        ],
    ],
    'catalog' => [
        'view' => [
            'label' => 'Voir le catalogue',
            'description' => 'Consultez le catalogue : marques, catégories et produits, y compris les brouillons, les éléments archivés et la corbeille.',
        ],
        'manage' => [
            'label' => 'Gérer le catalogue',
            'description' => 'Créez et modifiez des marques, des catégories et des produits, déplacez-les vers la corbeille et restaurez-les.',
        ],
    ],
    'inventory' => [
        'view' => [
            'label' => 'Voir le stock',
            'description' => 'Consultez les niveaux de stock, les emplacements et l’historique de chaque variation de stock.',
        ],
        'adjust' => [
            'label' => 'Ajuster le stock',
            'description' => 'Recevez du stock, corrigez les inventaires et modifiez la façon dont le stock de chaque variante est géré.',
        ],
        'costs' => [
            'label' => 'Coûts unitaires',
            'description' => 'Voir et modifier le prix de chaque unité au magasin. Les coûts sont internes et ne sont jamais montrés aux clients.',
        ],
    ],
    'customers' => [
        'view' => [
            'label' => 'Voir les clients',
            'description' => 'Consultez les clients, leurs profils, adresses, notes du personnel et groupes, y compris les comptes désactivés et supprimés.',
        ],
        'manage' => [
            'label' => 'Gérer les clients',
            'description' => 'Modifiez les profils des clients, désactivez, réactivez, supprimez et restaurez des comptes, ajoutez ou supprimez des notes et gérez les groupes de clients.',
        ],
        'tax_exemption' => [
            'label' => 'Exonérations de taxe',
            'description' => 'Exonérer un client de taxe, ou y mettre fin, après avoir vérifié son numéro d\'identification fiscale.',
        ],
        'privacy' => [
            'label' => 'Demandes de données des clients',
            'description' => 'Téléchargez toutes les données personnelles d\'un client, ou effacez-les définitivement, à la demande du client.',
        ],
    ],
    'promotions' => [
        'view' => [
            'label' => 'Voir les promotions',
            'description' => 'Consultez les promotions, leurs codes et la fréquence à laquelle ils ont été utilisés, et prévisualisez le montant que recevrait un panier.',
        ],
        'manage' => [
            'label' => 'Gérer les promotions',
            'description' => 'Créez, modifiez, activez, désactivez et archivez les promotions, et ajoutez, générez ou modifiez leurs codes.',
        ],
    ],
    'shipping' => [
        'view' => [
            'label' => 'Voir l\'expédition',
            'description' => 'Consultez les zones d\'expédition, les méthodes et leurs tarifs, et prévisualisez ce qu\'un panier paierait pour l\'expédition.',
        ],
        'manage' => [
            'label' => 'Gérer l\'expédition',
            'description' => 'Créez, modifiez et supprimez des zones d\'expédition, et créez, modifiez et archivez les méthodes d\'expédition et leurs tarifs.',
        ],
    ],
    'orders' => [
        'view' => [
            'label' => 'Voir les commandes',
            'description' => 'Consultez les commandes, leurs lignes, les paiements, l\'historique et les expéditions.',
        ],
        'manage' => [
            'label' => 'Gérer les commandes',
            'description' => 'Annulez les commandes, ajoutez des notes et des étiquettes, et corrigez les coordonnées et les adresses de livraison des acheteurs.',
        ],
        'fulfil' => [
            'label' => 'Traiter les commandes',
            'description' => 'Créez des envois, ajoutez un suivi et marquez ce qui reste, ce qui est prêt à être récupéré ou ce qui est arrivé.',
        ],
    ],
    'invoices' => [
        'view' => [
            'label' => 'Voir les factures',
            'description' => 'Consultez les factures et les notes de crédit, avec les détails de l\'acheteur dessus, et téléchargez leurs PDF.',
        ],
    ],
    'payments' => [
        'view' => [
            'label' => 'Voir les paiements',
            'description' => 'Consultez les paiements, les remboursements et la manière dont les passerelles de paiement sont connectées.',
        ],
        'confirm' => [
            'label' => 'Confirmer les paiements',
            'description' => 'Confirmez qu\'un virement bancaire est arrivé ou qu\'un paiement à la livraison a été encaissé, et demandez à nouveau à une passerelle un paiement.',
        ],
        'refund' => [
            'label' => 'Rembourser les paiements',
            'description' => 'Remboursez les paiements, via la passerelle ou tels qu\'ils sont enregistrés à la main.',
        ],
        'manage_gateways' => [
            'label' => 'Gérer les passerelles de paiement',
            'description' => 'Connectez, déconnectez et configurez des passerelles de paiement et des méthodes de paiement.',
        ],
    ],
    'pricing' => [
        'view' => [
            'label' => 'Voir les taxes et devises',
            'description' => 'Consultez les classes de taxe, les taux de taxe, la classe dans laquelle se trouve chaque variante, ainsi que les devises et les taux de change du magasin.',
        ],
        'manage' => [
            'label' => 'Gérer les taxes et devises',
            'description' => 'Modifiez les classes de taxe, les taux de taxe, les classes de taxe des variantes, les devises du magasin, les taux de change et les prix fixes.',
        ],
    ],
    'settings' => [
        'view' => [
            'label' => 'Voir les paramètres',
            'description' => 'Consultez les paramètres de la boutique : nom, pays, devise, langues, mode de taxe, unités et coordonnées.',
        ],
        'manage' => [
            'label' => 'Gérer les paramètres',
            'description' => 'Modifiez les paramètres du magasin. L’exigence d’une authentification à deux facteurs pour le personnel reste du ressort du propriétaire.',
        ],
    ],
    'roles' => [
        'view' => [
            'label' => 'Voir les rôles',
            'description' => 'Découvrez les rôles du magasin et ce que chacun permet.',
        ],
        'manage' => [
            'label' => 'Gérer les rôles',
            'description' => 'Créez, renommez et supprimez des rôles, et choisissez ce que chacun autorise.',
        ],
    ],
    'delivery' => [
        'view' => [
            'label' => 'Voir les livraisons',
            'description' => 'Consultez les livreurs et les livraisons de la boutique.',
        ],
        'manage' => [
            'label' => 'Gérer les livraisons',
            'description' => 'Confier des envois aux livreurs, et faire avancer, relancer, reprendre ou annuler leurs livraisons.',
        ],
        'cash' => [
            'manage' => [
                'label' => 'Enregistrer l\'argent des livreurs',
                'description' => 'Enregistrer les espèces remises par les livreurs, jamais plus qu’ils n’en détiennent.',
            ],
        ],
    ],
    'drivers' => [
        'manage' => [
            'label' => 'Gérer les livreurs',
            'description' => 'Ajoutez, modifiez, désactivez et effacez des livreurs, et donnez-leur des codes pour choisir un code PIN.',
        ],
    ],
    'returns' => [
        'view' => [
            'label' => 'Voir les retours',
            'description' => 'Voir les retours, leurs photos et leur historique, ainsi que les réglages des retours.',
        ],
        'manage' => [
            'label' => 'Gérer les retours',
            'description' => 'Créer, approuver, refuser, réceptionner, contrôler et annuler des retours, et modifier les réglages des retours.',
        ],
    ],
];
