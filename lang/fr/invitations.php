<?php

declare(strict_types=1);

return [
    'staff' => [
        'subject' => ':inviter vous a invité à rejoindre l\'équipe de son magasin',
        'reason' => ':inviter vous a invité à l\'aider à gérer son magasin. Acceptez pour choisir votre mot de passe et connectez-vous.',
        'action' => 'Acceptez l\'invitation',
        'expiry' => 'Ce lien fonctionne une fois et expire dans :days jours.',
        'ignore' => 'Si vous ne vous y attendiez pas, vous pouvez ignorer cet e-mail ; aucun compte n\'est créé.',
    ],
    'platform_admin' => [
        'subject' => ':inviter vous a invité à rejoindre l\'équipe :app',
        'reason' => ':inviter vous a invité à participer au fonctionnement de la plateforme :app. Acceptez pour choisir votre mot de passe.',
        'action' => 'Acceptez l\'invitation',
        'expiry' => 'Ce lien fonctionne une fois et expire dans :days jours.',
        'two_factor' => 'Vous aurez besoin d\'une application d\'authentification : une authentification à deux facteurs est requise avant de pouvoir utiliser votre compte.',
        'ignore' => 'Si vous ne vous y attendiez pas, vous pouvez ignorer cet e-mail ; aucun compte n\'est créé.',
    ],
];
