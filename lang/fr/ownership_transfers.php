<?php

declare(strict_types=1);

return [
    'offer' => [
        'subject' => ':owner veut faire de vous le propriétaire de sa boutique',
        'reason' => ':owner vous a demandé de devenir propriétaire du magasin. En tant que propriétaire, vous pouvez tout faire dans le magasin et le contrat du magasin avec :app devient le vôtre.',
        'terms' => 'Pour accepter, connectez-vous au tableau de bord du magasin et acceptez les conditions d\'utilisation actuelles de :app. Rien ne change jusqu\'à ce que vous le fassiez.',
        'action' => 'Vérifier le transfert',
        'expiry' => 'Cette offre expire dans :hours heures.',
        'ignore' => 'Si vous ne souhaitez pas reprendre le magasin, ignorez cet e-mail.',
    ],
    'completed' => [
        'subject' => 'Le magasin :store a un nouveau propriétaire',
        'new_owner' => 'Vous êtes désormais propriétaire de la boutique :store.',
        'previous_owner' => ':owner est désormais propriétaire du magasin :store. Vous conservez uniquement les rôles que vous avez choisis au début du transfert.',
        'not_you' => 'Si vous ne vous y attendiez pas, contactez immédiatement le support :app.',
    ],
];
