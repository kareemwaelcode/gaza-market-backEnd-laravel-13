

<?php $__env->startSection('title', 'edit admin'); ?>

<?php $__env->startSection('styles'); ?>

<?php $__env->stopSection(); ?>


<?php $__env->startSection('content'); ?>
    <div class="card">
    <div class="card-header">
        <strong>Adding supervisor details.</strong>
    </div>
    <div class="card-body">
        <form>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label for="full_name" class="form-control-label">Full name</label>
                    <input type="text" id="full_name" name="full_name"
                    value="<?php echo e($admins->full_name); ?>" placeholder="Enter Full Name.." class="form-control">
                    <span class="help-block">Please enter your Full Name</span>
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label for="mobile" class="form-control-label">Mobile</label>
                    <input type="text" id="mobile" name="mobile"
                    value="<?php echo e($admins->mobile); ?>" placeholder="Enter Mobile.." class="form-control">
                    <span class="help-block">Please enter your Mobile</span>
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label for="email" class="form-control-label">Email</label>
                    <input type="email" id="email" name="email"
                    value="<?php echo e($admins->email); ?>" placeholder="Enter Email.." class="form-control">
                    <span class="help-block">Please enter your email</span>
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label for="role_id" class="form-control-label">Role Name</label>
                    <select name="role_id" id="role_id" class="form-select">
                        <?php if (! ($currentRoleId)): ?>
                            <option value="" selected disabled>— اختر الرول —</option>
                        <?php endif; ?>
                        <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($role->id); ?>" <?php if($role->id === $currentRoleId): echo 'selected'; endif; ?>><?php echo e($role->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <span class="help-block">Please choose a role</span>
                </div>
            </div>
        </form>
    </div>
    <div class="card-footer">
        <button type="button" onclick="performUpdate(<?php echo e($admins->id); ?>)" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-plus preview-icon"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
            Update
        </button>

    </div>
</div>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('scripts'); ?>
<script>
    function performUpdate(id){
        let formData = new FormData();
        formData.append('full_name', document.getElementById('full_name').value);
        formData.append('mobile', document.getElementById('mobile').value);
        formData.append('email', document.getElementById('email').value);
        formData.append('role_id', document.getElementById('role_id').value);
        storeRoute('/cms/admins/admins_update/' +id, formData);
    }
    </script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('cms.parent', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\gaza-market\resources\views/cms/admin/edit.blade.php ENDPATH**/ ?>