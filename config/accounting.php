<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Invoice Matching Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for three-way matching (PO vs GR vs Invoice)
    |
    */
    'matching' => [
        // Default tolerance values (can be overridden per supplier)
        'default_quantity_tolerance' => 0, // 0%
        'default_price_tolerance' => 0, // 0%
        'default_amount_tolerance' => 0, // 0%

        // Rules
        'allow_under_invoicing' => true, // Invoice qty < GR qty
        'allow_over_invoicing' => false, // Invoice qty > GR qty - NOT ALLOWED
        'require_approval_if_variance' => true,

        // Auto-approval threshold (in IDR)
        'auto_approve_threshold' => null, // null = always require approval for variance
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Configuration
    |--------------------------------------------------------------------------
    */
    'payment' => [
        'default_method' => 'BANK_TRANSFER',
        'available_methods' => [
            'BANK_TRANSFER' => 'Bank Transfer',
            'CASH' => 'Cash',
            'CHECK' => 'Check',
            'GIRO' => 'Giro',
        ],

        // Default payment terms
        'default_terms' => 'CASH',
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Number Format
    |--------------------------------------------------------------------------
    */
    'invoice_number_format' => [
        'prefix' => 'INV',
        'date_format' => 'Ym', // YYYYMM
        'padding' => 4, // 0001, 0002, etc.
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Number Format
    |--------------------------------------------------------------------------
    */
    'payment_number_format' => [
        'prefix' => 'PAY',
        'date_format' => 'Ym', // YYYYMM
        'padding' => 4, // 0001, 0002, etc.
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Configuration
    |--------------------------------------------------------------------------
    */
    'uploads' => [
        'invoice' => [
            'disk' => 'local',
            'path' => 'invoices',
            'max_size' => 10240, // 10MB in KB
            'allowed_types' => ['pdf', 'jpg', 'jpeg', 'png'],
        ],
        'payment_proof' => [
            'disk' => 'local',
            'path' => 'payment-proofs',
            'max_size' => 5120, // 5MB in KB
            'allowed_types' => ['pdf', 'jpg', 'jpeg', 'png'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Configuration
    |--------------------------------------------------------------------------
    */
    'currency' => [
        'default' => 'IDR',
        'symbol' => 'Rp',
        'decimal_places' => 2,
        'thousand_separator' => '.',
        'decimal_separator' => ',',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax Configuration
    |--------------------------------------------------------------------------
    */
    'tax' => [
        'default_rate' => 0, // 0% (optional, bisa 11% untuk PPN)
        'rates' => [
            0 => 'No Tax',
            11 => 'PPN 11%',
        ],
    ],
];
