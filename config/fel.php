<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FEL Configuration (Factura Electrónica en Línea - SAT Guatemala)
    |--------------------------------------------------------------------------
    |
    | Configuration for FEL integration with SAT Guatemala
    | This is a base structure for future API integration
    |
    */

    // Certifier API Configuration (to be implemented)
    'certifier' => [
        'api_url' => env('FEL_API_URL', 'https://api.certifier.example.com'),
        'api_key' => env('FEL_API_KEY', ''),
        'api_secret' => env('FEL_API_SECRET', ''),
        'timeout' => env('FEL_API_TIMEOUT', 30),
    ],

    // Seller Information (Emisor)
    'seller_nit' => env('FEL_SELLER_NIT', '12345678'),
    'seller_name' => env('FEL_SELLER_NAME', 'Mi Empresa S.A.'),
    'seller_trade_name' => env('FEL_SELLER_TRADE_NAME', 'Mi Empresa'),
    'seller_address' => env('FEL_SELLER_ADDRESS', 'Ciudad de Guatemala'),
    'seller_postal_code' => env('FEL_SELLER_POSTAL_CODE', '01001'),
    'seller_department' => env('FEL_SELLER_DEPARTMENT', 'Guatemala'),
    'seller_municipality' => env('FEL_SELLER_MUNICIPALITY', 'Guatemala'),
    'seller_email' => env('FEL_SELLER_EMAIL', 'facturacion@miempresa.com'),

    // Invoice Configuration
    'default_currency' => 'GTQ',
    'default_tax_rate' => 0.12, // 12% IVA

    // Document types
    'document_types' => [
        'FACT' => 'Factura',
        'FCAM' => 'Factura Cambiaria',
        'FPEQ' => 'Factura Pequeño Contribuyente',
        'FCAP' => 'Factura Contribuyente Agropecuario',
        'FESP' => 'Factura Especial',
        'NABN' => 'Nota de Abono',
        'RDON' => 'Recibo por Donación',
        'RECI' => 'Recibo',
        'NDEB' => 'Nota de Débito',
        'NCRE' => 'Nota de Crédito',
    ],

    // Enable/disable FEL integration
    'enabled' => env('FEL_ENABLED', false),

    // Simulation mode (for development)
    'simulation_mode' => env('FEL_SIMULATION_MODE', true),
];
