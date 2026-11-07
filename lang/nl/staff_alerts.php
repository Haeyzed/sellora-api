<?php

declare(strict_types=1);

return [
    'new_order' => [
        'title' => 'Nieuwe bestelling :order',
        'message' => 'Bestelling :order is geplaatst voor :amount.',
    ],
    'payment_needs_review' => [
        'title' => 'Een betaling voor bestelling :order moet worden gecontroleerd',
        'message' => 'Een betaling van :amount voor bestelling :order kwam niet overeen met wat werd verwacht. Controleer haar voordat u de bestelling verzendt.',
    ],
    'payment_unresolved' => [
        'title' => 'Een betaling voor bestelling :order is niet afgehandeld',
        'message' => 'De betaalprovider heeft de betaling van :amount voor bestelling :order niet afgerond. Controleer haar bij de provider.',
    ],
    'payment_overpaid' => [
        'title' => 'Bestelling :order is te veel betaald',
        'message' => 'De koper heeft :amount meer betaald dan bestelling :order kost. Betaal het verschil terug.',
    ],
    'payment_disputed' => [
        'title' => 'Een betaling wordt betwist',
        'message' => 'Een koper betwist een betaling via :gateway. Reageer op de betwisting in het dashboard van de provider vóór de termijn.',
    ],
    'payment_gateway_needs_reconnecting' => [
        'title' => 'Verbind :gateway opnieuw',
        'message' => ':gateway heeft de sleutels van de winkel geweigerd. Betalingen via deze provider blijven openstaan tot u haar opnieuw verbindt.',
    ],
    'order_paid_after_cancellation' => [
        'title' => 'Geannuleerde bestelling :order is betaald',
        'message' => 'Er kwam een betaling binnen voor bestelling :order nadat deze was geannuleerd. De bestelling blijft geannuleerd; betaal de betaling terug.',
    ],
    'order_refunded_beyond_paid' => [
        'title' => 'Voor bestelling :order is meer terugbetaald dan betaald',
        'message' => 'De terugbetalingen voor bestelling :order zijn samen meer dan de koper betaalde. Controleer de terugbetalingen van de bestelling.',
    ],
    'refund_needs_review' => [
        'title' => 'Een terugbetaling voor bestelling :order moet worden gecontroleerd',
        'message' => 'De terugbetaling van :amount voor bestelling :order moet worden gecontroleerd: het antwoord van de provider was onduidelijk.',
    ],
    'refund_failed' => [
        'title' => 'Een terugbetaling voor bestelling :order is mislukt',
        'message' => 'De terugbetaling van :amount voor bestelling :order is mislukt. Betaal de koper op een andere manier terug of probeer het opnieuw.',
    ],
    'refund_still_pending' => [
        'title' => 'Een terugbetaling voor bestelling :order staat nog open',
        'message' => 'De terugbetaling van :amount voor bestelling :order wacht al dagen bij de provider. Controleer haar bij de provider.',
    ],
    'refund_succeeded_after_failing' => [
        'title' => 'Een mislukte terugbetaling voor bestelling :order is toch gelukt',
        'message' => 'De terugbetaling van :amount voor bestelling :order, gemeld als mislukt, is toch gelukt. Als u opnieuw hebt terugbetaald, heeft de koper twee keer geld teruggekregen.',
    ],
    'payment_hold_keeps_failing' => [
        'title' => 'Een betaling voor bestelling :order kan niet worden afgerond',
        'message' => 'Het afronden van de :method-betaling van :amount voor bestelling :order mislukt steeds. Het wordt elk uur opnieuw geprobeerd.',
    ],
    'order_payments_keep_failing' => [
        'title' => 'De betalingen van bestelling :order kunnen niet worden vastgelegd',
        'message' => 'Het vastleggen van de betalingen van bestelling :order mislukt steeds. Het wordt elk uur opnieuw geprobeerd.',
    ],
    'committed_payment_refund_failed' => [
        'title' => 'Geld voor geannuleerde bestelling :order kan niet worden teruggegeven',
        'message' => 'De :method-betaling van :amount voor geannuleerde bestelling :order kon niet worden teruggegeven. Het wordt elk uur opnieuw geprobeerd en de bestelling is de terugbetaling nog verschuldigd.',
    ],
    'exchange_settlement_refused' => [
        'title' => 'Een ruil voor bestelling :order is niet afgerekend',
        'message' => 'Vervangende bestelling :order is betaald, maar het afrekenen van de retour is geweigerd (:reason). Reken haar opnieuw af vanuit de retour.',
    ],
    'cash_collection_overdue' => [
        'title' => 'Contant geld voor bestelling :order is niet vastgelegd',
        'message' => 'Bestelling :order is bezorgd, maar de :amount contant is niet vastgelegd als ontvangen.',
    ],
    'delivery_failed' => [
        'title' => 'Een bezorging van bestelling :order is mislukt',
        'message' => 'De bezorger kon bestelling :order niet bezorgen. Regel een nieuwe bezorging of neem contact op met de koper.',
    ],
    'stock_running_low' => [
        'title' => ':product raakt op',
        'message' => 'Nog maar :available van :product (SKU :sku).',
    ],
    'variant_sold_out' => [
        'title' => ':product is uitverkocht',
        'message' => ':product (SKU :sku) is uitverkocht.',
    ],
    'return_requested' => [
        'title' => 'Retour aangevraagd voor bestelling :order',
        'message' => 'Een koper wil artikelen uit bestelling :order retourneren.',
    ],
    'waiting' => [
        'title' => 'Er wachten meer meldingen',
        'message' => 'Er kwamen dit uur meer meldingen binnen dan er worden gemaild. Bekijk ze allemaal in uw dashboard.',
    ],
    'see_dashboard' => 'Bekijk het in het dashboard van uw winkel.',
];
