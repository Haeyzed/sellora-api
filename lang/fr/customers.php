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
    'social_link' => [
        'subject' => 'Quelqu\'un connecte un accès à votre compte',
        'reason' => 'Quelqu\'un s\'est connecté avec :provider en utilisant cette adresse e-mail et a demandé à la relier à votre compte. Confirmez uniquement si c\'était vous : une fois connectée, la connexion avec :provider ouvre votre compte.',
        'action' => 'Oui, connecter :provider',
        'expiry' => 'Ce lien fonctionne une fois et expire dans :minutes minutes.',
        'ignore' => 'Si ce n\'était pas vous, ignorez cet e-mail : rien n\'est connecté et votre compte reste inchangé.',
    ],
    'erasure' => [
        'subject' => 'Confirmer la suppression de votre compte',
        'reason' => 'Vous avez demandé à supprimer votre compte dans ce magasin. Ouvrez ce lien pendant que vous êtes connecté pour confirmer : tout ce que le magasin détient à votre sujet est effacé et cela ne peut pas être annulé.',
        'action' => 'Supprimer mon compte',
        'expiry' => 'Ce lien fonctionne une fois et expire dans :minutes minutes.',
        'ignore' => 'Si vous ne l\'avez pas demandé, ignorez cet e-mail : rien n\'est supprimé. Quelqu\'un d\'autre peut être connecté à votre compte, alors déconnectez-vous de vos autres appareils.',
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
