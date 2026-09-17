<?php

declare(strict_types=1);

return [
    'public_contact' => [
        'email' => env('STOREFRONT_SUPPORT_EMAIL'),
        'phone' => env('STOREFRONT_SUPPORT_PHONE'),
    ],

    'product_type_fallbacks' => [
        'rental' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (5).png',
        'sale' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (1).png',
        'digital' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (10).png',
    ],
    /*
    |--------------------------------------------------------------------------
    | Local editorial fallback library
    |--------------------------------------------------------------------------
    |
    | Real CMS and media-library uploads always win. These local images keep
    | development, empty states, and newly seeded records visually complete.
    |
    */
    'service_fallbacks' => [
        'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (1).png',
        'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (2).png',
        'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (3).png',
        'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (4).png',
        'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (5).png',
        'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (6).png',
        'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (7).png',
        'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (8).png',
        'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (9).png',
        'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (10).png',
    ],

    'package_fallbacks' => [
        'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (1).png',
        'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (3).png',
        'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (2).png',
        'images/homepage-scenes/hero.png',
    ],
    'vendor_fallbacks' => [
        'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (6).png',
        'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (8).png',
        'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (7).png',
        'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (9).png',
        'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (5).png',
        'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (1).png',
    ],
    'category_fallbacks' => [
        'digital' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (10).png',
        'bouncy' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (5).png',
        'cake' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (1).png',
        'sweet' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (1).png',
        'wedding' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (3).png',
        'engagement' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (3).png',
        'baby' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (4).png',
        'flower' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (6).png',
        'catering' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (7).png',
        'food' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (7).png',
        'photo' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (8).png',
        'video' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (8).png',
        'entertainment' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (9).png',
        'party-equipment' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (10).png',
        'balloon' => 'images/ChatGPT Image Aug 12, 2026, 06_31_27 AM (5).png',
    ],
    'planner_scene' => 'images/ChatGPT Image Aug 12, 2026, 02_10_23 AM.png',
    'closing_scene' => 'images/f69f867c-5541-4577-9c0d-7fdd0fcfed63.png',
];
