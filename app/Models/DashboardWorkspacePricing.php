<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardWorkspacePricing extends Model
{
    public const PLACE_TYPES = ['workspace'];

    protected $table = 'dashboard_workspace_pricing';

    protected $fillable = [
        'price_hour',
        'price_half_day',
        'price_full_day',
        'price_week',
        'price_month',
        'open_time',
        'close_time',
        'seats_total',
        'seats_available',
    ];

    protected function casts(): array
    {
        return [
            'place_id' => 'integer',
            'seats_total' => 'integer',
            'seats_available' => 'integer',
        ];
    }

    public static function supportsPlace(Place $place): bool
    {
        return in_array($place->place_type, self::PLACE_TYPES, true);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'place_id');
    }
}