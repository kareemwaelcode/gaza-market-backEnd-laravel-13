<?php

return [

    'ticker' => [
        'ttl' => (int) env('PRICE_TICKER_TTL', 60),
        'limit' => (int) env('PRICE_TICKER_LIMIT', 30),
    ],

];