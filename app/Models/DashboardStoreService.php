<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** خدمة بسعر ومدة تنفيذ يعرضها محل خدمي (خياط، صالون، مغسلة...). */
class DashboardStoreService extends Model
{
    protected $table = 'dashboard_store_services';

    /** place_id لا يدخل هنا عمداً: يُحدد من الجلسة فقط. */
    protected $fillable = [
        'name', 'description', 'price', 'price_from', 'duration', 'item_category_id',
    ];

    protected function casts(): array
    {
        return [
            'place_id' => 'integer',
            'item_category_id' => 'integer',
            'price' => 'decimal:2',
            'price_from' => 'boolean',
        ];
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'place_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DashboardItemCategory::class, 'item_category_id');
    }
}