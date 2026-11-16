<?php

return [

    /*
    |--------------------------------------------------------------------------
    | حدود تصنيفات لوحة صاحب المحل حسب الباقة
    |--------------------------------------------------------------------------
    |
    | free      : الحد الأقصى لكل نوع (menu / product / service) للباقة المجانية.
    | paid      : null يعني بلا حد تجاري.
    | hard_cap  : سقف تقني لكل محل ونوع حتى بالباقة المدفوعة، يحمي القوائم غير المرقّمة.
    |
    | القيم مأخوذة من js/store-type-config.js بالفرونت (menuCategories = 5 و productCategories = 8).
    | حد service غير مؤكد بالفرونت وهو مفترض هنا = 8. وتخفيض حد منتجات بعض أنواع
    | المتاجر (بقالة وخضار ولحوم وأسماك = 5) غير مطبّق بهذه الخطوة.
    |
    */

    'category_limits' => [
        'free' => [
            'menu' => 5,
            'product' => 8,
            'service' => 8,
        ],
        'paid' => null,
    ],

    'category_hard_cap' => 200,

    /*
    |--------------------------------------------------------------------------
    | حدود أصناف المنيو حسب الباقة
    |--------------------------------------------------------------------------
    |
    | free.menu     : 10 أصناف (store-type-config.js: menuItems = 10).
    | paid          : null يعني بلا حد تجاري.
    | menu_item_hard_cap : سقف تقني لكل محل حتى بالباقة المدفوعة، لأن القائمة بلا ترقيم.
    |
    */

    'item_limits' => [
        'free' => [
            'menu' => 10,
        ],
        'paid' => null,
    ],

    'menu_item_hard_cap' => 500,

    /*
    |--------------------------------------------------------------------------
    | منتجات المتاجر (dashboard_store_products)
    |--------------------------------------------------------------------------
    |
    | نسخة من منطق js/store-type-config.js (STORE_BASE_FREE_LIMITS و STORE_VARIANTS).
    | المفاتيح هي قيم App\Enums\StoreCategoryVariant. أي تعديل هناك يلزم هنا أيضاً.
    | المحل بلا تصنيف متجر يُعامل كنوع general.
    |
    | free_default       : حد المنتجات المجاني الأساسي.
    | free_by_variant    : أنواع لها حد مجاني مختلف.
    | discounted_free    : حد المنتجات المخفّضة المجاني (للأنواع التي تدعم الخصم).
    | discount_variants  : الأنواع التي تدعم الخصم.
    | no_product_variants: أنواع خدمية بلا منتجات.
    | paid               : لا حد تجاري. hard_cap سقف تقني فقط.
    |
    */

    'store_products' => [
        'free_default' => 10,
        'free_by_variant' => [
            'grocery' => 15, 'produce' => 15, 'butcher' => 15, 'fish' => 15, 'bakery' => 15,
            'sweets' => 15, 'spices' => 15, 'pharmacy' => 15, 'cleaning' => 15,
        ],
        'discounted_free' => 5,
        'discount_variants' => [
            'menswear', 'womenswear', 'kidswear', 'shoes', 'accessories', 'furniture', 'furnishings',
            'household', 'appliances', 'plumbing', 'mobile', 'computers', 'electronics', 'solar',
            'paintsdecor', 'wood', 'tiles', 'stationery', 'toys', 'artsupplies', 'autoparts',
            'tires', 'agritools',
        ],
        'no_product_variants' => [
            'tailoring', 'clinic', 'repair', 'salonmen', 'salonwomen', 'laundry', 'photography', 'garage',
        ],
        'hard_cap' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | خدمات المحلات الخدمية (dashboard_store_services)
    |--------------------------------------------------------------------------
    |
    | أعمال بأسعار ومدة تنفيذ (تفصيل، قص شعر، غسيل...). نسخة من الأنواع التي لها
    | hasServiceItems في js/store-type-config.js. المفاتيح هي قيم
    | App\Enums\StoreCategoryVariant. أي نوع جديد بالفرونت يلزم هنا أيضاً.
    |
    | variants   : الأنواع التي تدعم الخدمات.
    | free_limit : حد الخدمات للباقة المجانية (مفترض = 10 مثل المنيو، غير مؤكد بالفرونت).
    | paid       : لا حد تجاري. hard_cap سقف تقني فقط.
    |
    */

    'service_items' => [
        'variants' => [
            'tailoring', 'solar', 'repair', 'ironalu', 'salonmen',
            'salonwomen', 'laundry', 'photography', 'garage', 'vet',
        ],
        'free_limit' => 10,
        'hard_cap' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | طاولات الكافيه (dashboard_tables)
    |--------------------------------------------------------------------------
    |
    | place_types : أنواع النشاط التي تدعم الطاولات والحجز (BOOKING_TYPES في js/menu.js).
    |               أي نوع آخر يرجع 404 من مسارات الطاولات.
    | free_limit  : حد الطاولات للباقة المجانية (عقد الطاولات والحجوزات، القاعدة 13).
    | paid        : لا حد تجاري. hard_cap سقف تقني فقط.
    | max_seats   : أقصى عدد كراسي لطاولة (مفترض 50، العقد لم يحدد سقفاً).
    |
    */

    'tables' => [
        'place_types' => ['cafe', 'restaurant-cafe'],
        'free_limit' => 5,
        'hard_cap' => 200,
        'max_seats' => 50,
    ],

        /*
    |--------------------------------------------------------------------------
    | حجوزات الكافيه (dashboard_reservations)
    |--------------------------------------------------------------------------
    |
    | free_limit : حد الحجوزات للباقة المجانية (إجمالي ما استقبله النشاط، القاعدة 13).
    | paid       : لا حد تجاري.
    | page_size / max_page_size : حجم صفحة القائمة (cursor) والأقصى المسموح.
    |
    */

    'reservations' => [
        'free_limit' => 5,
        'page_size' => 20,
        'max_page_size' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | مشتركو مساحات العمل (dashboard_subscribers)
    |--------------------------------------------------------------------------
    |
    | place_types : أنواع النشاط التي تدعم المشتركين (صفحة subscribers بنوع workspace فقط
    |               في js/store-type-config.js). أي نوع آخر يرجع 404.
    | timezone    : منطقة "اليوم" عند التجديد (GAZA_TZ بالفرونت). التخزين يبقى UTC.
    | page_size / max_page_size : حجم صفحة القائمة (cursor) والأقصى المسموح.
    |
    | لا حد تجاري بالباقة المجانية: الفرونت لا يقفل صفحة المشتركين (المقفل طلبات الاشتراك فقط).
    |
    */

    'subscribers' => [
        'place_types' => ['workspace'],
        'timezone' => 'Asia/Gaza',
        'page_size' => 50,
        'max_page_size' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | إعلانات المحل (dashboard_ads)
    |--------------------------------------------------------------------------
    |
    | نسخة من adTypes و adOptions و limits.free.ads في js/store-type-config.js.
    | أي تعديل هناك يلزم هنا أيضاً.
    |
    | free_limit        : حد الإعلانات للباقة المجانية (الفرونت: ads = 3 للمطاعم والكافيهات والمتاجر).
    |                     مساحة العمل بلا حد معرّف بالفرونت؛ مفترض = 3 مثل البقية (غير مؤكد).
    | paid              : لا حد تجاري. hard_cap سقف تقني فقط.
    | types_by_place    : أنواع الإعلان المسموحة لكل place_type.
    | store_general_types : متجر بلا تصنيف (general) لا adTypes له بالفرونت، فيعرض كل الأنواع.
    |
    | أسعار العرض (adOptions.offerPrices) تخص إعلان offer بمتجر له تصنيف غير general فقط،
    | والرابط (adOptions.linkOnlyForJob) لإعلان job فقط بالمطعم والمتجر الخاص. الشرطان بـ DashboardAd.
    |
    */

    'ads' => [
        'free_limit' => 3,
        'hard_cap' => 100,
        'types_by_place' => [
            'workspace' => ['activity', 'workshop', 'job', 'offer'],
            'restaurant' => ['offer', 'job'],
            'cafe' => ['activity', 'offer'],
            'restaurant-cafe' => ['activity', 'offer'],
            'store' => ['offer', 'job'],
        ],
        'store_general_types' => ['activity', 'workshop', 'job', 'offer'],
    ],

    /*
    |--------------------------------------------------------------------------
    | مواعيد العيادات (dashboard_appointments و dashboard_clinic_hours)
    |--------------------------------------------------------------------------
    |
    | variants       : تصنيفات المتجر التي تدعم المواعيد (hasAppointments بالفرونت = clinic فقط).
    | free_limit     : حد المواعيد للباقة المجانية (إجمالي ما استقبلته العيادة، freeLimits.appointments = 5).
    | paid           : لا حد تجاري.
    | timezone       : منطقة تحديد اليوم وساعات الدوام. التخزين يبقى UTC.
    | window_days    : أبعد موعد مسموح من الآن.
    | slot_options   : مدد الموعد المسموحة (دقائق) من clinic-appointments.js.
    | default_hours  : دوام العيادة قبل أن يحدده المالك (defaultHours بالفرونت).
    | page_size / max_page_size : حجم صفحة القائمة (cursor) والأقصى المسموح.
    |
    */

    'appointments' => [
        'variants' => ['clinic'],
        'free_limit' => 5,
        'timezone' => 'Asia/Gaza',
        'window_days' => 30,
        'slot_options' => [15, 20, 30, 45, 60],
        'default_slot' => 30,
        'default_hours' => [
            'from' => '09:00',
            'to' => '17:00',
            'closed_days' => ['fri'],
        ],
        'page_size' => 20,
        'max_page_size' => 50,
    ],

];