<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
        'https://bamboeoerip.com',       // app customer (produksi)
        'https://www.bamboeoerip.com',
        'https://dev.bamboeoerip.com',    // app customer (staging)
        'https://kelola.bamboeoerip.com', // dashboard admin
        'https://owner.bamboeoerip.com',  // extranet owner properti
        'https://www.owner.bamboeoerip.com',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:3000',
    ],

    // Izinkan SEMUA subdomain bamboeoerip.com, termasuk bertingkat seperti
    // www.owner.bamboeoerip.com (dulu hanya 1 tingkat -> www.owner. ditolak CORS)
    'allowed_origins_patterns' => [
        '#^https?://([a-z0-9-]+\.)*bamboeoerip\.com$#i',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supported_credentials' => false,

];
