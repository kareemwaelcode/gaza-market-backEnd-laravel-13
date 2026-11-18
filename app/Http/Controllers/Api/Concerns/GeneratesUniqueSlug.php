<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Support\Str;

trait GeneratesUniqueSlug
{
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    protected function uniqueSlug(string $modelClass, string $column, string $value, int $maxLength = 40): string
    {
        $base = rtrim(Str::limit(Str::slug($value), $maxLength, ''), '-');

        if ($base === '') {
            $base = 'item';
        }

        $slug = $base;
        $i = 2;

        while ($modelClass::query()->where($column, $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}