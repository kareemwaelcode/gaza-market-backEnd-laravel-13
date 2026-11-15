<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'user_phone',
    'user_full_name',
    'user_password',
    'user_role',
    'user_status',
    'user_preferred_lang',
    'user_theme',
    'area_id',
])]
#[Hidden(['user_password'])]
class User extends Authenticatable
{
    use Notifiable, SoftDeletes;

    const CREATED_AT = 'user_created_at';
    const UPDATED_AT = 'updated_at';
    const DELETED_AT = 'user_deleted_at';

    protected function casts(): array
    {
        return [
            'user_password' => 'hashed',
            'user_phone_verified_at' => 'datetime',
            'user_last_login_at' => 'datetime',
            'user_created_at' => 'datetime',
            'user_deleted_at' => 'datetime',
            'user_points_total' => 'integer',
            'area_id' => 'integer',
        ];
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function () {
            $avatar = $this->user_avatar;

            if (! $avatar) {
                return null;
            }

            if (str_starts_with($avatar, 'https://')) {
                return $avatar;
            }

            return Storage::disk('public')->url($avatar);
        });
    }

    public function getAuthPasswordName(): string
    {
        return 'user_password';
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }
    
        public function savedListings(): HasMany
    {
        return $this->hasMany(SavedListing::class, 'user_id');
    }
    
        public function place(): HasOne
    {
        return $this->hasOne(Place::class, 'user_id');
    }

    /** @return array<string, mixed> شكل المستخدم الموحّد بردود الـ API (auth/me وتعديل البروفايل). */
    public function apiPayload(): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->user_full_name,
            'phone' => $this->user_phone,
            'role' => $this->user_role,
            'preferred_lang' => $this->user_preferred_lang,
            'theme' => $this->user_theme,
            'area_id' => $this->area_id,
            'avatar_url' => $this->avatar_url,
            'points_total' => $this->user_points_total,
        ];
    }

    public function canUseOwnerDashboard(): bool
    {
        return $this->user_role === 'store_owner'
            && $this->user_status === 'active'
            && $this->place()->approved()->exists();
    }
}