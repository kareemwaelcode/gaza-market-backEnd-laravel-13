<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductPrice extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    // اختفى تلقائياً بسبب البلاغات (report_count > MAX_REPORTS): بيرجع بزر "إظهار" من الأدمن
    public const STATUS_FLAGGED = 'flagged';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_FLAGGED,
    ];

    // السعر بيختفي لما report_count > MAX_REPORTS
    public const MAX_REPORTS = 5;

    // ...إلا لو confirm_count > PROTECTED_CONFIRMS: التأكيدات الكافية بتحميه من الإخفاء بالبلاغات
    public const PROTECTED_CONFIRMS = 3;

    // الحالة والمستخدم والعدّادات والفاتورة والمراجعة ما بتتعبّى بالـ mass assignment
    protected $fillable = [
        'product_id',
        'area_id',
        'product_price_value',
        'product_price_store_name',
        'product_price_store_address',
        'product_price_store_phone',
    ];

    // rejection_reason عمود قديم ما عاد مستخدم (ما في تغيير مخطط)، بس بنخفيه احتياطاً من أي JSON
    protected $hidden = [
        'user_id',
        'product_price_store_phone',
        'product_price_receipt_image',
        'rejection_reason',
    ];

    protected $attributes = [
        'product_price_status' => self::STATUS_PENDING,
        'confirm_count' => 0,
        'report_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'product_price_value' => 'decimal:2',
            'confirm_count' => 'integer',
            'report_count' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PriceVote::class, 'product_price_id');
    }

    // محمي من الإخفاء بالبلاغات: أكثر من PROTECTED_CONFIRMS تأكيد
    public function isProtectedByConfirmations(): bool
    {
        return $this->confirm_count > self::PROTECTED_CONFIRMS;
    }

    // لازم يختفي تلقائياً: بلاغات أكثر من الحد وما هو محمي بالتأكيدات
    public function shouldBeHiddenByReports(): bool
    {
        return $this->report_count > self::MAX_REPORTS && ! $this->isProtectedByConfirmations();
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('product_price_status', self::STATUS_APPROVED);
    }
}