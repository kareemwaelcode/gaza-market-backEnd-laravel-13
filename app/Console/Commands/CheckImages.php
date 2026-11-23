<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckImages extends Command
{
    protected $signature = 'images:check';

    protected $description = 'يفحص جاهزية رفع الصور: درايفر المعالجة ودعم WebP وحدود PHP للرفع ورابط storage';

    // أكبر حد لحجم صورة واحدة بالـ Requests (max:5120 كيلوبايت). غيّره هون لو تغيّرت الحدود.
    private const REQUIRED_UPLOAD_BYTES = 5 * 1024 * 1024;

    public function handle(): int
    {
        $driver = (string) config('images.driver');

        $this->line("Image driver: {$driver}");
        $this->line('Media URL base: '.config('filesystems.disks.public.url'));

        $failed = false;

        foreach ($this->evaluate($this->environment($driver)) as [$level, $message]) {
            if ($level === 'ok') {
                $this->info("OK: {$message}");

                continue;
            }

            $failed = true;
            $this->error("FAILED: {$message}");
        }

        // PHP ما بيقدر يقرأ إعدادات Nginx/Apache، فبنذكّر فيها بدل ما ندّعي فحصها.
        $this->line('Not checked from PHP: make sure the web server body limit (Nginx client_max_body_size) is at least 6M.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * منطق التقييم لحاله (بدون قراءة بيئة PHP) عشان يتجرّب بالاختبارات.
     *
     * @param  array{driver: string, driver_loaded: bool, webp: bool, upload_max: int, post_max: int, link: bool}  $env
     *                                                                                                                  post_max = 0 يعني بدون حد
     * @return list<array{0: 'ok'|'fail', 1: string}>
     */
    public function evaluate(array $env): array
    {
        $results = [];

        if (! $env['driver_loaded']) {
            $results[] = ['fail', "driver [{$env['driver']}] is not available (PHP extension missing or IMAGE_DRIVER is wrong)"];
        } elseif (! $env['webp']) {
            $results[] = ['fail', "driver [{$env['driver']}] has no WebP support, all image uploads would fail"];
        } else {
            $results[] = ['ok', "driver [{$env['driver']}] is loaded with WebP support"];
        }

        $required = self::REQUIRED_UPLOAD_BYTES;

        $results[] = $env['upload_max'] >= $required
            ? ['ok', 'upload_max_filesize is '.$this->format($env['upload_max'])]
            : ['fail', 'upload_max_filesize ('.$this->format($env['upload_max']).') is below the largest allowed image ('.$this->format($required).')'];

        $results[] = ($env['post_max'] === 0 || $env['post_max'] >= $required)
            ? ['ok', 'post_max_size is '.($env['post_max'] === 0 ? 'unlimited' : $this->format($env['post_max']))]
            : ['fail', 'post_max_size ('.$this->format($env['post_max']).') is below the largest allowed image ('.$this->format($required).')'];

        $results[] = $env['link']
            ? ['ok', 'public/storage link exists']
            : ['fail', 'public/storage link is missing, run: php artisan storage:link'];

        return $results;
    }

    /** @return array{driver: string, driver_loaded: bool, webp: bool, upload_max: int, post_max: int, link: bool} */
    private function environment(string $driver): array
    {
        return [
            'driver' => $driver,
            'driver_loaded' => $this->driverLoaded($driver),
            'webp' => $this->supportsWebp($driver),
            'upload_max' => ini_parse_quantity((string) ini_get('upload_max_filesize')),
            'post_max' => ini_parse_quantity((string) ini_get('post_max_size')),
            'link' => file_exists(public_path('storage')),
        ];
    }

    private function driverLoaded(string $driver): bool
    {
        return in_array($driver, ['gd', 'imagick'], true) && extension_loaded($driver);
    }

    private function supportsWebp(string $driver): bool
    {
        if ($driver === 'gd' && function_exists('gd_info')) {
            return (bool) (gd_info()['WebP Support'] ?? false);
        }

        if ($driver === 'imagick' && class_exists(\Imagick::class)) {
            return \Imagick::queryFormats('WEBP') !== [];
        }

        return false;
    }

    private function format(int $bytes): string
    {
        return round($bytes / 1024 / 1024, 1).'M';
    }
}