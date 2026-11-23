<?php

namespace Tests\Feature;

use App\Services\ImageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\Concerns\BuildsProductPrices;
use Tests\TestCase;

class ImageProcessingTest extends TestCase
{
    use BuildsProductPrices;
    use RefreshDatabase;

    private function process(UploadedFile $file, string $profile, string $disk = 'public'): string|false
    {
        return app(ImageProcessor::class)->store($file, 'test-dir', $disk, $profile);
    }

    /** @return array{0:int,1:int,2:int} [عرض, ارتفاع, نوع الصورة IMAGETYPE_*] */
    private function inspect(string $disk, string $path): array
    {
        $info = getimagesizefromstring(Storage::disk($disk)->get($path));

        return [$info[0], $info[1], $info[2]];
    }

    public function test_output_is_webp_with_a_random_name_in_the_requested_directory(): void
    {
        Storage::fake('public');

        $path = $this->process(UploadedFile::fake()->image('photo.jpg', 800, 600), 'photo');

        $this->assertStringStartsWith('test-dir/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame(IMAGETYPE_WEBP, $this->inspect('public', $path)[2]);
    }

    public function test_large_image_is_shrunk_to_the_profile_limit_keeping_the_ratio(): void
    {
        Storage::fake('public');

        $path = $this->process(UploadedFile::fake()->image('big.jpg', 3200, 1600), 'photo');

        [$width, $height] = $this->inspect('public', $path);
        $this->assertSame(1600, $width);
        $this->assertSame(800, $height);
    }

    public function test_tall_image_is_limited_by_its_height(): void
    {
        Storage::fake('public');

        $path = $this->process(UploadedFile::fake()->image('tall.jpg', 1000, 4000), 'avatar');

        [$width, $height] = $this->inspect('public', $path);
        $this->assertSame(128, $width);
        $this->assertSame(512, $height);
    }

    public function test_each_profile_uses_its_own_limit(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $avatar = $this->process(UploadedFile::fake()->image('a.jpg', 2000, 2000), 'avatar');
        $logo = $this->process(UploadedFile::fake()->image('l.jpg', 2000, 2000), 'logo');
        $receipt = $this->process(UploadedFile::fake()->image('r.jpg', 3000, 3000), 'receipt', 'local');

        $this->assertSame(512, $this->inspect('public', $avatar)[0]);
        $this->assertSame(512, $this->inspect('public', $logo)[0]);
        $this->assertSame(2000, $this->inspect('local', $receipt)[0]);
    }

    public function test_small_images_are_never_enlarged(): void
    {
        Storage::fake('public');

        $path = $this->process(UploadedFile::fake()->image('small.jpg', 100, 80), 'photo');

        $this->assertSame([100, 80], array_slice($this->inspect('public', $path), 0, 2));
    }

    public function test_png_transparency_is_kept(): void
    {
        Storage::fake('public');

        $png = imagecreatetruecolor(40, 40);
        imagealphablending($png, false);
        imagesavealpha($png, true);
        imagefill($png, 0, 0, imagecolorallocatealpha($png, 255, 0, 0, 127)); // أحمر شفاف بالكامل
        ob_start();
        imagepng($png);
        $bytes = ob_get_clean();

        $path = $this->process(UploadedFile::fake()->createWithContent('logo.png', $bytes), 'logo');

        $out = imagecreatefromstring(Storage::disk('public')->get($path));
        $alpha = (imagecolorat($out, 5, 5) >> 24) & 0x7F;
        $this->assertGreaterThan(100, $alpha, 'الخلفية الشفافة لازم تضل شفافة');
    }

    public function test_output_is_much_smaller_than_the_original(): void
    {
        Storage::fake('public');

        // صورة بتفاصيل (ضجيج عشوائي) عشان الضغط يكون حقيقي مش صورة سادة
        $source = imagecreatetruecolor(2400, 1800);
        mt_srand(7);
        for ($i = 0; $i < 4000; $i++) {
            imagefilledrectangle(
                $source,
                $x = mt_rand(0, 2400), $y = mt_rand(0, 1800), $x + mt_rand(5, 60), $y + mt_rand(5, 60),
                imagecolorallocate($source, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255))
            );
        }
        ob_start();
        imagejpeg($source, null, 95);
        $original = ob_get_clean();

        $path = $this->process(UploadedFile::fake()->createWithContent('detail.jpg', $original), 'photo');

        $this->assertLessThan(strlen($original) / 2, Storage::disk('public')->size($path));
    }

    public function test_a_corrupt_file_returns_false_and_leaves_nothing_behind(): void
    {
        Storage::fake('public');

        $result = $this->process(UploadedFile::fake()->createWithContent('bad.jpg', 'not an image at all'), 'photo');

        $this->assertFalse($result);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_image_too_big_for_the_memory_limit_is_refused_instead_of_crashing_php(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('huge.jpg', 3000, 3000); // يحتاج حوالي 45MB لفكه
        $original = ini_get('memory_limit');

        try {
            ini_set('memory_limit', (string) (memory_get_usage(true) + 20 * 1024 * 1024));

            $this->assertFalse($this->process($file, 'photo'));
        } finally {
            ini_set('memory_limit', $original);
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_unknown_profile_is_a_programming_error(): void
    {
        Storage::fake('public');

        $this->expectException(InvalidArgumentException::class);

        $this->process(UploadedFile::fake()->image('a.jpg'), 'nope');
    }

    public function test_avatar_endpoint_stores_a_small_webp_file(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();

        $this->actingAs($user)->post('/api/auth/me/avatar', [
            'avatar' => UploadedFile::fake()->image('big.jpg', 2400, 2400),
        ], ['Accept' => 'application/json'])->assertOk();

        $path = $user->refresh()->user_avatar;
        $this->assertStringEndsWith('.webp', $path);
        [$width, $height, $type] = $this->inspect('public', $path);
        $this->assertSame(IMAGETYPE_WEBP, $type);
        $this->assertSame([512, 512], [$width, $height]);
    }
}