<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceVote extends Model
{
    public const TYPE_CONFIRM = 'confirm';

    public const TYPE_REPORT = 'report';

    public const TYPES = [
        self::TYPE_CONFIRM,
        self::TYPE_REPORT,
    ];

    // الصوت بينكتب بس من كونترولر التصويت (داخل transaction) وبقيم مضبوطة: ما في mass assignment
    protected $fillable = [];

    public function price(): BelongsTo
    {
        return $this->belongsTo(ProductPrice::class, 'product_price_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}