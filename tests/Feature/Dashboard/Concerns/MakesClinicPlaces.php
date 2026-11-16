<?php

namespace Tests\Feature\Dashboard\Concerns;

use App\Models\Area;
use App\Models\Place;
use App\Models\Regions;
use App\Models\StoreCategory;
use App\Models\StoreCategoryGroup;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** أدوات مشتركة لاختبارات المواعيد ودوام العيادة. */
trait MakesClinicPlaces
{
    private static int $placeCounter = 0;

    private Area $area;

    private StoreCategoryGroup $group;

    protected function setUpPlaces(): void
    {
        $region = new Regions;
        $region->region_slug = 'r-'.Str::random(6);
        $region->region_name_en = 'Gaza';
        $region->region_name_ar = 'غزة';
        $region->save();

        $this->area = new Area;
        $this->area->area_slug = 'a-'.Str::random(6);
        $this->area->area_name_en = 'Al-Rimal';
        $this->area->area_name_ar = 'الرمال';
        $this->area->region_id = $region->id;
        $this->area->save();

        $this->group = new StoreCategoryGroup;
        $this->group->forceFill([
            'store_category_group_slug' => 'g-'.Str::random(6),
            'store_category_group_name_en' => 'Group',
            'store_category_group_name_ar' => 'مجموعة',
        ])->save();
    }

    /** $variant = null يعني محلاً بلا تصنيف متجر. */
    protected function makeOwner(?string $variant = 'clinic', string $plan = 'free', string $type = 'store', string $status = Place::STATUS_APPROVED): User
    {
        $n = ++self::$placeCounter;
        $user = UserFactory::new()->storeOwner()->create();

        $categoryId = null;

        if ($variant !== null) {
            $category = new StoreCategory;
            $category->forceFill([
                'store_category_slug' => 'c-'.$n.Str::lower(Str::random(5)),
                'store_category_variant_key' => $variant,
                'store_category_name_en' => 'Cat '.$n,
                'store_category_name_ar' => 'تصنيف '.$n,
                'store_category_is_active' => true,
                'store_category_group_id' => $this->group->id,
            ])->save();
            $categoryId = $category->id;
        }

        $place = new Place;
        $place->fill([
            'place_name' => 'Place '.$n,
            'place_type' => $type,
            'place_address' => 'Street '.$n,
            'place_phone' => '056'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'place_whatsapp' => '970593'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'area_id' => $this->area->id,
            'store_category_id' => $categoryId,
        ]);
        $place->place_slug = 'p-'.Str::lower(Str::random(12));
        $place->place_status = $status;
        $place->place_plan = $plan;
        $place->user_id = $user->id;
        $place->save();

        return $user;
    }

    protected function placeOf(User $user): Place
    {
        return Place::where('user_id', $user->id)->firstOrFail();
    }

    /** تاريخ ثابت (الاثنين 2026-11-16 10:00 بتوقيت غزة) ليكون الاختبار قابلاً للتكرار. */
    protected function freezeNow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 08:00:00', 'UTC'));
    }

    /**
     * وقت ISO (UTC) لـ $time بتوقيت غزة بعد $daysAhead يوماً.
     * 2026-11-16 اثنين: +2 = الأربعاء (مفتوح)، +4 = الجمعة (مغلق افتراضياً).
     */
    protected function at(string $time = '10:00', int $daysAhead = 2): string
    {
        return Carbon::now('Asia/Gaza')->startOfDay()->addDays($daysAhead)
            ->setTimeFromTimeString($time)
            ->utc()
            ->toIso8601ZuluString('millisecond');
    }

    /** @return array<string, mixed> */
    protected function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'أحمد علي',
            'countryCode' => '970',
            'whatsapp' => '599123456',
            'datetime' => $this->at(),
            'reason' => 'فحص عام',
            'note' => '',
        ], $override);
    }
}