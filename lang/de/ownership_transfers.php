<?php

declare(strict_types=1);

return [
    'offer' => [
        'subject' => ':owner möchte Sie zum Besitzer seines Shops machen',
        'reason' => ':owner hat Sie gebeten, die Rolle des Ladenbesitzers zu übernehmen. Als Eigentümer können Sie alles im Laden erledigen und der Ladenvertrag mit Sellora wird Ihnen gehören.',
        'terms' => 'Um zu akzeptieren, melden Sie sich im Store-Dashboard an und akzeptieren Sie die aktuellen Nutzungsbedingungen von Sellora. Es ändert sich nichts, bis Sie es tun.',
        'action' => 'Überprüfen Sie die Übertragung',
        'expiry' => 'Dieses Angebot läuft in :hours Stunden ab.',
        'ignore' => 'Wenn Sie den Laden nicht übernehmen möchten, ignorieren Sie diese E-Mail.',
    ],
    'completed' => [
        'subject' => 'Der Laden :store hat einen neuen Besitzer',
        'new_owner' => 'Sie sind jetzt der Besitzer des Shops :store.',
        'previous_owner' => ':owner ist jetzt Eigentümer des Shops :store. Sie behalten nur die Rollen, die Sie beim Start der Übertragung ausgewählt haben.',
        'not_you' => 'Wenn Sie damit nicht gerechnet haben, wenden Sie sich umgehend an den Sellora-Support.',
    ],
];
