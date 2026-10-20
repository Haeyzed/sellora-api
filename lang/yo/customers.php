<?php

declare(strict_types=1);

return [
    'email_verification' => [
        'subject' => 'Jẹrisi adirẹsi imeeli rẹ',
        'reason' => 'Jẹrisi pe eyi ni adirẹsi imeeli rẹ, nitorinaa o le rii awọn aṣẹ ti o kọja ati ṣe igbasilẹ data rẹ.',
        'action' => 'Jẹrisi imeeli mi',
        'expiry' => 'Ọna asopọ yii ṣiṣẹ ni ẹẹkan ati pari ni awọn wakati :hours.',
        'ignore' => 'Ti o ko ba ṣẹda akọọlẹ kan, o le foju parẹ imeeli yii.',
    ],
    'email_change' => [
        'subject' => 'Jẹrisi adirẹsi imeeli titun rẹ',
        'reason' => 'O beere lati lo adirẹsi yii fun akọọlẹ rẹ. Ṣii ọna asopọ yii lakoko ti o wọle lati ṣe iyipada.',
        'action' => 'Lo imeeli yii',
        'expiry' => 'Ọna asopọ yii ṣiṣẹ ni ẹẹkan ati pari ni awọn iṣẹju :minutes.',
        'ignore' => 'Ti o ko ba beere fun eyi, o le foju imeeli yii; ko si ohun ayipada.',
    ],
    'marketing_consent' => [
        'email' => 'Fi awọn iroyin ranṣẹ si mi ati awọn ipese lati :store nipasẹ imeeli. Mo le yowo kuro ni igbakugba.',
        'sms' => 'Fi awọn iroyin ranṣẹ si mi ati awọn ipese lati :store nipasẹ ifọrọranṣẹ. Mo le yowo kuro ni igbakugba.',
    ],
    'email_changed' => [
        'subject' => 'Adirẹsi imeeli akọọlẹ rẹ ti yipada',
        'reason' => 'Adirẹsi imeeli ti akọọlẹ rẹ ti yipada, ati pe gbogbo ẹrọ miiran ti jade. Awọn imeeli nipa akọọlẹ rẹ ni bayi lọ si adirẹsi tuntun.',
        'help' => 'Ti o ko ba ṣe iyipada yii, kan si ile itaja taara.',
    ],
];
