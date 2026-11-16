<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** إعلان أو عرض ينشره صاحب المحل من لوحته (يمكن إخفاؤه بدل حذفه). */
class DashboardAd extends Model
{
    /** القيم مفترضة (لم تُحسم بالفرونت) وتُعدَّل هنا فقط. */
    public const TYPES = ['offer', 'discount', 'announcement', 'event'];

    protected $table = 'dashboard_ads';

    /** place_id لا يدخل هنا عمداً: يُحدد من الجلسة فقط. */
    protected $fillable = [
        'type', 'title', 'details', 'date', 'link', 'price_before', 'price_after', 'is_hidden',
    ];

    protected function casts(): array
    {
        return [
            'place_id' => 'integer',
            'date' => 'date:Y-m-d',
            'price_before' => 'decimal:2',
            'price_after' => 'decimal:2',
            'is_hidden' => 'boolean',
        ];
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'place_id');
    }
}