<?php

declare(strict_types=1);

return [
    'email_verification' => [
        'subject' => 'Confirmez votre adresse e-mail',
        'reason' => 'Confirmez qu\'il s\'agit de votre adresse e-mail afin de pouvoir voir vos commandes passées et télécharger vos données.',
        'action' => 'Confirmer mon email',
        'expiry' => 'Ce lien fonctionne une fois et expire dans :hours heures.',
        'ignore' => 'Si vous n\'avez pas créé de compte, vous pouvez ignorer cet e-mail.',
    ],
    'email_change' => [
        'subject' => 'Confirmez votre nouvelle adresse e-mail',
        'reason' => 'Vous avez demandé à utiliser cette adresse pour votre compte. Ouvrez ce lien lorsque vous êtes connecté pour effectuer la modification.',
        'action' => 'Utilisez cet e-mail',
        'expiry' => 'Ce lien fonctionne une fois et expire dans :minutes minutes.',
        'ignore' => 'Si vous ne l\'avez pas demandé, vous pouvez ignorer cet e-mail ; rien ne change.',
    ],
    'marketing_consent' => [
        'email' => 'Envoyez-moi des nouvelles et des offres de :store par email. Je peux me désinscrire à tout moment.',
        'sms' => 'Envoyez-moi des nouvelles et des offres de :store par SMS. Je peux me désinscrire à tout moment.',
    ],
    'email_changed' => [
        'subject' => 'L\'adresse e-mail de votre compte a été modifiée',
        'reason' => 'L\'adresse e-mail de votre compte a été modifiée et tous les autres appareils ont été déconnectés. Les e-mails concernant votre compte vont désormais à la nouvelle adresse.',
        'help' => 'Si vous n\'avez pas effectué cette modification, contactez immédiatement le magasin.',
    ],
];
