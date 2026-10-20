<?php

return [
    'catalog_base_url' => env('KLAVIYO_CATALOG_BASE_URL', 'https://bali.hr'),
    'catalog_refresh_hours' => 3,

    // Bali apparel: source Croatian standard VAT -> destination standard VAT.
    // Product-specific classes can override these via tax_rates.settings.destination_rates.
    'destination_vat' => [
        'DE' => ['25' => 19.0, '19' => 19.0, '20' => 19.0],
        'AT' => ['25' => 20.0, '19' => 20.0, '20' => 20.0],
    ],
];
