<?php

namespace App\Services;

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Throwable;

/**
 * بياخد الصورة اللي رفعها اليوزر، بيصغّرها وبيحوّلها WebP، وبيخزّنها.
 *
 * بديل مباشر لـ $file->store(): بيرجّع المسار المخزّن، أو false عند الفشل،
 * فالكونترولرات بتضل تتعامل مع الفشل بنفس الطريقة القديمة.
 */
class ImageProcessor
{
    /**
     * @param  string  $profile  اسم نوع الصورة من config/images.php (avatar, logo, photo, receipt)
     */
    public function store(UploadedFile $file, string $directory, string $disk, string $profile): string|false
    {
        $settings = config("images.profiles.{$profile}");

        // اسم نوع غلط = غلطة برمجية، لازم تبين فورًا مش تنبلع
        if (! is_array($settings)) {
            throw new InvalidArgumentException("Unknown image profile [{$profile}]");
        }

        $source = $file->getRealPath();
        $temp = tempnam(sys_get_temp_dir(), 'img_');

        if ($source === false || $temp === false) {
            return false;
        }

        try {
            $this->assertFitsInMemory($source);

            // fit(Max): بيصغّر لحد أقصى مع الحفاظ على النسبة وبدون تكبير.
            // المكتبة بتصحّح دوران صور الموبايل (EXIF) لحالها وقت التحميل، فما منعملها orientation() بإيدينا.
            // حفظ WebP بيرمي الـ metadata الأصلي (الموقع، نوع الكاميرا)، وهاد مفيد للخصوصية.
            Image::useImageDriver(config('images.driver'))
                ->loadFile($source)
                ->fit(Fit::Max, $settings['max'], $settings['max'])
                ->quality($settings['quality'])
                ->format('webp')
                ->save($temp);

            // اسم عشوائي مثل اللي بيعمله store()، والامتداد دايمًا webp
            return Storage::disk($disk)->putFileAs($directory, new File($temp), Str::random(40).'.webp');
        } catch (Throwable $e) {
            report($e);

            return false;
        } finally {
            // الملف المؤقت ما لازم يضل على السيرفر بكل الأحوال
            @unlink($temp);
        }
    }

    /**
     * فك ضغط صورة كبيرة بيحتاج ذاكرة كتير، ولو خلصت الذاكرة PHP بيموت (fatal) وما بنقدر نلقطه.
     * فبنقدّر المطلوب قبل ما نبدأ، ونرفض الصورة بدل ما نوقع العامل (worker).
     * التقدير تقريبي (حوالي 5 بايت لكل بكسل)، مش قياس دقيق.
     */
    private function assertFitsInMemory(string $path): void
    {
        $size = @getimagesize($path);

        if ($size === false) {
            throw new RuntimeException('Unreadable image');
        }

        $limit = ini_parse_quantity((string) ini_get('memory_limit'));

        if ($limit <= 0) { // -1 = بدون حد
            return;
        }

        $needed = $size[0] * $size[1] * 5;

        if ($needed > $limit - memory_get_usage(true)) {
            throw new RuntimeException("Image {$size[0]}x{$size[1]} is too large for the PHP memory limit");
        }
    }
}