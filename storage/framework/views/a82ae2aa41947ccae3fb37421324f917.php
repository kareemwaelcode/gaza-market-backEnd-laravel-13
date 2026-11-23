<?php $__env->startSection('title', 'edit listing category'); ?>

<?php $__env->startSection('styles'); ?>

<?php $__env->stopSection(); ?>


<?php $__env->startSection('content'); ?>
    <div class="card">
    <div class="card-header">
        <strong>Editing listing category details.</strong>
    </div>
    <div class="card-body card-body">
        <form id="create_form">
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="listing_category_name_en" class="form-control-label">Name (English)</label>
                    <input type="text" id="listing_category_name_en" name="listing_category_name_en" maxlength="100"
                    value="<?php echo e($listingCategory->listing_category_name_en); ?>" placeholder="Enter Name in English.." class="form-control">
                    <span class="help-block">Please enter the name in English</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="listing_category_name_ar" class="form-control-label">Name (Arabic)</label>
                    <input type="text" id="listing_category_name_ar" name="listing_category_name_ar" maxlength="100"
                    value="<?php echo e($listingCategory->listing_category_name_ar); ?>" placeholder="Enter Name in Arabic.." class="form-control">
                    <span class="help-block">Please enter the name in Arabic</span>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="listing_category_is_active" class="form-control-label">Status</label>
                    <select id="listing_category_is_active" name="listing_category_is_active" class="form-control">
                        <option value="1" <?php if($listingCategory->listing_category_is_active): echo 'selected'; endif; ?>>Active</option>
                        <option value="0" <?php if(! $listingCategory->listing_category_is_active): echo 'selected'; endif; ?>>Inactive</option>
                    </select>
                    <span class="help-block">Inactive categories will not appear when adding a listing</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="listing_category_sort_order" class="form-control-label">Sort Order</label>
                    <input type="number" id="listing_category_sort_order" name="listing_category_sort_order" min="0" max="32767"
                    value="<?php echo e($listingCategory->listing_category_sort_order); ?>" placeholder="Enter Sort Order.." class="form-control">
                    <span class="help-block">Please enter the sort order (0 - 32767)</span>
                </div>
            </div>
        </form>
    </div>
    <div class="card-footer">
        <button type="button" onclick="performUpdate(<?php echo e($listingCategory->id); ?>)" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus preview-icon"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Update
        </button>
        <a href="<?php echo e(route('listing_category.index')); ?>" type="button" class="btn btn-secondary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-table-of-contents preview-icon"><path d="M16 5H3"/><path d="M16 12H3"/><path d="M16 19H3"/><path d="M21 5h.01"/><path d="M21 12h.01"/><path d="M21 19h.01"/></svg>
            Index
        </a>

    </div>
</div>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('scripts'); ?>
<script>
    function performUpdate(id){
        let formData = new FormData();
        formData.append('listing_category_name_en', document.getElementById('listing_category_name_en').value);
        formData.append('listing_category_name_ar', document.getElementById('listing_category_name_ar').value);
        formData.append('listing_category_is_active', document.getElementById('listing_category_is_active').value);
        formData.append('listing_category_sort_order', document.getElementById('listing_category_sort_order').value);
        storeRoute('/cms/admins/listing_category_update/' + id, formData);
    }
    </script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('cms.parent', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\gaza-market\resources\views/cms/listing_category/edit.blade.php ENDPATH**/ ?>