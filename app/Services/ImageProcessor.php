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
 * store(): بديل مباشر لـ $file->store()، بيرجّع المسار المخزّن أو false عند الفشل.
 *
 * process() + publish() + discard(): نفس الشغل مقسوم لمرحلتين، عشان الكونترولر اللي
 * جوّا DB::transaction ما يعالج الصورة (بطيء: 0.14-0.6 ثانية للصورة) والـ transaction مفتوحة.
 * بيعالج كل الصور قبل الـ transaction، وجوّاها بينسخ الملفات الجاهزة بس (سريع).
 */
class ImageProcessor
{
    /** تقدير تقريبي لذاكرة فك الصورة، مش قياس دقيق (القياس عندنا كان حوالي 4). */
    private const BYTES_PER_PIXEL = 5;

    public function store(UploadedFile $file, string $directory, string $disk, string $profile): string|false
    {
        $processed = $this->process($file, $profile);

        if ($processed === false) {
            return false;
        }

        try {
            return $this->publish($processed, $directory, $disk);
        } finally {
            $this->discard($processed);
        }
    }

    /**
     * المرحلة الثقيلة: تحقق + تصغير + تحويل WebP إلى ملف مؤقت.
     *
     * @param  string  $profile  اسم نوع الصورة من config/images.php (avatar, logo, photo, receipt)
     * @return string|false مسار الملف المؤقت، والمستدعي مسؤول عن discard() له، أو false عند الفشل
     */
    public function process(UploadedFile $file, string $profile): string|false
    {
        $settings = config("images.profiles.{$profile}");

        // اسم نوع غلط = غلطة برمجية، لازم تبين فورًا مش تنبلع
        if (! is_array($settings)) {
            throw new InvalidArgumentException("Unknown image profile [{$profile}]");
        }

        $source = $file->getRealPath();

        if ($source === false) {
            return false;
        }

        $temp = null;

        try {
            $this->assertAcceptable($source);

            // الملف المؤقت بينشأ جوّا الـ try عشان أي فشل مبكر ينظّفه الـ catch
            $temp = tempnam(sys_get_temp_dir(), 'img_');

            if ($temp === false) {
                throw new RuntimeException('Could not create a temporary file');
            }

            // fit(Max): بيصغّر لحد أقصى مع الحفاظ على النسبة وبدون تكبير.
            // المكتبة بتصحّح دوران صور الموبايل (EXIF) لحالها وقت التحميل، فما منعملها orientation() بإيدينا.
            // حفظ WebP بيرمي الـ metadata الأصلي (الموقع، نوع الكاميرا)، وهاد مفيد للخصوصية.
            Image::useImageDriver(config('images.driver'))
                ->loadFile($source)
                ->fit(Fit::Max, $settings['max'], $settings['max'])
                ->quality($settings['quality'])
                ->format('webp')
                ->save($temp);

            return $temp;
        } catch (Throwable $e) {
            report($e);
            $this->discard($temp);

            return false;
        }
    }

    /**
     * المرحلة الخفيفة: نسخ الملف الجاهز للتخزين باسم عشوائي مثل اللي بيعمله store()،
     * والامتداد دايمًا webp. ما بتحذف الملف المؤقت، هاد شغل discard().
     */
    public function publish(string $processed, string $directory, string $disk): string|false
    {
        try {
            return Storage::disk($disk)->putFileAs($directory, new File($processed), Str::random(40).'.webp');
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /** حذف ملف/ملفات مؤقتة ناتجة عن process()، آمن لو القيمة false أو الملف مش موجود. */
    public function discard(string|array|false|null $paths): void
    {
        foreach ((array) $paths as $path) {
            if (is_string($path) && $path !== '') {
                @unlink($path);
            }
        }
    }

    /**
     * فحص قبل أي فك ضغط:
     * 1) النوع من محتوى الملف مش من امتداده، ولازم يكون ضمن images.allowed_types.
     *    ملاحظة: getimagesize بتقرأ الهيدر بس، فهاد مش ضمان أمني لحاله، الضمان الحقيقي
     *    إن الصورة بتنفك كاملة وبتنكتب من جديد (الناتج مش الملف الأصلي).
     * 2) سقف ثابت للبكسلات (images.max_pixels)، وهو الفحص الأساسي لأنه ما بيعتمد على بيئة PHP.
     * 3) تقدير الذاكرة، ثانوي: على بعض نسخ PHP ذاكرة GD ما بتنحسب ضمن memory_limit.
     *
     * فك صورة كبيرة بدون هالفحوص ممكن يخلّي PHP يموت (fatal) وما بنقدر نلقطه بـ try/catch.
     */
    private function assertAcceptable(string $path): void
    {
        $info = @getimagesize($path);

        if ($info === false) {
            throw new RuntimeException('Unreadable image');
        }

        [$width, $height, $type] = $info;

        if (! in_array($type, (array) config('images.allowed_types'), true)) {
            throw new RuntimeException("Image type [{$type}] is not allowed");
        }

        if ($width < 1 || $height < 1) {
            throw new RuntimeException('Invalid image dimensions');
        }

        $pixels = $width * $height;
        $maxPixels = (int) config('images.max_pixels');

        if ($maxPixels > 0 && $pixels > $maxPixels) {
            throw new RuntimeException("Image {$width}x{$height} exceeds the pixel limit");
        }

        $limit = ini_parse_quantity((string) ini_get('memory_limit'));

        if ($limit <= 0) { // -1 = بدون حد
            return;
        }

        if ($pixels * self::BYTES_PER_PIXEL > $limit - memory_get_usage(true)) {
            throw new RuntimeException("Image {$width}x{$height} is too large for the PHP memory limit");
        }
    }
}