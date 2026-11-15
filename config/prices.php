<?php

return [

    'ticker' => [
        // مدة صلاحية الشريط بالكاش (ثواني). لازم تكون أكبر من فترة التحديث (refresh_minutes * 60)
        // عشان الكاش ما ينتهي بين تحديثين فيرجع الزائر يضرب الداتابيز. 0 = بلا كاش (للتطوير فقط).
        'ttl' => (int) env('PRICE_TICKER_TTL', 1800),
        'limit' => (int) env('PRICE_TICKER_LIMIT', 30),

        // الأمر المجدول prices:refresh-ticker بيبني الشريط كل N دقيقة (1 إلى 59، يفضّل رقم بيقسم 60)
        'refresh_minutes' => (int) env('PRICE_TICKER_REFRESH_MINUTES', 10),

        // إعادة بناء كاملة كل ليلة بوقت قليل الزوار (HH:MM) وبمنطقة زمنية صريحة (التطبيق نفسه على UTC)
        'nightly_at' => env('PRICE_TICKER_NIGHTLY_AT', '03:05'),
        'timezone' => env('PRICE_TICKER_TIMEZONE', 'Asia/Gaza'),
    ],

];