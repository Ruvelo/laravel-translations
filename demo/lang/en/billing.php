<?php

return [
    'plan' => [
        'title' => 'Your plan',
        'name' => ':plan plan',
        'price' => ':price a month, billed :interval',
        'usage' => ':used of :limit invoices used this month',
        'renews' => 'Renews on :date',
    ],
    'payment_method' => [
        'title' => 'Payment method',
        'card' => ':brand ending :last4, expires :expiry',
    ],
    'history' => [
        'title' => 'Billing history',
        'invoice' => 'Invoice',
        'date' => 'Date',
        'status' => 'Status',
        'amount' => 'Amount',
        'empty' => 'No invoices yet. Your first one arrives at the end of the month.',
    ],
    'status' => [
        'paid' => 'Paid',
        'open' => 'Open',
        'overdue' => 'Overdue',
        'void' => 'Void',
    ],
    'seats' => '{0} No seats|{1} :count seat|[2,*] :count seats',
    'trial_ends' => 'Your trial ends in :days days. <a href=":url">Choose a plan</a> to keep your invoices.',
    'cancel' => 'Cancel subscription',
    'cancel_confirm' => 'Cancel your subscription? You keep access until :date.',
];
