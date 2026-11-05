<?php

namespace Tests\Feature;

use App\Http\Requests\StoreListingRequest;
use App\Models\Area;
use App\Models\ListingCategory;
use App\Models\Regions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ملاحظة: UploadedFile::fake()->image() يحتاج امتداد GD في PHP.
 */
class StoreListingRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validData(array $override = []): array
    {
        $region = new Regions();
        $region->region_slug = 'r-'.Str::random(6);
        $region->region_name_en = 'Gaza';
        $region->region_name_ar = 'غزة';
        $region->save();

        $area = new Area();
        $area->area_slug = 'a-'.Str::random(6);
        $area->area_name_en = 'Rimal';
        $area->area_name_ar = 'الرمال';
        $area->region_id = $region->id;
        $area->save();

        return array_merge([
            'listing_title' => 'iPhone 15',
            'listing_condition' => 'new',
            'listing_description' => 'Brand new, sealed.',
            'listing_price' => '2500.50',
            'listing_phone' => '0591234567',
            'listing_category_id' => $this->category()->id,
            'area_id' => $area->id,
            'images' => [UploadedFile::fake()->image('a.jpg')],
        ], $override);
    }

    private function category(bool $active = true): ListingCategory
    {
        $category = new ListingCategory();
        $category->listing_category_slug = 'c-'.Str::random(6);
        $category->listing_category_name_en = 'Phones';
        $category->listing_category_name_ar = 'جوالات';
        $category->listing_category_is_active = $active;
        $category->save();

        return $category;
    }

    private function fails(array $data): bool
    {
        return Validator::make($data, (new StoreListingRequest())->rules())->fails();
    }

    public function test_valid_data_passes_without_optional_fields(): void
    {
        $this->assertFalse($this->fails($this->validData()));
    }

    public function test_optional_whatsapp_and_negotiable_are_accepted(): void
    {
        $this->assertFalse($this->fails($this->validData([
            'listing_whatsapp' => '0561234567',
            'listing_is_negotiable' => '1',
        ])));
    }

    public function test_every_mandatory_field_is_required(): void
    {
        foreach (['listing_title', 'listing_condition', 'listing_description', 'listing_price', 'listing_phone', 'listing_category_id', 'area_id', 'images'] as $field) {
            $data = $this->validData();
            unset($data[$field]);

            $this->assertTrue($this->fails($data), "{$field} should be required");
        }
    }

    public function test_price_must_be_greater_than_zero_with_at_most_two_decimals(): void
    {
        foreach (['0', '0.00', '-5', '0.99', '10.999', 'abc'] as $price) {
            $this->assertTrue($this->fails($this->validData(['listing_price' => $price])), "price {$price}");
        }

        foreach (['1', '1.00', '150', '99999999.99'] as $price) {
            $this->assertFalse($this->fails($this->validData(['listing_price' => $price])), "price {$price}");
        }
    }

    public function test_phone_and_whatsapp_must_match_the_format(): void
    {
        foreach (['591234567', '05912345678', '0521234567', '059123456a', '+970591234567', ' 0591234567x'] as $phone) {
            $this->assertTrue($this->fails($this->validData(['listing_phone' => $phone])), "phone {$phone}");
            $this->assertTrue($this->fails($this->validData(['listing_whatsapp' => $phone])), "whatsapp {$phone}");
        }
    }

    public function test_description_is_limited_to_1000_characters(): void
    {
        $this->assertFalse($this->fails($this->validData(['listing_description' => str_repeat('a', 1000)])));
        $this->assertTrue($this->fails($this->validData(['listing_description' => str_repeat('a', 1001)])));
    }

    public function test_condition_accepts_only_new_used_urgent(): void
    {
        foreach (['new', 'used', 'urgent'] as $condition) {
            $this->assertFalse($this->fails($this->validData(['listing_condition' => $condition])));
        }

        $this->assertTrue($this->fails($this->validData(['listing_condition' => 'broken'])));
    }

    public function test_category_must_exist_and_be_active_and_area_must_exist(): void
    {
        $this->assertTrue($this->fails($this->validData(['listing_category_id' => $this->category(false)->id])));
        $this->assertTrue($this->fails($this->validData(['listing_category_id' => 999999])));
        $this->assertTrue($this->fails($this->validData(['area_id' => 999999])));
    }

    public function test_images_must_be_between_one_and_three(): void
    {
        $this->assertTrue($this->fails($this->validData(['images' => []])));

        foreach ([1, 2, 3] as $count) {
            $images = array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, $count));
            $this->assertFalse($this->fails($this->validData(['images' => $images])), "{$count} images");
        }

        $four = array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 4));
        $this->assertTrue($this->fails($this->validData(['images' => $four])));
    }

    public function test_images_must_be_real_allowed_images_under_2mb(): void
    {
        $notImage = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');
        $tooBig = UploadedFile::fake()->image('big.jpg')->size(2049);

        $this->assertTrue($this->fails($this->validData(['images' => [$notImage]])));
        $this->assertTrue($this->fails($this->validData(['images' => [$tooBig]])));
    }
}