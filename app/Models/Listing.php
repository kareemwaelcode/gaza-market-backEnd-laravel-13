<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Listing extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SOLD = 'sold';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_SOLD,
    ];

    protected $table = 'listings';

    protected $fillable = [
        'listing_title',
        'listing_condition',
        'listing_description',
        'listing_price',
        'listing_is_negotiable',
        'listing_phone',
        'listing_whatsapp',
        'listing_category_id',
        'area_id',
    ];

    protected function casts(): array
    {
        return [
            'listing_price' => 'decimal:2',
            'listing_is_negotiable' => 'boolean',
            'listing_published_at' => 'datetime',
            'listing_category_id' => 'integer',
            'user_id' => 'integer',
            'area_id' => 'integer',
        ];
    }

    // رابط محادثة واتساب (wa.me يطلب الرقم الدولي بدون +)، أو null لو ما في واتساب
    protected function whatsappLink(): Attribute
    {
        return Attribute::get(fn () => $this->listing_whatsapp
            ? 'https://wa.me/'.ltrim($this->listing_whatsapp, '+')
            : null);
    }

    /* ---------------- Relations ---------------- */

    // withTrashed: المستخدم يستخدم SoftDeletes وإعلاناته يجب أن تبقى مقروءة للأدمن
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ListingCategory::class, 'listing_category_id');
    }

    // صور الإعلان مرتبة، والأولى هي الغلاف
    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class, 'listing_id')
            ->orderBy('listing_image_sort_order');
    }

    /* ---------------- Scopes ---------------- */

    // صفحة السوق: المعتمد فقط، الأحدث نشراً أولاً
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('listing_status', self::STATUS_APPROVED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('listing_status', self::STATUS_PENDING);
    }

    public function scopeLatestPublished(Builder $query): Builder
    {
        return $query->orderByDesc('listing_published_at')->orderByDesc('id');
    }
}