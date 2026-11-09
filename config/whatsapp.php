<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp sending driver
    |--------------------------------------------------------------------------
    |
    | log   : لا يرسل شيئاً، يسجّل فقط أن رسالة كانت ستُرسل (للتطوير والاختبار).
    | wuapi : الإرسال الفعلي عبر wuapi.dev (رقم مربوط كجهاز، وليس الواجهة الرسمية).
    |
    | لاحقاً يمكن إضافة مشغّل "meta" من دون تغيير منطق القبول والرفض.
    |
    */

    'driver' => env('WHATSAPP_DRIVER', 'log'),

    'wuapi' => [
        'base_url' => env('WUAPI_BASE_URL', 'https://api.wuapi.dev'),
        'api_key' => env('WUAPI_API_KEY'),
        'account_id' => env('WUAPI_ACCOUNT_ID'),
        'timeout' => (int) env('WUAPI_TIMEOUT', 10),
    ],
    
    'daily_limit' => (int) env('WHATSAPP_DAILY_LIMIT', 0),
    'allowed_to' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('WHATSAPP_ALLOWED_TO', ''))
    ))),

];