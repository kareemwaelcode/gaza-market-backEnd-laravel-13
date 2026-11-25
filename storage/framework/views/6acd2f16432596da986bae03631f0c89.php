<?php $__env->startSection('title', 'show product'); ?>

<?php $__env->startSection('styles'); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $badge = [
        'pending' => 'bg-warning text-dark',
        'active' => 'bg-success',
        'hidden' => 'bg-secondary',
    ];
    $status = $product->product_status;
?>
<div class="card" style="margin-top: 16px;">
<div class="card-header">
<strong>Product #<?php echo e($product->id); ?></strong>
<span class="badge <?php echo e($badge[$status] ?? 'bg-secondary'); ?>"><?php echo e(ucfirst($status)); ?></span>
</div>
<div class="card-body">
<div class="row">
<div class="col-lg-8">
    <h4><?php echo e($product->product_name_ar ?: $product->product_name_en); ?></h4>

    <h6 class="mt-3">Receipt</h6>
    <div class="mb-3" style="max-width: 520px;">
        <?php if($product->product_receipt_image): ?>
            <a href="<?php echo e(route('product_receipt', $product->id)); ?>" target="_blank" rel="noopener noreferrer">
                <img src="<?php echo e(route('product_receipt', $product->id)); ?>" class="d-block w-100 border rounded"
                        style="object-fit: contain;" alt="Receipt">
            </a>
        <?php else: ?>
            <div class="ratio ratio-4x3 border rounded bg-light">
                <div class="d-flex align-items-center justify-content-center text-muted small">No receipt</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="col-lg-4">
    <table class="table table-sm">
        <tr><th>Name (AR)</th><td><?php echo e($product->product_name_ar ?: '-'); ?></td></tr>
        <tr><th>Name (EN)</th><td><?php echo e($product->product_name_en ?: '-'); ?></td></tr>
        <tr><th>Category</th><td><?php echo e($product->category?->category_name_ar); ?> / <?php echo e($product->category?->category_name_en); ?></td></tr>
        <tr><th>Quantity</th><td><?php echo e($product->product_quantity); ?> <?php echo e($product->unit?->unit_name_ar); ?></td></tr>
        <tr><th>Price</th><td><?php echo e(number_format((float) $product->product_price, 2)); ?> ₪</td></tr>
        <tr><th>Store</th><td><?php echo e($product->product_store_name); ?></td></tr>
        <tr><th>Store address</th><td><?php echo e($product->product_store_address ?: '-'); ?></td></tr>
        <tr><th>Store phone</th><td><?php echo e($product->product_store_phone ?: '-'); ?></td></tr>
        <tr><th>Area</th><td><?php echo e($product->area?->region?->region_name_ar); ?> - <?php echo e($product->area?->area_name_ar); ?></td></tr>
        <tr><th>Added by</th><td><?php echo e($product->user?->user_full_name); ?> <small>(<?php echo e($product->user?->user_phone); ?>)</small></td></tr>
        <tr><th>Created</th><td><?php echo e($product->created_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i')); ?></td></tr>
        <tr><th>Updated</th><td><?php echo e($product->updated_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i')); ?></td></tr>
    </table>
</div>
</div>
</div>
<div class="card-footer">
<?php if($status === 'pending'): ?>
<button type="button" onclick="approveProduct(<?php echo e($product->id); ?>)" class="btn btn-success btn-sm">Approve</button>
<?php endif; ?>
<?php if($status === 'active'): ?>
<button type="button" onclick="hideProduct(<?php echo e($product->id); ?>)" class="btn btn-warning btn-sm">Hide</button>
<?php endif; ?>
<?php if($status === 'hidden'): ?>
<button type="button" onclick="unhideProduct(<?php echo e($product->id); ?>)" class="btn btn-success btn-sm">Show</button>
<?php endif; ?>
<button type="button" onclick="deleteProduct(<?php echo e($product->id); ?>)" class="btn btn-danger btn-sm">Delete</button>
<a href="<?php echo e(route('product.index')); ?>" class="btn btn-secondary btn-sm">Index</a>
</div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
function approveProduct(id){
storeRoute('/cms/admins/product_approve/' + id, new FormData());
}

function hideProduct(id){
storeRoute('/cms/admins/product_hide/' + id, new FormData());
}

function unhideProduct(id){
storeRoute('/cms/admins/product_unhide/' + id, new FormData());
}

function deleteProduct(id){
Swal.fire({
title: 'هل أنت متأكد من عملية الحذف ؟',
text: 'لا يمكن التراجع عن عملية الحذف',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'نعم',
cancelButtonText: 'لا',
}).then((result) => {
if (result.isConfirmed) {
    axios.delete('/cms/admins/product/' + id)
        .then(function () { window.location = '<?php echo e(route('product.index')); ?>'; })
        .catch(function (error) {
            showMessage(error.response ? error.response.data : { icon: 'error', title: 'Delete failed' });
        });
}
});
}
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('cms.parent', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\gaza-market\resources\views/cms/product/show.blade.php ENDPATH**/ ?>