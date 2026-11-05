<?php

declare(strict_types=1);

/*
| Refunds: where a refund can go, as the customer is shown the choice.
| Back to how they paid is core's own and always offered; modules name
| their own destinations (such as store credit) themselves.
*/

return [

    'destinations' => [
        'original_payment' => 'उसी तरीके से जिससे आपने भुगतान किया',
        'exchange' => 'आपके बदले वाले ऑर्डर के लिए',
    ],

];
