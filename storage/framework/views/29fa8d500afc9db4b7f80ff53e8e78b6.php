

<?php $__env->startSection('title', 'edit Permission'); ?>

<?php $__env->startSection('styles'); ?>

<?php $__env->stopSection(); ?>


<?php $__env->startSection('content'); ?>
    <div class="card">
    <div class="card-header">
        <strong>Edit Permission</strong>
    </div>
    <div class="card-body card-body">
        <form id="create_form">
            <?php echo csrf_field(); ?>
            <div class="row">
        <div class="col-12 col-md-6">
            <label for="guard_name" class="form-control-label">Guard Name</label>
        <select name="guard_name" id="guard_name" class="form-select">
            <option value="admins" <?php if($permissions->guard_name == 'admins'): ?> selected <?php endif; ?>>Admin</option>
            <option value="users" <?php if($permissions->guard_name == 'users'): ?> selected <?php endif; ?>>User</option>
        </select>
    </div>
                
                <div class="col-lg-6 mb-3">
                    <label for="name" class="form-control-label">Permission Name</label>
                    <input type="text" id="name" name="name" maxlength="45" 
                    value="<?php echo e($permissions->name); ?>" placeholder="Enter Permission Name in Arabic.." class="form-control">
                    <span class="help-block">Please enter the Permission name in Arabic</span>
                </div>
            </div>
            
        </form>
    </div>
    <div class="card-footer">
        <button type="button" onclick="performUpdate(<?php echo e($permissions->id); ?>)" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus preview-icon"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Update
        </button>
        <a href="<?php echo e(route('permissions.index')); ?>" type="button" class="btn btn-secondary btn-sm">
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
    formData.append('guard_name', document.getElementById('guard_name').value);
    formData.append('name', document.getElementById('name').value);
    storeRoute('/cms/admins/permissions_update/' +id , formData);
    }
    </script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('cms.parent', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\gaza-market\resources\views/cms/spatie/permission/edit.blade.php ENDPATH**/ ?>