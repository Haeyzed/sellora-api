<?php

declare(strict_types=1);

return [
    'legal_documents' => [
        'manage' => [
            'label' => 'Rechtsdokumente verwalten',
            'description' => 'Entwerfen, bearbeiten und veröffentlichen Sie die von Händlern akzeptierten Rechtsdokumente, beispielsweise die Nutzungsbedingungen.',
        ],
    ],
    'stores' => [
        'view' => [
            'label' => 'Shops anzeigen',
            'description' => 'Sehen Sie sich jedes Geschäft, seinen Status, Plan, Funktionsgewährungen und Limitüberschreibungen an.',
        ],
        'manage' => [
            'label' => 'Shops verwalten',
            'description' => 'Unterbrechen, reaktivieren, schließen und wiederherstellen Sie Stores und versuchen Sie erneut, einen Store einzurichten, dessen Einrichtung fehlgeschlagen ist.',
        ],
        'grant' => [
            'label' => 'Funktionen und Limits gewähren',
            'description' => 'Bieten Sie Filialen Module und Integrationen außerhalb ihres Plans an und ändern Sie deren Nutzungsbeschränkungen.',
        ],
        'export' => [
            'label' => 'Shopdaten exportieren',
            'description' => 'Exportieren Sie alle Daten eines Shops, einschließlich der persönlichen Daten jedes Kunden.',
        ],
    ],
    'staff' => [
        'view' => [
            'label' => 'Team anzeigen',
            'description' => 'Sehen Sie sich das Team an: Mitarbeiter, ihre Rollen und ausstehende Einladungen.',
        ],
        'invite' => [
            'label' => 'Mitarbeiter einladen',
            'description' => 'Laden Sie Personen ein, dem Team beizutreten, und senden Sie Einladungen erneut oder stornieren Sie sie.',
        ],
        'manage' => [
            'label' => 'Mitarbeiter verwalten',
            'description' => 'Ändern Sie die Rollen der Mitarbeiter und deaktivieren oder reaktivieren Sie sie.',
        ],
    ],
    'catalog' => [
        'view' => [
            'label' => 'Katalog anzeigen',
            'description' => 'Sehen Sie sich den Katalog an: Marken, Kategorien und Produkte, einschließlich Entwürfe, archivierte Artikel und den Papierkorb.',
        ],
        'manage' => [
            'label' => 'Katalog verwalten',
            'description' => 'Erstellen und ändern Sie Marken, Kategorien und Produkte, verschieben Sie sie in den Papierkorb und stellen Sie sie wieder her.',
        ],
    ],
    'inventory' => [
        'view' => [
            'label' => 'Bestand anzeigen',
            'description' => 'Sehen Sie sich Lagerbestände, Standorte und den Verlauf jeder Lagerbestandsänderung an.',
        ],
        'adjust' => [
            'label' => 'Bestand anpassen',
            'description' => 'Erhalten Sie Lagerbestände, korrigieren Sie die Lagerbestände und ändern Sie die Art und Weise, wie mit den Lagerbeständen jeder Variante umgegangen wird.',
        ],
        'costs' => [
            'label' => 'Stückkosten',
            'description' => 'Sehen und ändern Sie, was jede Einheit im Geschäft kostet. Die Kosten sind intern und werden den Kunden nie angezeigt.',
        ],
    ],
    'customers' => [
        'view' => [
            'label' => 'Kunden anzeigen',
            'description' => 'Sehen Sie Kunden, ihre Profile, Adressen, Mitarbeiternotizen und Gruppen, einschließlich deaktivierter und gelöschter Konten.',
        ],
        'manage' => [
            'label' => 'Kunden verwalten',
            'description' => 'Ändern Sie Kundenprofile, deaktivieren, reaktivieren, löschen und wiederherstellen Sie Konten, fügen Sie Notizen hinzu oder löschen Sie sie und verwalten Sie Kundengruppen.',
        ],
        'tax_exemption' => [
            'label' => 'Steuerbefreiungen',
            'description' => 'Machen Sie einen Kunden steuerbefreit oder beenden Sie die Steuerbefreiung, nachdem Sie seine Steuernummer überprüft haben.',
        ],
        'privacy' => [
            'label' => 'Datenanfragen von Kunden',
            'description' => 'Laden Sie alle persönlichen Daten eines Kunden herunter oder löschen Sie sie auf Wunsch des Kunden endgültig.',
        ],
    ],
    'promotions' => [
        'view' => [
            'label' => 'Aktionen anzeigen',
            'description' => 'Sehen Sie sich Werbeaktionen, ihre Codes und die Häufigkeit ihrer Nutzung an und sehen Sie sich eine Vorschau an, was ein Warenkorb enthalten würde.',
        ],
        'manage' => [
            'label' => 'Aktionen verwalten',
            'description' => 'Werbeaktionen erstellen, ändern, aktivieren, deaktivieren und archivieren sowie deren Codes hinzufügen, generieren oder ändern.',
        ],
    ],
    'shipping' => [
        'view' => [
            'label' => 'Versand anzeigen',
            'description' => 'Sehen Sie sich Versandzonen, Methoden und deren Tarife an und sehen Sie sich an, wie viel ein Warenkorb für den Versand kosten würde.',
        ],
        'manage' => [
            'label' => 'Versand verwalten',
            'description' => 'Versandzonen anlegen, ändern und löschen sowie Versandarten und deren Tarife anlegen, ändern und archivieren.',
        ],
    ],
    'orders' => [
        'view' => [
            'label' => 'Bestellungen anzeigen',
            'description' => 'Sehen Sie sich Bestellungen, deren Zeilen, Zahlungen, Verlauf und Sendungen an.',
        ],
        'manage' => [
            'label' => 'Bestellungen verwalten',
            'description' => 'Stornieren Sie Bestellungen, fügen Sie Notizen und Tags hinzu und korrigieren Sie die Kontaktdaten und Lieferadressen der Käufer.',
        ],
        'fulfil' => [
            'label' => 'Bestellungen ausführen',
            'description' => 'Erstellen Sie Sendungen, fügen Sie die Sendungsverfolgung hinzu und markieren Sie, was noch übrig ist, zur Abholung bereit ist oder eingetroffen ist.',
        ],
    ],
    'invoices' => [
        'view' => [
            'label' => 'Rechnungen anzeigen',
            'description' => 'Sehen Sie sich Rechnungen und Gutschriften mit den Details des Käufers an und laden Sie deren PDFs herunter.',
        ],
    ],
    'payments' => [
        'view' => [
            'label' => 'Zahlungen anzeigen',
            'description' => 'Sehen Sie sich Zahlungen, Rückerstattungen und die Verbindung von Zahlungsgateways an.',
        ],
        'confirm' => [
            'label' => 'Zahlungen bestätigen',
            'description' => 'Bestätigen Sie, dass eine Banküberweisung oder eine Nachnahme eingegangen ist, und fragen Sie erneut bei einem Gateway nach einer Zahlung.',
        ],
        'refund' => [
            'label' => 'Zahlungen erstatten',
            'description' => 'Erstatten Sie Zahlungen über das Gateway oder wie von Hand erfasst.',
        ],
        'manage_gateways' => [
            'label' => 'Zahlungsanbieter verwalten',
            'description' => 'Verbinden, trennen und richten Sie Zahlungsgateways und Zahlungsmethoden ein.',
        ],
    ],
    'pricing' => [
        'view' => [
            'label' => 'Steuern und Währungen anzeigen',
            'description' => 'Sehen Sie sich Steuerklassen, Steuersätze, die Klasse der einzelnen Varianten sowie die Währungen und Wechselkurse des Shops an.',
        ],
        'manage' => [
            'label' => 'Steuern und Währungen verwalten',
            'description' => 'Ändern Sie Steuerklassen, Steuersätze, Steuerklassen der Varianten, die Währungen des Shops, Wechselkurse und Festpreise.',
        ],
    ],
    'settings' => [
        'view' => [
            'label' => 'Einstellungen anzeigen',
            'description' => 'Sehen Sie sich die Einstellungen des Shops an: Name, Land, Währung, Sprachen, Steuermodus, Einheiten und Kontaktdaten.',
        ],
        'manage' => [
            'label' => 'Einstellungen verwalten',
            'description' => 'Ändern Sie die Einstellungen des Shops. Die Verpflichtung zur Zwei-Faktor-Authentifizierung für das Personal verbleibt beim Eigentümer.',
        ],
    ],
    'roles' => [
        'view' => [
            'label' => 'Rollen anzeigen',
            'description' => 'Sehen Sie sich die Rollen des Shops an und erfahren Sie, was jeder einzelne erlaubt.',
        ],
        'manage' => [
            'label' => 'Rollen verwalten',
            'description' => 'Erstellen, benennen und löschen Sie Rollen und wählen Sie aus, was jede Rolle zulässt.',
        ],
    ],
    'delivery' => [
        'view' => [
            'label' => 'Lieferungen anzeigen',
            'description' => 'Die Fahrer und Lieferungen des Shops ansehen.',
        ],
        'manage' => [
            'label' => 'Lieferungen verwalten',
            'description' => 'Sendungen an Fahrer geben und ihre Lieferungen weiterführen, erneut versuchen, zurücknehmen oder stornieren.',
        ],
        'cash' => [
            'manage' => [
                'label' => 'Bargeld von Fahrern erfassen',
                'description' => 'Bargeld erfassen, das Fahrer abgeben, nie mehr als sie haben.',
            ],
        ],
    ],
    'drivers' => [
        'manage' => [
            'label' => 'Fahrer verwalten',
            'description' => 'Fahrer hinzufügen, ändern, deaktivieren und löschen und ihnen Codes zur Wahl einer PIN geben.',
        ],
    ],
    'returns' => [
        'view' => [
            'label' => 'Retouren anzeigen',
            'description' => 'Retouren mit Fotos und Verlauf sowie die Retoureneinstellungen ansehen.',
        ],
        'manage' => [
            'label' => 'Retouren verwalten',
            'description' => 'Retouren anlegen, genehmigen, ablehnen, annehmen, prüfen und stornieren und die Retoureneinstellungen ändern.',
        ],
    ],
];
