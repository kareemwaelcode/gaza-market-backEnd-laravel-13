<?php $__env->startSection('title', 'index listing category'); ?>

<?php $__env->startSection('styles'); ?>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('content'); ?>
    <!-- Listing categories table -->
    <div class="row row-tight" style="margin-top: 16px;">
    <div class="col-md-12">
        <section class="m-card" aria-labelledby="listing-categories-title">
    <header class="m-card__header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <div>
            <h2 class="m-card__title" id="listing-categories-title">Listing Categories</h2>
            <p class="m-card__subtitle">Categories used to classify listings</p>
        </div>
        <a href="<?php echo e(route('listing_category.create')); ?>" type="button" class="m-btn m-btn--primary" style="height:30px;padding:0 12px;font-size:12.5px;"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus preview-icon"><path d="M5 12h14"/><path d="M12 5v14"/></svg>Add Listing Category</a>
    </header>
        <div class="table-responsive">
            <table class="table table-borderless table-data3">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Slug</th>
                        <th>Name (EN)</th>
                        <th>Name (AR)</th>
                        <th>Status</th>
                        <th>Sort Order</th>
                        <th>Setting</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $listingCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $listingCategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($listingCategories->firstItem() + $loop->index); ?></td>
                        <td><?php echo e($listingCategory->listing_category_slug); ?></td>
                        <td><?php echo e($listingCategory->listing_category_name_en); ?></td>
                        <td><?php echo e($listingCategory->listing_category_name_ar); ?></td>
                        <td><?php echo e($listingCategory->listing_category_is_active ? 'Active' : 'Inactive'); ?></td>
                        <td><?php echo e($listingCategory->listing_category_sort_order); ?></td>
                        <td>
                            <div class="btn-group" role="group" aria-label="Toolbar group">
                            <a href="<?php echo e(route('listing_category.edit', $listingCategory->id)); ?>">
                                <button type="button" class="btn btn-info"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-pen preview-icon"><path d="M2 21a8 8 0 0 1 10.821-7.487"/><path d="M21.378 16.626a1 1 0 0 0-3.004-3.004l-4.01 4.012a2 2 0 0 0-.506.854l-.837 2.87a.5.5 0 0 0 .62.62l2.87-.837a2 2 0 0 0 .854-.506z"/><circle cx="10" cy="8" r="5"/></svg></button>
                            </a>
                            <a href="#">
                                <button type="button" onclick="performDestroy(<?php echo e($listingCategory->id); ?>, this)" class="btn btn-danger"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash preview-icon"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                            </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php echo e($listingCategories->links()); ?>

</div>
</div>
<script>
function performDestroy(id, reference){
    let url = '/cms/admins/listing_category/'+id;
    confirmDestroy(url, reference);
}
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('cms.parent', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\gaza-market\resources\views/cms/listing_category/index.blade.php ENDPATH**/ ?>