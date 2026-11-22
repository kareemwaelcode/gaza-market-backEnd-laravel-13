

<?php $__env->startSection('title', 'index user'); ?>

<?php $__env->startSection('styles'); ?>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('content'); ?>
    <?php
        $roles = [
            'user' => ['مستخدم', 'text-bg-secondary'],
            'store_owner' => ['صاحب نشاط', 'text-bg-primary'],
        ];
        $statuses = [
            'active' => ['فعّال', 'text-bg-success'],
            'suspended' => ['موقوف', 'text-bg-danger'],
            'deleted' => ['محذوف', 'text-bg-secondary'],
        ];
        $langs = ['ar' => 'العربية', 'en' => 'English'];
    ?>

    <!-- Users table -->
    <div class="row row-tight" style="margin-top: 16px;">
    <div class="col-md-12">
        <section class="m-card" aria-labelledby="users-title">
    <header class="m-card__header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <div>
            <h2 class="m-card__title" id="users-title">Users</h2>
            <p class="m-card__subtitle">Users who registered on Gaza Market</p>
        </div>
    </header>
        <div class="table-responsive">
            <table class="table table-borderless table-data3">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Area</th>
                        <th>Language / Theme</th>
                        <th>Registered / Last Login</th>
                        <th>Setting</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $role = $roles[$user->user_role] ?? [$user->user_role, 'text-bg-secondary'];
                        $status = $statuses[$user->user_status] ?? [$user->user_status, 'text-bg-secondary'];
                    ?>
                    <tr>
                        <td><?php echo e($users->firstItem() + $loop->index); ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="position-relative d-inline-flex align-items-center justify-content-center flex-shrink-0 overflow-hidden rounded-circle text-bg-secondary fw-semibold" style="width: 36px; height: 36px;">
                                    <?php echo e(mb_substr($user->user_full_name, 0, 1)); ?>

                                    <?php if($user->avatar_url): ?>
                                        <img src="<?php echo e($user->avatar_url); ?>" alt="" width="36" height="36" loading="lazy" class="position-absolute top-0 start-0 w-100 h-100" style="object-fit: cover;" onerror="this.remove()">
                                    <?php endif; ?>
                                </span>
                                <div>
                                    <span class="d-block fw-semibold"><?php echo e($user->user_full_name); ?></span>
                                    <small class="d-block text-muted" dir="ltr"><?php echo e($user->user_phone); ?></small>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge <?php echo e($role[1]); ?>"><?php echo e($role[0]); ?></span></td>
                        <td><span class="badge <?php echo e($status[1]); ?>"><?php echo e($status[0]); ?></span></td>
                        <td><?php echo e($user->area?->area_name_ar ?? '-'); ?></td>
                        <td>
                            <span class="d-block"><?php echo e($langs[$user->user_preferred_lang] ?? '-'); ?></span>
                            <small class="d-flex align-items-center gap-1 text-muted">
                            <?php if($user->user_theme === 'dark'): ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-moon preview-icon"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>داكن
                            <?php else: ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sun preview-icon"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>فاتح
                            <?php endif; ?>
                            </small>
                        </td>
                        <td>
                            <span class="d-block"><small class="text-muted">Registered:</small> <?php echo e($user->user_created_at?->timezone(config('app.display_timezone'))->format('Y-m-d') ?? '-'); ?></span>
                            <span class="d-block"><small class="text-muted">Last login:</small> <?php echo e($user->user_last_login_at?->timezone(config('app.display_timezone'))->format('Y-m-d') ?? '-'); ?></span>
                        </td>
                        <td>
                            <div class="btn-group" role="group" aria-label="Toolbar group">
                            <?php if($user->user_status === 'active'): ?>
                                <button type="button" onclick="performToggle(<?php echo e($user->id); ?>, 'block')" class="btn btn-warning" title="Block"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-ban preview-icon"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg></button>
                            <?php elseif($user->user_status === 'suspended'): ?>
                                <button type="button" onclick="performToggle(<?php echo e($user->id); ?>, 'unblock')" class="btn btn-success" title="Unblock"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-check-big preview-icon"><path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/></svg></button>
                            <?php endif; ?>
                                <button type="button" onclick="performDestroy(<?php echo e($user->id); ?>, this)" class="btn btn-danger" title="Delete"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash preview-icon"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="8" class="text-center">No users yet</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php echo e($users->links()); ?>

</div>
</div>

<?php $__env->stopSection(); ?>


<?php $__env->startSection('scripts'); ?>
<script>
function performDestroy(id, reference){
    let url = '/cms/admins/user/'+id;
    confirmDestroy(url, reference);
}

function performToggle(id, action){
    let isBlock = action === 'block';

    Swal.fire({
        title: isBlock ? 'هل تريد حظر هذا المستخدم ؟' : 'هل تريد فك الحظر عن هذا المستخدم ؟',
        text: isBlock ? 'سيتم إخراجه من الموقع ولن يستطيع تسجيل الدخول' : 'سيتمكن من تسجيل الدخول مرة أخرى',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'نعم',
        cancelButtonText: 'لا',
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        axios.post('/cms/admins/user_toggle_status/' + id)
            .then(function (response) {
                showMessage(response.data);
                setTimeout(function () { location.reload(); }, 1200);
            })
            .catch(function (error) {
                showMessage(error.response ? error.response.data : { icon: 'error', title: 'حدث خطأ غير متوقع' });
            });
    });
}
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('cms.parent', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\gaza-market\resources\views/cms/user/index.blade.php ENDPATH**/ ?>