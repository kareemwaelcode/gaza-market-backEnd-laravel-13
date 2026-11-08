<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Place extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    public const TYPES = ['workspace', 'cafe', 'restaurant', 'restaurant-cafe', 'store'];

    protected $table = 'places';

    protected $fillable = [
        'place_name',
        'place_type',
        'place_address',
        'place_phone',
        'place_whatsapp',
        'store_category_id',
        'area_id',
    ];

    protected function casts(): array
    {
        return [
            'place_is_open' => 'boolean',
            'place_approved_at' => 'datetime',
            'place_activated_at' => 'datetime',
            'store_category_id' => 'integer',
            'area_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->place_logo
            ? Storage::disk('public')->url($this->place_logo)
            : null);
    }

    protected function whatsappLink(): Attribute
    {
        return Attribute::get(fn () => $this->place_whatsapp
            ? 'https://wa.me/'.ltrim($this->place_whatsapp, '+')
            : null);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function storeCategory(): BelongsTo
    {
        return $this->belongsTo(StoreCategory::class, 'store_category_id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('place_status', self::STATUS_APPROVED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('place_status', self::STATUS_PENDING);
    }
}