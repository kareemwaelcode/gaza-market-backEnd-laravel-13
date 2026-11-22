

<?php $__env->startSection('title', 'role-permission'); ?>

<?php $__env->startSection('styles'); ?>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('content'); ?>
<div class="row row-tight" style="margin-top: 16px;">
<div class="col-md-12">
<section class="m-card" aria-labelledby="role-permission-title">
<header class="m-card__header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
<div>
<h2 class="m-card__title" id="role-permission-title"><?php echo e($role->name); ?></h2>
<p class="m-card__subtitle">
    Permissions of this role
    <span class="badge bg-success"><?php echo e($role->guard_name); ?></span>
</p>
</div>
<a href="<?php echo e(route('roles.index')); ?>" class="btn btn-secondary btn-sm">Back to roles</a>
</header>

<div class="table-responsive">
<table class="table table-borderless table-data3">
<thead>
    <tr>
        <th>ID</th>
        <th>Guard Name</th>
        <th>Permission</th>
        <th style="width: 120px;">Status</th>
    </tr>
</thead>
<tbody>
    <?php $__empty_1 = true; $__currentLoopData = $permissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
            <td><?php echo e($loop->iteration); ?></td>
            <td><span class="badge bg-success"><?php echo e($permission->guard_name); ?></span></td>
            <td><?php echo e($permission->name); ?></td>
            <td>
                <label class="au-checkbox">
                    <input type="checkbox"
                            id="permission_<?php echo e($permission->id); ?>"
                            <?php if(in_array($permission->id, $assignedIds, true)): echo 'checked'; endif; ?>
                            onchange="storeRolePermission(<?php echo e($permission->id); ?>, this)">
                    <span class="au-checkmark"></span>
                </label>
            </td>
        </tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr>
            <td colspan="4" class="text-center">
                No permissions for guard "<?php echo e($role->guard_name); ?>" yet.
                <a href="<?php echo e(route('permissions.create')); ?>">Create one</a>
            </td>
        </tr>
    <?php endif; ?>
</tbody>
</table>
</div>
</section>
</div>
</div>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('scripts'); ?>
<script>
function storeRolePermission(permissionId, checkbox) {
let formData = new FormData();
formData.append('permission_id', permissionId);
formData.append('active', checkbox.checked ? 1 : 0);

axios.post('<?php echo e(route('roles.permissions.store', $role)); ?>', formData)
.then(function (response) {
showMessage(response.data);
})
.catch(function (error) {
checkbox.checked = !checkbox.checked;
showMessage(error.response && error.response.data
? error.response.data
: {icon: 'error', title: 'Request failed'});
});
}
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('cms.parent', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\gaza-market\resources\views/cms/spatie/role/role-permission.blade.php ENDPATH**/ ?>