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
    'social_link' => [
        'subject' => 'Jemand verbindet eine Anmeldung mit Ihrem Konto',
        'reason' => 'Jemand hat sich mit :provider über diese E-Mail-Adresse angemeldet und darum gebeten, sie mit Ihrem Konto zu verbinden. Bestätigen Sie dies nur, wenn es Sie war: Nach der Verbindung öffnet die Anmeldung mit :provider Ihr Konto.',
        'action' => 'Ja, :provider verbinden',
        'expiry' => 'Dieser Link funktioniert einmal und läuft in :minutes Minuten ab.',
        'ignore' => 'Wenn das nicht Sie waren, ignorieren Sie diese E-Mail: Es ist nichts verbunden, und Ihr Konto bleibt unverändert.',
    ],
    'erasure' => [
        'subject' => 'Löschen Ihres Kontos bestätigen',
        'reason' => 'Sie haben darum gebeten, Ihr Konto in diesem Shop zu löschen. Öffnen Sie diesen Link, während Sie angemeldet sind, um zu bestätigen: Alles, was der Shop über Sie gespeichert hat, wird gelöscht und kann nicht rückgängig gemacht werden.',
        'action' => 'Mein Konto löschen',
        'expiry' => 'Dieser Link funktioniert einmal und läuft in :minutes Minuten ab.',
        'ignore' => 'Wenn Sie das nicht angefordert haben, ignorieren Sie diese E-Mail: Es wurde nichts gelöscht. Jemand anderes kann bei Ihrem Konto angemeldet sein, also melden Sie sich von Ihren anderen Geräten ab.',
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
