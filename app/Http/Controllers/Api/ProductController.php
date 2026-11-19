<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Support\ApiCache;
use App\Support\PriceTicker;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * إدارة منتجات الكتالوج للأدمن (مراجعة، إخفاء، حذف).
 * بحث المنتجات وإضافتهم من المستخدم بيضلوا بـ Api\ProductController.
 */
class ProductController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth:admins-api'),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $validator = validator($request->query(), [
            'status' => ['nullable', Rule::in(Product::STATUSES)],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'area_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $filters = array_filter($validator->validated(), fn ($value) => $value !== null);

        $products = Product::query()
            ->with([
                'user:id,user_full_name,user_phone',
                'category:id,category_name_ar,category_name_en',
                'unit:id,unit_name_ar,unit_name_en',
                'area:id,area_name_ar',
            ])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('product_status', $status))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['area_id'] ?? null, fn ($q, $id) => $q->where('area_id', $id))
            ->orderByRaw("case when product_status = 'pending' then 0 else 1 end")
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->appends($filters);

        return response()->json([
            'status' => true,
            'message' => 'Data of Products',
            'data' => $products,
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $product = Product::with([
            'user:id,user_full_name,user_phone,user_status',
            'category',
            'unit',
            'area:id,area_name_ar,region_id',
            'area.region:id,region_name_ar',
        ])->withCount('prices')->findOrFail($id);

        // الأدمن بيشوف رقم المحل، والإيصال بيتحمّل من راوت منفصل
        $product->makeVisible('product_store_phone');
        $product->setAttribute('has_receipt', filled($product->product_receipt_image));

        return response()->json([
            'status' => true,
            'message' => 'Data of Product',
            'data' => $product,
        ], 200);
    }

    public function receipt(string $id)
    {
        $product = Product::findOrFail($id);
        $path = $product->product_receipt_image;

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function approve(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $approved = DB::transaction(function () use ($product) {
            $updated = Product::whereKey($product->id)
                ->where('product_status', Product::STATUS_PENDING)
                ->update([
                    'product_status' => Product::STATUS_ACTIVE,
                    'updated_at' => now(),
                ]);

            if (! $updated) {
                return false;
            }

            $product->refresh()->createFirstPrice();

            return true;
        });

        if (! $approved) {
            return $this->conflict('Only pending products can be approved');
        }

        PriceTicker::refreshAfterChange();
        ApiCache::productsChanged();

        return response()->json([
            'status' => true,
            'message' => 'Approved Successfully',
            'data' => $product->fresh(),
        ], 200);
    }

    public function hide(string $id): JsonResponse
    {
        return $this->changeStatus($id, Product::STATUS_ACTIVE, Product::STATUS_HIDDEN, 'Only active products can be hidden', 'Hidden Successfully');
    }

    public function unhide(string $id): JsonResponse
    {
        return $this->changeStatus($id, Product::STATUS_HIDDEN, Product::STATUS_ACTIVE, 'Only hidden products can be shown', 'Shown Successfully');
    }

    public function destroy(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $priceReceipts = [];

        try {
            // الأسعار (وأصواتها بالـ cascade) بتنحذف مع المنتج كوحدة وحدة
            $deleted = DB::transaction(function () use ($product, &$priceReceipts) {
                $priceReceipts = ProductPrice::query()
                    ->where('product_id', $product->id)
                    ->whereNotNull('product_price_receipt_image')
                    ->pluck('product_price_receipt_image')
                    ->all();

                ProductPrice::query()->where('product_id', $product->id)->delete();

                return Product::whereKey($product->id)->delete();
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return $this->conflict('This product is still referenced and cannot be deleted');
            }

            throw $e;
        }

        if (! $deleted) {
            return response()->json(['status' => false, 'message' => 'Product not found'], 404);
        }

        // الملفات بعد الـ commit: لو فشلت المعاملة ما منخسر أي وصل
        $paths = array_filter(array_merge([$product->product_receipt_image], $priceReceipts));

        foreach ($paths as $path) {
            Storage::disk('local')->delete($path);
        }

        PriceTicker::refreshAfterChange();
        ApiCache::productsChanged();

        return response()->json([
            'status' => true,
            'message' => 'Deleted Successfully',
        ], 200);
    }

    private function changeStatus(string $id, string $from, string $to, string $error, string $success): JsonResponse
    {
        $product = Product::findOrFail($id);

        // تحديث شرطي بعملية وحدة (آمن مع الطلبات المتزامنة)
        $updated = Product::whereKey($product->id)
            ->where('product_status', $from)
            ->update([
                'product_status' => $to,
                'updated_at' => now(),
            ]);

        if (! $updated) {
            return $this->conflict($error);
        }

        PriceTicker::refreshAfterChange();
        ApiCache::productsChanged();

        return response()->json([
            'status' => true,
            'message' => $success,
            'data' => $product->fresh(),
        ], 200);
    }

    private function conflict(string $message): JsonResponse
    {
        return response()->json(['status' => false, 'message' => $message], 409);
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => 'Validation failed',
            'errors' => $errors,
        ], 422);
    }
}