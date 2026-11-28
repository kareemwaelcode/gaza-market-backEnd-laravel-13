<?php $__env->startSection('title', 'show listing'); ?>

<?php $__env->startSection('styles'); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $badge = [
        'pending' => 'bg-warning text-dark',
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
        'sold' => 'bg-secondary',
    ];
    $status = $listing->listing_status;
    $canApprove = in_array($status, ['pending', 'rejected'], true);
    $canReject = in_array($status, ['pending', 'approved'], true);

    $images = $listing->images;
?>
<div class="card" style="margin-top: 16px;">
<div class="card-header">
<strong>Listing #<?php echo e($listing->id); ?></strong>
<span class="badge <?php echo e($badge[$status] ?? 'bg-secondary'); ?>"><?php echo e(ucfirst($status)); ?></span>
</div>
<div class="card-body">
<div class="row">
<div class="col-lg-8">
    <h4><?php echo e($listing->listing_title); ?></h4>
    <p style="white-space: pre-line;"><?php echo e($listing->listing_description); ?></p>

    
    <h6 class="mt-3">
        Photos <small class="text-muted">(<?php echo e($images->count()); ?>/3)</small>
    </h6>
    <div class="mb-3" style="max-width: 520px;">
        <?php if($images->isEmpty()): ?>
            <div class="ratio ratio-4x3 border rounded bg-light">
                <div class="d-flex align-items-center justify-content-center text-muted small">No photos</div>
            </div>
        <?php else: ?>
            <div id="listingPhotos" class="carousel slide border rounded overflow-hidden">
                <?php if($images->count() > 1): ?>
                    <div class="carousel-indicators">
                        <?php $__currentLoopData = $images; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $image): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <button type="button" data-bs-target="#listingPhotos" data-bs-slide-to="<?php echo e($loop->index); ?>"
                                    class="<?php echo \Illuminate\Support\Arr::toCssClasses(['active' => $loop->first]); ?>" <?php if($loop->first): ?> aria-current="true" <?php endif; ?>
                                    aria-label="Photo <?php echo e($loop->iteration); ?>"></button>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                <?php endif; ?>

                <div class="carousel-inner">
                    <?php $__currentLoopData = $images; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $image): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['carousel-item', 'active' => $loop->first]); ?>">
                            <img src="<?php echo e($image->url); ?>" class="d-block w-100"
                                    style="aspect-ratio: 4 / 3; object-fit: cover;"
                                    alt="Listing photo <?php echo e($loop->iteration); ?>">
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>

                <?php if($images->count() > 1): ?>
                    <button class="carousel-control-prev" type="button" data-bs-target="#listingPhotos" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#listingPhotos" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
</div>

<div class="col-lg-4">
    <table class="table table-sm">
        <tr><th>Price</th><td><?php echo e(number_format((float) $listing->listing_price, 2)); ?> ₪ <?php if($listing->listing_is_negotiable): ?><small>(negotiable)</small><?php endif; ?></td></tr>
        <tr><th>Condition</th><td><?php echo e(ucfirst($listing->listing_condition)); ?></td></tr>
        <tr><th>Category</th><td><?php echo e($listing->category?->listing_category_name_ar); ?> / <?php echo e($listing->category?->listing_category_name_en); ?></td></tr>
        <tr><th>Area</th><td><?php echo e($listing->area?->region?->region_name_ar); ?> - <?php echo e($listing->area?->area_name_ar); ?></td></tr>
        <tr><th>Phone</th><td><?php echo e($listing->listing_phone); ?></td></tr>
        <tr><th>WhatsApp</th><td><?php if($listing->whatsapp_link): ?><a href="<?php echo e($listing->whatsapp_link); ?>" target="_blank" rel="noopener noreferrer" dir="ltr"><?php echo e($listing->listing_whatsapp); ?></a><?php else: ?> - <?php endif; ?></td></tr>
        <tr><th>Owner</th><td><?php echo e($listing->user?->user_full_name); ?> <small>(<?php echo e($listing->user?->user_phone); ?>)</small></td></tr>
        <tr><th>Created</th><td><?php echo e($listing->created_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i')); ?></td></tr>
        <tr><th>Published</th><td><?php echo e($listing->listing_published_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?? '-'); ?></td></tr>
    </table>
</div>
</div>
</div>
<div class="card-footer">
<?php if($canApprove): ?>
<button type="button" onclick="approveListing(<?php echo e($listing->id); ?>)" class="btn btn-success btn-sm">Approve</button>
<?php endif; ?>
<?php if($canReject): ?>
<button type="button" onclick="rejectListing(<?php echo e($listing->id); ?>)" class="btn btn-warning btn-sm">Reject</button>
<?php endif; ?>
<button type="button" onclick="deleteListing(<?php echo e($listing->id); ?>)" class="btn btn-danger btn-sm">Delete</button>
<a href="<?php echo e(route('listing.index')); ?>" class="btn btn-secondary btn-sm">Index</a>
</div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
function approveListing(id){
storeRoute('/cms/admins/listing_approve/' + id, new FormData());
}

function rejectListing(id){
Swal.fire({
title: 'رفض الإعلان؟',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'رفض',
cancelButtonText: 'إلغاء',
}).then((result) => {
if (result.isConfirmed) {
    storeRoute('/cms/admins/listing_reject/' + id, new FormData());
}
});
}

function deleteListing(id){
Swal.fire({
title: 'هل أنت متأكد من عملية الحذف ؟',
text: 'لا يمكن التراجع عن عملية الحذف',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'نعم',
cancelButtonText: 'لا',
}).then((result) => {
if (result.isConfirmed) {
    axios.delete('/cms/admins/listing/' + id)
        .then(function () { window.location = '<?php echo e(route('listing.index')); ?>'; })
        .catch(function (error) {
            showMessage(error.response ? error.response.data : { icon: 'error', title: 'Delete failed' });
        });
}
});
}
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('cms.parent', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\gaza-market\resources\views/cms/listing/show.blade.php ENDPATH**/ ?>