<?php

declare(strict_types=1);

return [
    'legal_documents' => [
        'manage' => [
            'label' => 'Juridische documenten beheren',
            'description' => 'Ontwerp, bewerk en publiceer de juridische documenten die verkopers accepteren, zoals de servicevoorwaarden.',
        ],
    ],
    'stores' => [
        'view' => [
            'label' => 'Winkels bekijken',
            'description' => 'Bekijk elke winkel, de status, het abonnement, de functietoekenningen en limietoverschrijvingen.',
        ],
        'manage' => [
            'label' => 'Winkels beheren',
            'description' => 'Winkels onderbreken, opnieuw activeren, sluiten en herstellen, en opnieuw proberen een winkel op te zetten waarvan de installatie is mislukt.',
        ],
        'grant' => [
            'label' => 'Functies en limieten toekennen',
            'description' => 'Geef winkels modules en integraties buiten hun abonnement, en wijzig hun gebruikslimieten.',
        ],
        'export' => [
            'label' => 'Winkelgegevens exporteren',
            'description' => 'Exporteer alle gegevens van een winkel, inclusief de persoonlijke gegevens van elke klant.',
        ],
    ],
    'staff' => [
        'view' => [
            'label' => 'Team bekijken',
            'description' => 'Bekijk het team: medewerkers, hun rollen en openstaande uitnodigingen.',
        ],
        'invite' => [
            'label' => 'Personeel uitnodigen',
            'description' => 'Nodig mensen uit om zich bij het team aan te sluiten en uitnodigingen opnieuw te verzenden of te annuleren.',
        ],
        'manage' => [
            'label' => 'Personeel beheren',
            'description' => 'Wijzig de rollen van medewerkers en deactiveer of reactiveer ze.',
        ],
    ],
    'catalog' => [
        'view' => [
            'label' => 'Catalogus bekijken',
            'description' => 'Zie de catalogus: merken, categorieën en producten, inclusief concepten, gearchiveerde items en de prullenbak.',
        ],
        'manage' => [
            'label' => 'Catalogus beheren',
            'description' => 'Maak en wijzig merken, categorieën en producten, verplaats ze naar de prullenbak en herstel ze.',
        ],
    ],
    'inventory' => [
        'view' => [
            'label' => 'Voorraad bekijken',
            'description' => 'Bekijk voorraadniveaus, locaties en de geschiedenis van elke voorraadwijziging.',
        ],
        'adjust' => [
            'label' => 'Voorraad aanpassen',
            'description' => 'Ontvang voorraad, corrigeer voorraadtellingen en wijzig de manier waarop de voorraad van elke variant wordt verwerkt.',
        ],
        'costs' => [
            'label' => 'Kosten per eenheid',
            'description' => 'Bekijk en wijzig wat elke eenheid de winkel kost. Kosten zijn intern en worden nooit aan klanten getoond.',
        ],
    ],
    'customers' => [
        'view' => [
            'label' => 'Klanten bekijken',
            'description' => 'Bekijk klanten, hun profielen, adressen, personeelsnotities en groepen, inclusief gedeactiveerde en weggegooide accounts.',
        ],
        'manage' => [
            'label' => 'Klanten beheren',
            'description' => 'Wijzig de profielen van klanten, deactiveer, reactiveer, verwijder en herstel accounts, voeg notities toe of verwijder deze en beheer klantgroepen.',
        ],
        'tax_exemption' => [
            'label' => 'Belastingvrijstellingen',
            'description' => 'Maak een klant vrijgesteld van belasting, of beëindig deze, nadat hij zijn belastingnummer heeft gecontroleerd.',
        ],
        'privacy' => [
            'label' => 'Gegevensverzoeken van klanten',
            'description' => 'Op verzoek van de klant alle persoonsgegevens van een klant downloaden of definitief wissen.',
        ],
    ],
    'promotions' => [
        'view' => [
            'label' => 'Promoties bekijken',
            'description' => 'Bekijk promoties, hun codes en hoe vaak ze zijn gebruikt, en bekijk een voorbeeld van wat een winkelwagentje zou krijgen.',
        ],
        'manage' => [
            'label' => 'Promoties beheren',
            'description' => 'Creëer, wijzig, activeer, deactiveer en archiveer promoties, en voeg hun codes toe, genereer of wijzig deze.',
        ],
    ],
    'shipping' => [
        'view' => [
            'label' => 'Verzending bekijken',
            'description' => 'Bekijk verzendzones, methoden en hun tarieven, en bekijk wat een winkelmandje voor verzending zou betalen.',
        ],
        'manage' => [
            'label' => 'Verzending beheren',
            'description' => 'Creëer, wijzig en verwijder verzendzones, en creëer, wijzig en archiveer verzendmethoden en hun tarieven.',
        ],
    ],
    'orders' => [
        'view' => [
            'label' => 'Bestellingen bekijken',
            'description' => 'Bekijk bestellingen, hun regels, betalingen, geschiedenis en verzendingen.',
        ],
        'manage' => [
            'label' => 'Bestellingen beheren',
            'description' => 'Annuleer bestellingen, voeg notities en tags toe en corrigeer de contactgegevens en verzendadressen van kopers.',
        ],
        'fulfil' => [
            'label' => 'Bestellingen verwerken',
            'description' => 'Maak zendingen aan, voeg tracking toe en markeer wat er nog is, klaar is om opgehaald te worden of is aangekomen.',
        ],
    ],
    'invoices' => [
        'view' => [
            'label' => 'Facturen bekijken',
            'description' => 'Bekijk facturen en creditnota\'s met de gegevens van de koper en download hun PDF-bestanden.',
        ],
    ],
    'payments' => [
        'view' => [
            'label' => 'Betalingen bekijken',
            'description' => 'Bekijk betalingen, terugbetalingen en hoe betalingsgateways zijn verbonden.',
        ],
        'confirm' => [
            'label' => 'Betalingen bevestigen',
            'description' => 'Bevestig dat er een bankoverschrijving is ontvangen of dat er onder rembours is betaald, en vraag een gateway opnieuw naar een betaling.',
        ],
        'refund' => [
            'label' => 'Betalingen terugbetalen',
            'description' => 'Betalingen terugbetalen, via de gateway of zoals handmatig geregistreerd.',
        ],
        'manage_gateways' => [
            'label' => 'Betaalproviders beheren',
            'description' => 'Betalingsgateways en betaalmethoden verbinden, ontkoppelen en instellen.',
        ],
    ],
    'pricing' => [
        'view' => [
            'label' => 'Belastingen en valuta bekijken',
            'description' => 'Bekijk belastingklassen, belastingtarieven, in welke klasse elke variant zich bevindt, en de valuta\'s en wisselkoersen van de winkel.',
        ],
        'manage' => [
            'label' => 'Belastingen en valuta beheren',
            'description' => 'Wijzig belastingklassen, belastingtarieven, belastingklassen van varianten, de valuta van de winkel, wisselkoersen en vaste prijzen.',
        ],
    ],
    'settings' => [
        'view' => [
            'label' => 'Instellingen bekijken',
            'description' => 'Bekijk de instellingen van de winkel: naam, land, valuta, talen, belastingmodus, eenheden en contactgegevens.',
        ],
        'manage' => [
            'label' => 'Instellingen beheren',
            'description' => 'Wijzig de instellingen van de winkel. Het vereisen van tweefactorauthenticatie voor personeel blijft bij de eigenaar.',
        ],
    ],
    'roles' => [
        'view' => [
            'label' => 'Rollen bekijken',
            'description' => 'Bekijk de rollen van de winkel en wat elke rol toestaat.',
        ],
        'manage' => [
            'label' => 'Rollen beheren',
            'description' => 'Creëer, hernoem en verwijder rollen, en kies wat elke rol toestaat.',
        ],
    ],
    'delivery' => [
        'view' => [
            'label' => 'Leveringen bekijken',
            'description' => 'De bezorgers en bezorgingen van de winkel bekijken.',
        ],
        'manage' => [
            'label' => 'Leveringen beheren',
            'description' => 'Zendingen aan bezorgers geven, en hun bezorgingen verder zetten, opnieuw proberen, terugnemen of annuleren.',
        ],
        'cash' => [
            'manage' => [
                'label' => 'Contant geld van bezorgers vastleggen',
                'description' => 'Contant geld registreren dat bezorgers afdragen, nooit meer dan ze hebben.',
            ],
        ],
    ],
    'drivers' => [
        'manage' => [
            'label' => 'Bezorgers beheren',
            'description' => 'Bezorgers toevoegen, wijzigen, deactiveren en wissen, en hun codes geven om een pincode te kiezen.',
        ],
    ],
    'returns' => [
        'view' => [
            'label' => 'Retouren bekijken',
            'description' => 'Retouren met hun foto\'s en geschiedenis bekijken, en de retourinstellingen.',
        ],
        'manage' => [
            'label' => 'Retouren beheren',
            'description' => 'Retouren starten, goedkeuren, afwijzen, ontvangen, controleren en annuleren, en de retourinstellingen wijzigen.',
        ],
        'exchange' => [
            'label' => 'Ruilen bevestigen',
            'description' => 'De ruil van een retour bevestigen: dit plaatst de vervangende bestelling en verrekent wat de geretourneerde artikelen waard zijn.',
        ],
    ],
    'storefront' => [
        'view' => [
            'label' => 'Webwinkelinhoud bekijken',
            'description' => 'De pagina\'s, menu\'s, banners en webwinkelinstellingen van de winkel bekijken.',
        ],
        'manage' => [
            'label' => 'Webwinkelinhoud beheren',
            'description' => 'Pagina\'s, menu\'s en banners van de winkel toevoegen, wijzigen, publiceren en verwijderen, en de webwinkelinstellingen wijzigen.',
        ],
    ],
];
