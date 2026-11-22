

<?php $__env->startSection('title', 'show product price'); ?>

<?php $__env->startSection('styles'); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $badge = [
        'pending' => 'bg-warning text-dark',
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
        'flagged' => 'bg-dark',
    ];
    $status = $price->product_price_status;
    $statusLabel = ['rejected' => 'Hidden', 'flagged' => 'Hidden (reports)'][$status] ?? ucfirst($status);
    $canShow = $status !== 'approved';
    $canHide = in_array($status, ['pending', 'approved', 'flagged'], true);
    $product = $price->product;
?>
<div class="card" style="margin-top: 16px;">
<div class="card-header">
<strong>Price #<?php echo e($price->id); ?></strong>
<span class="badge <?php echo e($badge[$status] ?? 'bg-secondary'); ?>"><?php echo e($statusLabel); ?></span>
</div>
<div class="card-body">
<div class="row">
<div class="col-lg-8">
    <h4><?php echo e($product?->product_name_ar ?: $product?->product_name_en); ?></h4>

    <?php if($status === 'flagged'): ?>
        <div class="alert alert-warning" role="alert">
            Hidden automatically after <?php echo e($price->report_count); ?> reports. Show it to dismiss the reports.
        </div>
    <?php endif; ?>

    <div class="row mt-3">
        <div class="col-md-6">
            <h6>👍 Confirmed by (<?php echo e($confirmedBy->count()); ?>)</h6>
            <ul class="list-unstyled small">
                <?php $__empty_1 = true; $__currentLoopData = $confirmedBy; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $vote): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <li><?php echo e($vote->user?->user_full_name ?: '-'); ?> <small class="text-muted">(<?php echo e($vote->user?->user_phone); ?>)</small></li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <li class="text-muted">No confirmations.</li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="col-md-6">
            <h6>🚩 Reported by (<?php echo e($reportedBy->count()); ?>)</h6>
            <ul class="list-unstyled small">
                <?php $__empty_1 = true; $__currentLoopData = $reportedBy; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $vote): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <li><?php echo e($vote->user?->user_full_name ?: '-'); ?> <small class="text-muted">(<?php echo e($vote->user?->user_phone); ?>)</small></li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <li class="text-muted">No reports.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <h6 class="mt-3">Receipt</h6>
    <div class="mb-3" style="max-width: 520px;">
        <?php if($price->product_price_receipt_image): ?>
            <a href="<?php echo e(route('product_price_receipt', $price->id)); ?>" target="_blank" rel="noopener noreferrer">
                <img src="<?php echo e(route('product_price_receipt', $price->id)); ?>" class="d-block w-100 border rounded"
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
        <tr><th>Product</th><td><?php echo e($product?->product_name_ar ?: '-'); ?> / <?php echo e($product?->product_name_en ?: '-'); ?></td></tr>
        <tr><th>Category</th><td><?php echo e($product?->category?->category_name_ar); ?> / <?php echo e($product?->category?->category_name_en); ?></td></tr>
        <tr><th>Quantity</th><td><?php echo e($product?->product_quantity); ?> <?php echo e($product?->unit?->unit_name_ar); ?></td></tr>
        <tr><th>Price</th><td><?php echo e(number_format((float) $price->product_price_value, 2)); ?> ₪</td></tr>
        <tr><th>Store</th><td><?php echo e($price->product_price_store_name); ?></td></tr>
        <tr><th>Store address</th><td><?php echo e($price->product_price_store_address ?: '-'); ?></td></tr>
        <tr><th>Store phone</th><td><?php echo e($price->product_price_store_phone ?: '-'); ?></td></tr>
        <tr><th>Area</th><td><?php echo e($price->area?->region?->region_name_ar); ?> - <?php echo e($price->area?->area_name_ar); ?></td></tr>
        <tr><th>Reported by</th><td><?php echo e($price->user?->user_full_name); ?> <small>(<?php echo e($price->user?->user_phone); ?>)</small></td></tr>
        <tr><th>Votes</th><td>👍 <?php echo e($price->confirm_count); ?> / 🚩 <?php echo e($price->report_count); ?></td></tr>
        <tr><th>Reviewed</th><td><?php echo e($price->reviewed_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?: '-'); ?></td></tr>
        <tr><th>Created</th><td><?php echo e($price->created_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i')); ?></td></tr>
        <tr><th>Updated</th><td><?php echo e($price->updated_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i')); ?></td></tr>
    </table>
</div>
</div>
</div>
<div class="card-footer">
<?php if($canShow): ?>
<button type="button" onclick="showPrice(<?php echo e($price->id); ?>)" class="btn btn-success btn-sm">Show</button>
<?php endif; ?>
<?php if($canHide): ?>
<button type="button" onclick="hidePrice(<?php echo e($price->id); ?>)" class="btn btn-warning btn-sm">Hide</button>
<?php endif; ?>
<button type="button" onclick="deletePrice(<?php echo e($price->id); ?>)" class="btn btn-danger btn-sm">Delete</button>
<a href="<?php echo e(route('product_price.index')); ?>" class="btn btn-secondary btn-sm">Index</a>
</div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
function showPrice(id){
storeRoute('/cms/admins/product_price_approve/' + id, new FormData());
}

function hidePrice(id){
Swal.fire({
title: 'إخفاء هذا السعر؟',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'إخفاء',
cancelButtonText: 'إلغاء',
}).then((result) => {
if (result.isConfirmed) {
    storeRoute('/cms/admins/product_price_reject/' + id, new FormData());
}
});
}

function deletePrice(id){
Swal.fire({
title: 'هل أنت متأكد من عملية الحذف ؟',
text: 'لا يمكن التراجع عن عملية الحذف',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'نعم',
cancelButtonText: 'لا',
}).then((result) => {
if (result.isConfirmed) {
    axios.delete('/cms/admins/product_price/' + id)
        .then(function () { window.location = '<?php echo e(route('product_price.index')); ?>'; })
        .catch(function (error) {
            showMessage(error.response ? error.response.data : { icon: 'error', title: 'Delete failed' });
        });
}
});
}
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('cms.parent', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\gaza-market\resources\views/cms/product_price/show.blade.php ENDPATH**/ ?>