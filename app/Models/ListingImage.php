<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ListingImage extends Model
{
    protected $table = 'listing_images';

    // الجدول بدون created_at / updated_at
    public $timestamps = false;

    // listing_id مش fillable: بيتحدد من العلاقة $listing->images()->create([...])
    protected $fillable = [
        'listing_image_path',
        'listing_image_sort_order',
    ];

    protected function casts(): array
    {
        return [
            'listing_id' => 'integer',
            'listing_image_sort_order' => 'integer',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }

    // false = رابط https خارجي (بيانات تجريبية)، فما إله ملف على القرص
    public function isLocalFile(): bool
    {
        return ! str_starts_with($this->listing_image_path, 'https://');
    }

    // رابط الصورة الجاهز للعرض (نفس فكرة avatar_url بـ User)
    protected function url(): Attribute
    {
        return Attribute::get(fn () => $this->isLocalFile()
            ? Storage::disk('public')->url($this->listing_image_path)
            : $this->listing_image_path);
    }
}