<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Listing;
use App\Models\ListingCategory;
use App\Models\Regions;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ListingSeeder extends Seeder
{
    /**
     * تشغيل: php artisan db:seed --class=ListingSeeder
     * الصور بتحطها يدوياً بـ storage/app/public/listings/demo/ (1-1.jpeg, 1-2.jpeg, 1-3.jpeg)
     * ولازم يكون عامل php artisan storage:link
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('ListingSeeder بيشتغل بالبيئة local فقط.');

            return;
        }

        DB::transaction(function () {
            $region = Regions::firstOrCreate(
                ['region_slug' => 'demo-gaza'],
                ['region_name_en' => 'Gaza', 'region_name_ar' => 'غزة']
            );

            $area = Area::firstOrCreate(
                ['area_slug' => 'demo-rimal'],
                ['area_name_en' => 'Rimal', 'area_name_ar' => 'الرمال', 'region_id' => $region->id]
            );

            $user = User::firstOrCreate(
                ['user_phone' => '0590000000'],
                ['user_full_name' => 'Demo Seller', 'area_id' => $area->id]
            );

            $phones = $this->category('demo-phones', 'Phones', 'جوالات');
            $electronics = $this->category('demo-electronics', 'Electronics', 'إلكترونيات');
            $furniture = $this->category('demo-furniture', 'Furniture', 'أثاث');

            $listings = [
                [
                    'title' => 'Samsung Galaxy S21 - Demo',
                    'condition' => 'used',
                    'description' => 'جوال بحالة ممتازة مع الشاحن والعلبة. إعلان تجريبي بثلاث صور لاختبار السلايدر.',
                    'price' => 1200,
                    'negotiable' => true,
                    'category' => $phones,
                    'status' => Listing::STATUS_APPROVED,
                    'images' => ['listings/demo/1-1.jpeg', 'listings/demo/1-2.jpeg', 'listings/demo/1-3.jpeg'],
                ],
                [
                    'title' => 'Laptop Lenovo - Demo',
                    'condition' => 'used',
                    'description' => 'لابتوب للدراسة والعمل. إعلان تجريبي معتمد بدون صور لاختبار حالة No photos.',
                    'price' => 1800,
                    'negotiable' => false,
                    'category' => $electronics,
                    'status' => Listing::STATUS_APPROVED,
                    'images' => [],
                ],
                [
                    'title' => 'Dining Table - Demo',
                    'condition' => 'new',
                    'description' => 'طاولة سفرة جديدة. إعلان تجريبي بانتظار المراجعة.',
                    'price' => 650,
                    'negotiable' => true,
                    'category' => $furniture,
                    'status' => Listing::STATUS_PENDING,
                    'images' => [],
                ],
                [
                    'title' => 'Used Bicycle - Demo',
                    'condition' => 'urgent',
                    'description' => 'دراجة هوائية للبيع بسرعة. إعلان تجريبي مرفوض.',
                    'price' => 200,
                    'negotiable' => false,
                    'category' => $furniture,
                    'status' => Listing::STATUS_REJECTED,
                    'rejection_reason' => 'الوصف غير واضح، يرجى إضافة تفاصيل أكثر',
                    'images' => [],
                ],
                [
                    'title' => 'Refrigerator - Demo',
                    'condition' => 'used',
                    'description' => 'ثلاجة تعمل بشكل ممتاز. إعلان تجريبي بحالة مباع.',
                    'price' => 900,
                    'negotiable' => false,
                    'category' => $electronics,
                    'status' => Listing::STATUS_SOLD,
                    'images' => [],
                ],
            ];

            foreach ($listings as $data) {
                $exists = Listing::where('listing_title', $data['title'])
                    ->where('user_id', $user->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $listing = new Listing;
                $listing->fill([
                    'listing_title' => $data['title'],
                    'listing_condition' => $data['condition'],
                    'listing_description' => $data['description'],
                    'listing_price' => $data['price'],
                    'listing_is_negotiable' => $data['negotiable'],
                    'listing_phone' => '0590000000',
                    'listing_whatsapp' => '970590000000',
                    'listing_category_id' => $data['category']->id,
                    'area_id' => $area->id,
                ]);
                $listing->user_id = $user->id;
                $listing->listing_status = $data['status'];
                $listing->listing_published_at = in_array($data['status'], [Listing::STATUS_APPROVED, Listing::STATUS_SOLD], true)
                    ? now()
                    : null;
                $listing->listing_rejection_reason = $data['rejection_reason'] ?? null;
                $listing->save();

                foreach ($data['images'] as $index => $path) {
                    $listing->images()->create([
                        'listing_image_path' => $path,
                        'listing_image_sort_order' => $index + 1,
                    ]);
                }
            }
        });
    }

    private function category(string $slug, string $nameEn, string $nameAr): ListingCategory
    {
        return ListingCategory::firstOrCreate(
            ['listing_category_slug' => $slug],
            ['listing_category_name_en' => $nameEn, 'listing_category_name_ar' => $nameAr]
        );
    }
}