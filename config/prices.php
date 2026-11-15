<?php

return [

    'cache' => [

        'store' => env('API_CACHE_STORE'),

        'feed_ttl' => (int) env('PRICE_FEED_CACHE_TTL', 60),

        'search_ttl' => (int) env('PRODUCT_SEARCH_CACHE_TTL', 300),

        'market_ttl' => (int) env('MARKET_FEED_CACHE_TTL', 120),

        'market_detail_ttl' => (int) env('MARKET_DETAIL_CACHE_TTL', 120),

        'places_ttl' => (int) env('PLACES_FEED_CACHE_TTL', 120),

        'place_detail_ttl' => (int) env('PLACE_DETAIL_CACHE_TTL', 300),

        'lookups_ttl' => (int) env('LOOKUPS_CACHE_TTL', 3600),

        'empty_ttl' => (int) env('API_CACHE_EMPTY_TTL', 60),

        'max_page' => (int) env('API_CACHE_MAX_PAGE', 20),
    ],

    'ticker' => [

        'ttl' => (int) env('PRICE_TICKER_TTL', 1800),
        'limit' => (int) env('PRICE_TICKER_LIMIT', 30),

        'refresh_minutes' => (int) env('PRICE_TICKER_REFRESH_MINUTES', 10),

        'nightly_at' => env('PRICE_TICKER_NIGHTLY_AT', '03:05'),
        'timezone' => env('PRICE_TICKER_TIMEZONE', 'Asia/Gaza'),
    ],

];