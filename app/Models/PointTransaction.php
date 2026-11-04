<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointTransaction extends Model
{
// سجل ثابت: له وقت إنشاء فقط
const CREATED_AT = 'point_transaction_created_at';

const UPDATED_AT = null;

protected $table = 'point_transactions';

protected $fillable = [
    'user_id',
    'point_rule_id',
    'point_transaction_points',
    'point_transaction_source_type',
    'point_transaction_source_id',
];

protected function casts(): array
{
    return [
        'user_id' => 'integer',
        'point_rule_id' => 'integer',
        'point_transaction_points' => 'integer',
        'point_transaction_source_id' => 'integer',
        'point_transaction_created_at' => 'datetime',
    ];
}

// withTrashed: يبقى السجل ظاهراً حتى لو حُذف المستخدم (soft delete)
public function user(): BelongsTo
{
    return $this->belongsTo(User::class, 'user_id')->withTrashed();
}

public function pointRule(): BelongsTo
{
    return $this->belongsTo(PointRule::class, 'point_rule_id');
}
}