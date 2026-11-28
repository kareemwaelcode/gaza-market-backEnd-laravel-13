<?php

namespace App\Support;

use App\Models\NavigationItem;
use Illuminate\Support\HtmlString;
use Throwable;

class NavigationMenu
{
    /**
     * العناصر الفعّالة مرتبة. الكاش بيتجدد لما أي عنصر ينضاف أو يتعدل أو ينحذف.
     */
    public static function items(): array
    {
        return ApiCache::lookup(
            'navigation-menu',
            fn () => NavigationItem::active()->ordered()->get()->toArray(),
            paged: false,
        );
    }

    /**
     * أيقونة Lucide بالاسم المخزّن بالجدول. الاسم الغلط بيرجع دائرة بدل ما تقع الصفحة.
     */
    public static function icon(string $name, int $size): HtmlString
    {
        // قبل تثبيت mallardduck/blade-lucide-icons الدالة svg() مش موجودة.
        if (! function_exists('svg')) {
            return new HtmlString('');
        }

        foreach ([$name, 'circle'] as $icon) {
            try {
                return new HtmlString(svg('lucide-'.$icon, '', [
                    'width' => $size,
                    'height' => $size,
                    'aria-hidden' => 'true',
                ])->toHtml());
            } catch (Throwable $e) {
                report($e);
            }
        }

        return new HtmlString('');
    }
}