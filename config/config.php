<?php
declare(strict_types=1);

return [
    'site_name' => 'PricePlan',
    'timezone' => 'Europe/London',
    'currency' => 'GBP',
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'pricing_planner',
        'user' => 'pricing_user',
        'password' => 'ihbihdbfihrfu*TGyhgf',
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
        'lifetime' => 28800,
    ],
];
