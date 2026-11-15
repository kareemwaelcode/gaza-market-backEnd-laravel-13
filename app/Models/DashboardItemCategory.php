<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** تصنيف داخل لوحة صاحب المحل: منيو أو منتجات أو خدمات. */
class DashboardItemCategory extends Model
{
    public const KIND_MENU = 'menu';

    public const KIND_PRODUCT = 'product';

    public const KIND_SERVICE = 'service';

    public const KINDS = [self::KIND_MENU, self::KIND_PRODUCT, self::KIND_SERVICE];

    protected $table = 'dashboard_item_categories';

    /** place_id لا يدخل هنا عمداً: يُحدد من الجلسة فقط. */
    protected $fillable = ['kind', 'name'];

    protected function casts(): array
    {
        return ['place_id' => 'integer'];
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'place_id');
    }
}