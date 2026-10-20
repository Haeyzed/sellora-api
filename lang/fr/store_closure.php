<?php

declare(strict_types=1);

return [
    'closed' => [
        'subject' => 'Votre magasin :store est fermé',
        'line' => 'Votre magasin :store est fermé. Il ne s\'ouvre plus à personne et tout le monde était déconnecté. Ses données seront définitivement supprimées le :date.',
    ],
    'reminder' => [
        'subject' => 'Votre boutique :store sera supprimée le :date',
        'line' => 'Votre magasin :store était fermé. Ses données seront définitivement supprimées sur :date et ne pourront plus être récupérées par la suite.',
    ],
    'export' => 'Si vous avez besoin d\'une copie des données de votre boutique, ou si vous souhaitez rouvrir la boutique, contactez le support Sellora avant le :date : ils pourront restaurer la boutique afin que vous puissiez tout exporter.',
    'not_you' => 'Si vous n\'avez pas fermé cette boutique, contactez immédiatement le support Sellora.',
];
