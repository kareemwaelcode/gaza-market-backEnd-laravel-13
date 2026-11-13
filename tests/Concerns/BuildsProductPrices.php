<?php

namespace Tests\Concerns;

use App\Models\Area;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\PriceVote;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Regions;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;

// بيانات مشتركة لاختبارات أسعار المنتجات (API + CMS)
trait BuildsProductPrices
{
    private function makeUser(string $name = 'Test User'): User
    {
        $user = new User;
        $user->user_phone = '059'.random_int(1000000, 9999999);
        $user->user_full_name = $name;
        $user->save();

        return $user;
    }

    private function lookups(?string $areaSlug = null): array
    {
        $region = new Regions;
        $region->region_slug = 'r-'.Str::random(6);
        $region->region_name_en = 'Gaza';
        $region->region_name_ar = 'غزة';
        $region->save();

        $area = new Area;
        $area->area_slug = $areaSlug ?? 'a-'.Str::random(6);
        $area->area_name_en = 'Rimal';
        $area->area_name_ar = 'الرمال';
        $area->region_id = $region->id;
        $area->save();

        $group = new CategoryGroup;
        $group->category_group_slug = 'g-'.Str::random(6);
        $group->category_group_name_en = 'Food';
        $group->category_group_name_ar = 'غذاء';
        $group->save();

        $category = new Category;
        $category->category_slug = 'c-'.Str::random(6);
        $category->category_name_en = 'Grains';
        $category->category_name_ar = 'حبوب';
        $category->category_group_id = $group->id;
        $category->save();

        $unit = new Unit;
        $unit->unit_slug = 'u-'.Str::random(6);
        $unit->unit_name_en = 'Kilo';
        $unit->unit_name_ar = 'كيلو';
        $unit->save();

        return ['area' => $area, 'category' => $category, 'unit' => $unit];
    }

    // منتج الكتالوج اللي بينتبّلغ عن سعره
    private function makeProduct(array $lookups, User $user, string $status = 'active', array $overrides = []): Product
    {
        $product = new Product;
        $product->forceFill(array_merge([
            'product_name_ar' => 'سكر',
            'product_name_en' => 'Sugar',
            'category_id' => $lookups['category']->id,
            'product_quantity' => 1,
            'unit_id' => $lookups['unit']->id,
            'product_price' => 8,
            'product_store_name' => 'متجر الكتالوج',
            'area_id' => $lookups['area']->id,
            'user_id' => $user->id,
            'product_status' => $status,
        ], $overrides));
        $product->save();

        return $product->refresh();
    }

    private function makePrice(Product $product, array $lookups, User $user, string $status = 'approved', array $overrides = []): ProductPrice
    {
        $price = new ProductPrice;
        $price->forceFill(array_merge([
            'product_id' => $product->id,
            'area_id' => $lookups['area']->id,
            'user_id' => $user->id,
            'product_price_value' => 7.5,
            'product_price_store_name' => 'بقالة أبو محمد',
            'product_price_status' => $status,
        ], $overrides));
        $price->save();

        return $price->refresh();
    }

    private function makeVote(ProductPrice $price, User $user, string $type): PriceVote
    {
        $vote = new PriceVote;
        $vote->product_price_id = $price->id;
        $vote->user_id = $user->id;
        $vote->price_vote_type = $type;
        $vote->save();

        return $vote;
    }
}