<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedListing extends Model
{
    // سجل ثابت: له وقت إنشاء فقط
    const CREATED_AT = 'saved_listing_created_at';

    const UPDATED_AT = null;

    protected $table = 'saved_listings';

    protected $fillable = [
        'user_id',
        'listing_id',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'listing_id' => 'integer',
            'saved_listing_created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }
}