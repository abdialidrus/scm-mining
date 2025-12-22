<?php

return [
    // Default currency for procurement documents.
    'default_currency' => env('PROCUREMENT_DEFAULT_CURRENCY', 'IDR'),

    // Tax rate for PPN (e.g. 0.11 = 11%).
    'ppn_rate' => (float) env('PROCUREMENT_PPN_RATE', 0.11),
];
