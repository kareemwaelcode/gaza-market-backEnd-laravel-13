<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** طلب اشتراك يرسله زبون مسجّل لمساحة عمل، ويقبله صاحب المحل أو يرفضه من اللوحة. */
class DashboardSubscriptionRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_REJECTED];

    protected $table = 'dashboard_subscription_requests';

    /** place_id و user_id و status لا تدخل هنا عمداً: الأوليان من المسار والجلسة، والحالة عبر القبول والرفض فقط. */
    protected $fillable = ['name', 'country_code', 'whatsapp', 'duration', 'start_date', 'note'];

    protected function casts(): array
    {
        return [
            'place_id' => 'integer',
            'user_id' => 'integer',
            'start_date' => 'date:Y-m-d',
        ];
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'place_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}