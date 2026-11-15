<?php

return [

    // كاش الـ feed والبحث (app/Support/ApiCache.php). أي TTL = 0 بيطفي الكاش (للتطوير فقط).
    'cache' => [
        // اسم الـ store من config/cache.php (مثلاً redis). فاضي = الـ store الافتراضي.
        'store' => env('API_CACHE_STORE'),

        // الـ feed: عدّادات التصويت (confirm/report) بتتأخر لحد هالمدة، لأن التصويت ما بيبطل الكاش
        'feed_ttl' => (int) env('PRICE_FEED_CACHE_TTL', 60),

        'search_ttl' => (int) env('PRODUCT_SEARCH_CACHE_TTL', 300),

        // نتيجة فاضية (غالباً كلمة/فلتر عشوائي) بتنكاش مدة أقصر
        'empty_ttl' => (int) env('API_CACHE_EMPTY_TTL', 60),

        // ما بنكنّش الصفحات الأبعد من هالرقم (تقليل المفاتيح العشوائية)
        'max_page' => (int) env('API_CACHE_MAX_PAGE', 20),
    ],

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