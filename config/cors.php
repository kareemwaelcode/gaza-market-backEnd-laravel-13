<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS للفرونت (المنصة ولوحة صاحب المحل)
    |--------------------------------------------------------------------------
    |
    | FRONTEND_URLS: عناوين الفرونت المسموحة مفصولة بفاصلة، مثال:
    |   https://gazamarket.com,https://www.gazamarket.com
    | فارغة (الافتراضي) = لا يُسمح لأي origin آخر. لا تستخدم "*" لأن الجلسات
    | تحتاج supports_credentials، والمتصفح يرفض "*" مع الـ cookies.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FRONTEND_URLS', ''))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => true,

];