<?php

namespace Tests\Feature;

use App\Http\Requests\Dashboard\MenuItemImageRequest;
use App\Http\Requests\Dashboard\StoreProductImageRequest;
use App\Http\Requests\StoreListingRequest;
use App\Http\Requests\StorePlaceRequest;
use App\Http\Requests\StoreProductPriceRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdateDashboardPlaceLogoRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ImageDimensionsTest extends TestCase
{
    protected bool $actAsAdmin = false;

    /** @return array<string, array{0: class-string, 1: string}> */
    public static function imageRules(): array
    {
        return [
            'listing images' => [StoreListingRequest::class, 'images.*'],
            'place image' => [StorePlaceRequest::class, 'image'],
            'place logo' => [UpdateDashboardPlaceLogoRequest::class, 'logo'],
            'avatar' => [UpdateAvatarRequest::class, 'avatar'],
            'product receipt' => [StoreProductRequest::class, 'product_receipt_image'],
            'price receipt' => [StoreProductPriceRequest::class, 'receipt_image'],
            'store product image' => [StoreProductImageRequest::class, 'image'],
            'menu item image' => [MenuItemImageRequest::class, 'image'],
        ];
    }

    private function validate(string $requestClass, string $ruleKey, UploadedFile $file): bool
    {
        $rules = (new $requestClass())->rules()[$ruleKey];
        $field = str_replace('.*', '', $ruleKey);

        return Validator::make([$field => $file], [$field => $rules])->passes();
    }

    #[DataProvider('imageRules')]
    public function test_an_image_with_oversized_dimensions_is_rejected(string $requestClass, string $ruleKey): void
    {
        $this->assertFalse($this->validate($requestClass, $ruleKey, UploadedFile::fake()->image('wide.jpg', 6001, 100)));
        $this->assertFalse($this->validate($requestClass, $ruleKey, UploadedFile::fake()->image('tall.jpg', 100, 6001)));
    }

    #[DataProvider('imageRules')]
    public function test_an_image_within_the_limit_is_accepted(string $requestClass, string $ruleKey): void
    {
        $this->assertTrue($this->validate($requestClass, $ruleKey, UploadedFile::fake()->image('ok.jpg', 600, 600)));
    }
}