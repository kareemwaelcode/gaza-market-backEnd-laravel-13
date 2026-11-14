<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\PriceTicker;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BackfillFirstProductPrices extends Command
{
protected $signature = 'prices:backfill-first {--dry-run : عدّ المنتجات المعنية بدون كتابة أي شي}';
protected $description = 'ينشئ السعر الأول (approved) للمنتجات الفعّالة اللي انوافق عليها قبل ما الموافقة تنشئه تلقائياً';

public function handle(): int {
$query = Product::query()
    ->active()
    ->whereDoesntHave('prices', fn (Builder $q) => $q
        ->whereColumn('product_prices.user_id', 'products.user_id')
        ->whereColumn('product_prices.product_price_store_name', 'products.product_store_name')
        ->whereColumn('product_prices.product_price_value', 'products.product_price')
        ->whereColumn('product_prices.area_id', 'products.area_id'));

if ($this->option('dry-run')) {
    $this->info('Products that would get a first price: '.(clone $query)->count());
    return self::SUCCESS;
}

$created = 0;

$query->chunkById(200, function ($products) use (&$created) {
    foreach ($products as $product) {
        DB::transaction(fn () => $product->createFirstPrice());
        $created++;
    }
});

if ($created > 0) {
    PriceTicker::forget();
}

$this->info("First prices created: {$created}");

return self::SUCCESS;
}
}