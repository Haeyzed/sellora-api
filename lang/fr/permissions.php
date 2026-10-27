<?php

declare(strict_types=1);

return [
    'legal_documents' => [
        'manage' => 'Rédigez, éditez et publiez les documents juridiques acceptés par les commerçants, tels que les conditions de service.',
    ],
    'stores' => [
        'view' => 'Consultez chaque magasin, son statut, son plan, ses fonctionnalités accordées et ses dépassements de limites.',
        'manage' => 'Suspendez, réactivez, fermez et restaurez les magasins, puis réessayez de configurer un magasin dont la configuration a échoué.',
        'grant' => 'Offrez aux magasins des modules et des intégrations en dehors de leur plan et modifiez leurs limites d\'utilisation.',
        'export' => 'Exportez toutes les données d\'un magasin, y compris les données personnelles de chaque client.',
    ],
    'staff' => [
        'view' => 'Voir l\'équipe : les membres du personnel, leurs rôles et les invitations en attente.',
        'invite' => 'Invitez des personnes à rejoindre l’équipe et renvoyez ou annulez les invitations.',
        'manage' => 'Modifiez les rôles des membres du personnel et désactivez-les ou réactivez-les.',
    ],
    'catalog' => [
        'view' => 'Consultez le catalogue : marques, catégories et produits, y compris les brouillons, les éléments archivés et la corbeille.',
        'manage' => 'Créez et modifiez des marques, des catégories et des produits, déplacez-les vers la corbeille et restaurez-les.',
    ],
    'inventory' => [
        'view' => 'Consultez les niveaux de stock, les emplacements et l’historique de chaque variation de stock.',
        'adjust' => 'Recevez du stock, corrigez les inventaires et modifiez la façon dont le stock de chaque variante est géré.',
        'costs' => 'Voir et modifier le prix de chaque unité au magasin. Les coûts sont internes et ne sont jamais montrés aux clients.',
    ],
    'customers' => [
        'view' => 'Consultez les clients, leurs profils, adresses, notes du personnel et groupes, y compris les comptes désactivés et supprimés.',
        'manage' => 'Modifiez les profils des clients, désactivez, réactivez, supprimez et restaurez des comptes, ajoutez ou supprimez des notes et gérez les groupes de clients.',
        'tax_exemption' => 'Exonérer un client de taxe, ou y mettre fin, après avoir vérifié son numéro d\'identification fiscale.',
        'privacy' => 'Téléchargez toutes les données personnelles d\'un client, ou effacez-les définitivement, à la demande du client.',
    ],
    'promotions' => [
        'view' => 'Consultez les promotions, leurs codes et la fréquence à laquelle ils ont été utilisés, et prévisualisez le montant que recevrait un panier.',
        'manage' => 'Créez, modifiez, activez, désactivez et archivez les promotions, et ajoutez, générez ou modifiez leurs codes.',
    ],
    'shipping' => [
        'view' => 'Consultez les zones d\'expédition, les méthodes et leurs tarifs, et prévisualisez ce qu\'un panier paierait pour l\'expédition.',
        'manage' => 'Créez, modifiez et supprimez des zones d\'expédition, et créez, modifiez et archivez les méthodes d\'expédition et leurs tarifs.',
    ],
    'orders' => [
        'view' => 'Consultez les commandes, leurs lignes, les paiements, l\'historique et les expéditions.',
        'manage' => 'Annulez les commandes, ajoutez des notes et des étiquettes, et corrigez les coordonnées et les adresses de livraison des acheteurs.',
        'fulfil' => 'Créez des envois, ajoutez un suivi et marquez ce qui reste, ce qui est prêt à être récupéré ou ce qui est arrivé.',
    ],
    'invoices' => [
        'view' => 'Consultez les factures et les notes de crédit, avec les détails de l\'acheteur dessus, et téléchargez leurs PDF.',
    ],
    'payments' => [
        'view' => 'Consultez les paiements, les remboursements et la manière dont les passerelles de paiement sont connectées.',
        'confirm' => 'Confirmez qu\'un virement bancaire est arrivé ou qu\'un paiement à la livraison a été encaissé, et demandez à nouveau à une passerelle un paiement.',
        'refund' => 'Remboursez les paiements, via la passerelle ou tels qu\'ils sont enregistrés à la main.',
        'manage_gateways' => 'Connectez, déconnectez et configurez des passerelles de paiement et des méthodes de paiement.',
    ],
    'pricing' => [
        'view' => 'Consultez les classes de taxe, les taux de taxe, la classe dans laquelle se trouve chaque variante, ainsi que les devises et les taux de change du magasin.',
        'manage' => 'Modifiez les classes de taxe, les taux de taxe, les classes de taxe des variantes, les devises du magasin, les taux de change et les prix fixes.',
    ],
    'settings' => [
        'view' => 'Consultez les paramètres de la boutique : nom, pays, devise, langues, mode de taxe, unités et coordonnées.',
        'manage' => 'Modifiez les paramètres du magasin. L’exigence d’une authentification à deux facteurs pour le personnel reste du ressort du propriétaire.',
    ],
    'roles' => [
        'view' => 'Découvrez les rôles du magasin et ce que chacun permet.',
        'manage' => 'Créez, renommez et supprimez des rôles, et choisissez ce que chacun autorise.',
    ],
    'delivery' => [
        'view' => 'Consultez les livreurs et les livraisons de la boutique.',
        'manage' => 'Confier des envois aux livreurs, et faire avancer, relancer, reprendre ou annuler leurs livraisons.',
    ],
    'drivers' => [
        'manage' => 'Ajoutez, modifiez, désactivez et effacez des livreurs, et donnez-leur des codes pour choisir un code PIN.',
    ],
];
