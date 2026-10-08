<?php

return [
    'plan' => [
        'title' => 'Ihr Tarif',
        'name' => 'Tarif :plan',
        'price' => ':price pro Monat, :interval abgerechnet',
        'usage' => ':used von :limit Rechnungen in diesem Monat',
        'renews' => 'Verlängert sich am :date',
    ],
    'payment_method' => [
        'title' => 'Zahlungsmethode',
        'card' => ':brand mit Endziffern :last4, gültig bis :expiry',
    ],
    'history' => [
        'title' => 'Abrechnungsverlauf',
        'invoice' => 'Rechnung',
        'date' => 'Datum',
        'status' => 'Status',
        'amount' => 'Betrag',
    ],
    'status' => [
        'paid' => 'Bezahlt',
        'open' => 'Offen',
        'void' => 'Storniert',
    ],
    'seats' => '{0} Keine Plätze|{1} :count Platz|[2,*] :count Plätze',
    'cancel' => 'Abo kündigen',
];
