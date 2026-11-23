<?php $__env->startSection('title', 'index product'); ?>

<?php $__env->startSection('styles'); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $badge = [
        'pending' => 'bg-warning text-dark',
        'active' => 'bg-success',
        'hidden' => 'bg-secondary',
    ];
?>
<div class="row row-tight" style="margin-top: 16px;">
    <div class="col-md-12">
        <section class="m-card" aria-labelledby="products-title">
            <header class="m-card__header" style="width: 100%;">
                <h2 class="m-card__title" id="products-title">Products</h2>
                <p class="m-card__subtitle">Review prices submitted by users. Pending prices stay hidden until approved.</p>
            </header>

            <?php if(! empty($ignoredFilters)): ?>
                <div class="alert alert-warning" role="alert" style="margin: 0 16px 16px;">
                    Invalid filter value ignored (<?php echo e(implode(', ', $ignoredFilters)); ?>). Showing results without it.
                </div>
            <?php endif; ?>

            <form method="GET" action="<?php echo e(route('product.index')); ?>" class="row g-2 mb-3" style="padding: 0 16px;">
                <div class="col-lg-3">
                    <select name="status" class="form-control">
                        <option value="">All statuses</option>
                        <?php $__currentLoopData = \App\Models\Product::STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($status); ?>" <?php if(($filters['status'] ?? null) === $status): echo 'selected'; endif; ?>><?php echo e(ucfirst($status)); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="col-lg-4">
                    <select name="category" class="form-control">
                        <option value="">All categories</option>
                        <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($category->id); ?>" <?php if((int) ($filters['category'] ?? 0) === $category->id): echo 'selected'; endif; ?>><?php echo e($category->category_name_ar); ?> / <?php echo e($category->category_name_en); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="col-lg-3">
                    <select name="area" class="form-control">
                        <option value="">All areas</option>
                        <?php $__currentLoopData = $areas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $area): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($area->id); ?>" <?php if((int) ($filters['area'] ?? 0) === $area->id): echo 'selected'; endif; ?>><?php echo e($area->area_name_ar); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="col-lg-2">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="<?php echo e(route('product.index')); ?>" class="btn btn-secondary btn-sm">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-borderless table-data3">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Store</th>
                            <th>Area</th>
                            <th>Added by</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Setting</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td><?php echo e($product->id); ?></td>
                            <td><?php echo e($product->product_name_ar ?: $product->product_name_en); ?></td>
                            <td><?php echo e($product->category?->category_name_ar); ?></td>
                            <td><?php echo e($product->product_quantity); ?> <?php echo e($product->unit?->unit_name_ar); ?></td>
                            <td><?php echo e(number_format((float) $product->product_price, 2)); ?> ₪</td>
                            <td><?php echo e($product->product_store_name); ?></td>
                            <td><?php echo e($product->area?->area_name_ar); ?></td>
                            <td><?php echo e($product->user?->user_full_name); ?><br><small><?php echo e($product->user?->user_phone); ?></small></td>
                            <td><span class="badge <?php echo e($badge[$product->product_status] ?? 'bg-secondary'); ?>"><?php echo e(ucfirst($product->product_status)); ?></span></td>
                            <td><?php echo e($product->created_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i')); ?></td>
                            <td>
                                <div class="btn-group" role="group" aria-label="Toolbar group">
                                    <a href="<?php echo e(route('product.show', $product->id)); ?>" class="btn btn-info btn-sm">View</a>
                                    <button type="button" onclick="performDestroy(<?php echo e($product->id); ?>, this)" class="btn btn-danger btn-sm">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="11" class="text-center">No products found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php echo e($products->links()); ?>

    </div>
</div>
<script>
function performDestroy(id, reference){
    let url = '/cms/admins/product/'+id;
    confirmDestroy(url, reference);
}
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('cms.parent', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\gaza-market\resources\views/cms/product/index.blade.php ENDPATH**/ ?>