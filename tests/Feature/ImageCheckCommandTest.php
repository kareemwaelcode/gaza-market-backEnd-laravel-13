<?php

namespace Tests\Feature;

use App\Console\Commands\CheckImages;
use Tests\TestCase;

/**
 * اختبار منطق images:check بقيم مُحاكاة. هاد بيثبت سلوك الأمر، مش جاهزية السيرفر الحقيقي
 * (شغّل php artisan images:check يدويًا على كل بيئة).
 */
class ImageCheckCommandTest extends TestCase
{
    private function env(array $override = []): array
    {
        return array_merge([
            'driver' => 'gd',
            'driver_loaded' => true,
            'webp' => true,
            'upload_max' => 8 * 1024 * 1024,
            'post_max' => 10 * 1024 * 1024,
            'link' => true,
        ], $override);
    }

    private function levels(array $override = []): array
    {
        return array_column((new CheckImages)->evaluate($this->env($override)), 0);
    }

    public function test_everything_is_ok_when_the_environment_is_ready(): void
    {
        $this->assertSame(['ok', 'ok', 'ok', 'ok'], $this->levels());
    }

    public function test_a_missing_driver_fails(): void
    {
        $this->assertSame('fail', $this->levels(['driver_loaded' => false])[0]);
    }

    public function test_a_driver_without_webp_fails(): void
    {
        $this->assertSame('fail', $this->levels(['webp' => false])[0]);
    }

    public function test_an_upload_limit_below_the_largest_image_fails(): void
    {
        $this->assertSame('fail', $this->levels(['upload_max' => 2 * 1024 * 1024])[1]);
    }

    public function test_a_small_post_limit_fails_but_unlimited_is_fine(): void
    {
        $this->assertSame('fail', $this->levels(['post_max' => 2 * 1024 * 1024])[2]);
        $this->assertSame('ok', $this->levels(['post_max' => 0])[2]);
    }

    public function test_a_missing_storage_link_fails(): void
    {
        $this->assertSame('fail', $this->levels(['link' => false])[3]);
    }

    public function test_the_command_prints_the_driver_and_the_unchecked_server_note(): void
    {
        $this->artisan('images:check')
            ->expectsOutputToContain('Image driver:')
            ->expectsOutputToContain('Not checked from PHP');
    }
}