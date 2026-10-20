<?php

declare(strict_types=1);

return [
    'offer' => [
        'subject' => ':owner wil jou eigenaar maken van hun winkel',
        'reason' => ':owner heeft u gevraagd om de eigenaar van de winkel over te nemen. Als eigenaar kun je alles in de winkel doen en wordt het winkelcontract met Sellora het jouwe.',
        'terms' => 'Om te accepteren logt u in op het winkeldashboard en accepteert u de huidige servicevoorwaarden van Sellora. Er verandert niets totdat jij dat doet.',
        'action' => 'Controleer de overdracht',
        'expiry' => 'Deze aanbieding vervalt over :hours uur.',
        'ignore' => 'Als u de winkel niet wilt overnemen, kunt u deze e-mail negeren.',
    ],
    'completed' => [
        'subject' => 'De winkel :store heeft een nieuwe eigenaar',
        'new_owner' => 'U bent nu de eigenaar van de winkel :store.',
        'previous_owner' => ':owner is nu de eigenaar van de winkel :store. Je behoudt alleen de rollen die je hebt gekozen toen je met de overdracht begon.',
        'not_you' => 'Als u dit niet had verwacht, neem dan onmiddellijk contact op met Sellora-ondersteuning.',
    ],
];
