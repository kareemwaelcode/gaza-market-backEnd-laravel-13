<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}