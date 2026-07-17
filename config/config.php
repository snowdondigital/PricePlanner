<?php
declare(strict_types=1);

return [
    'site_name' => 'PricePlan',
    'timezone' => 'Europe/London',
    'currency' => 'GBP',
    'updates' => [
        'enabled' => true,
        'repository' => 'snowdondigital/PricePlanner',
        'asset' => 'priceplan-update.zip',
        'timeout' => 20,
    ],
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'your_database_name',
        'user' => 'your_database_user',
        'password' => 'your_database_password',
        'charset' => 'utf8mb4',
    ],
    'defaults' => [
        'vat_rate' => 0.20,
        'target_margin' => 0.80,
        'trade_discount' => 0.40,
        'minimum_margin' => 0.20,
    ],
    'session' => [
        'name' => 'pricing_planner_session',
        'inactivity_lifetime' => 2592000,
    ],
];
