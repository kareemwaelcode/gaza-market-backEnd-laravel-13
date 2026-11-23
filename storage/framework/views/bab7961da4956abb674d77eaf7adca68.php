

<?php $__env->startSection('title', 'index admin'); ?>

<?php $__env->startSection('styles'); ?>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('content'); ?>
    <!-- Sales table -->
    <div class="row row-tight" style="margin-top: 16px;">
    <div class="col-md-12">
        <section class="m-card" aria-labelledby="sales-title">
    <header class="m-card__header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <div>
            <h2 class="m-card__title" id="sales-title">System users</h2>
            <p class="m-card__subtitle">All users of the Gaza Market system</p>
        </div>
        <a href="<?php echo e(route('admin.create')); ?>" type="button" class="m-btn m-btn--primary" style="height:30px;padding:0 12px;font-size:12.5px;"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus preview-icon"><path d="M5 12h14"/><path d="M12 5v14"/></svg>Add Admin</a>
    </header>
        <div class="table-responsive">
            <table class="table table-borderless table-data3">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Role</th>
                        <th>Setting</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $admins; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $admin): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($admins->firstItem() + $loop->index); ?></td>
                        <td><?php echo e($admin->full_name); ?></td>
                        <td><?php echo e($admin->email); ?></td>
                        <td><?php echo e($admin->mobile); ?></td>
                        <td><?php echo e($admin->roles->pluck('name')->join('، ') ?: '—'); ?></td>
                        <td>
                            <div class="btn-group" role="group" aria-label="Toolbar group">
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('Edit-Admin')): ?>
                            <a href="<?php echo e(route('admin.edit' ,$admin->id )); ?>">
                                <?php endif; ?>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('Delet-Admin')): ?>
                                <button type="button" class="btn btn-info"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-pen preview-icon"><path d="M2 21a8 8 0 0 1 10.821-7.487"/><path d="M21.378 16.626a1 1 0 0 0-3.004-3.004l-4.01 4.012a2 2 0 0 0-.506.854l-.837 2.87a.5.5 0 0 0 .62.62l2.87-.837a2 2 0 0 0 .854-.506z"/><circle cx="10" cy="8" r="5"/></svg></button>
                                <?php endif; ?>    
                            </a>
                            <a href="#">
                                <button type="button" onclick="performDestroy(<?php echo e($admin->id); ?>, this)"  class="btn btn-danger"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash preview-icon"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>س</button>
                            </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php echo e($admins->links()); ?>

</div>
</div>

<?php $__env->stopSection(); ?>


<?php $__env->startSection('scripts'); ?>
<script>
function performDestroy(id, reference){
    let url = '/cms/admins/admin/'+id;
    confirmDestroy(url, reference);
}
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('cms.parent', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\gaza-market\resources\views/cms/admin/index.blade.php ENDPATH**/ ?>