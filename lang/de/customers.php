<?php

declare(strict_types=1);

return [
    'email_verification' => [
        'subject' => 'Bestätigen Sie Ihre E-Mail-Adresse',
        'reason' => 'Bestätigen Sie, dass es sich hierbei um Ihre E-Mail-Adresse handelt, damit Sie Ihre vergangenen Bestellungen einsehen und Ihre Daten herunterladen können.',
        'action' => 'Bestätige meine E-Mail',
        'expiry' => 'Dieser Link funktioniert einmal und läuft in :hours Stunden ab.',
        'ignore' => 'Wenn Sie kein Konto erstellt haben, können Sie diese E-Mail ignorieren.',
    ],
    'email_change' => [
        'subject' => 'Bestätigen Sie Ihre neue E-Mail-Adresse',
        'reason' => 'Sie haben darum gebeten, diese Adresse für Ihr Konto zu verwenden. Öffnen Sie diesen Link, während Sie angemeldet sind, um die Änderung vorzunehmen.',
        'action' => 'Verwenden Sie diese E-Mail',
        'expiry' => 'Dieser Link funktioniert einmal und läuft in :minutes Minuten ab.',
        'ignore' => 'Wenn Sie nicht danach gefragt haben, können Sie diese E-Mail ignorieren; nichts ändert sich.',
    ],
    'marketing_consent' => [
        'email' => 'Senden Sie mir Neuigkeiten und Angebote von :store per E-Mail. Ich kann mich jederzeit abmelden.',
        'sms' => 'Senden Sie mir Neuigkeiten und Angebote von :store per SMS. Ich kann mich jederzeit abmelden.',
    ],
    'email_changed' => [
        'subject' => 'Die E-Mail-Adresse Ihres Kontos wurde geändert',
        'reason' => 'Die E-Mail-Adresse Ihres Kontos wurde geändert und alle anderen Geräte wurden abgemeldet. E-Mails zu Ihrem Konto werden jetzt an die neue Adresse gesendet.',
        'help' => 'Wenn Sie diese Änderung nicht vorgenommen haben, wenden Sie sich umgehend an das Geschäft.',
    ],
];
