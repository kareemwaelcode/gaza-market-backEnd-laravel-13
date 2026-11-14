<?php

return [

    /*
    | شريط الأسعار في الهيرو (GET api/prices/ticker)
    | ttl: مدة الكاش بالثواني؛ شبكة أمان فوق الإبطال الصريح عند أي تغيير (0 = بدون كاش)
    | limit: عدد الأسعار بالشريط
    */
    'ticker' => [
        'ttl' => (int) env('PRICE_TICKER_TTL', 60),
        'limit' => (int) env('PRICE_TICKER_LIMIT', 10),
    ],

];