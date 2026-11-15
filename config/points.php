<?php

return [

    // حدود "اليوم" للحد اليومي للنقاط: بتوقيت غزة، بدون تغيير config('app.timezone') (UTC).
    'timezone' => env('POINTS_TIMEZONE', 'Asia/Gaza'),

];