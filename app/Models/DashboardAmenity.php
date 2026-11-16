<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardAmenity extends Model
{
    public const KEY_PATTERN = '/^[a-z][a-z_-]{0,31}$/';

    public const MAX_PER_PLACE = 30;

    public const DETAILS_MAX_LENGTH = 120;

    public const LOCKED_ON_FREE = ['card_payment', 'payment_methods-tow', 'whatsapp_order', 'installments'];

    protected $table = 'dashboard_amenities';

    protected $fillable = ['service_key', 'details'];

    protected function casts(): array
    {
        return [
            'place_id' => 'integer',
        ];
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'place_id');
    }
}