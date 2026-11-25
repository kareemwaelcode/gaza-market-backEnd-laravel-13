<?php $__env->startSection('title', 'index place'); ?>

<?php $__env->startSection('styles'); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $badge = [
        'pending' => 'bg-warning text-dark',
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
    ];
    $planLabels = [
        'paid' => 'Subscribed',
        'free' => 'Free / expired',
        'expiring' => "Expiring within {$expiringDays} days",
    ];
?>
<div class="row row-tight" style="margin-top: 16px;">
    <div class="col-md-12">
        <section class="m-card" aria-labelledby="places-title">
            <header class="m-card__header" style="width: 100%;">
                <h2 class="m-card__title" id="places-title">Places</h2>
                <p class="m-card__subtitle">Review registered places. Pending places are listed first.</p>
            </header>

            <?php if(! empty($ignoredFilters)): ?>
                <div class="alert alert-warning" role="alert" style="margin: 0 16px 16px;">
                    Invalid filter value ignored (<?php echo e(implode(', ', $ignoredFilters)); ?>). Showing results without it.
                </div>
            <?php endif; ?>

            <form method="GET" action="<?php echo e(route('place.index')); ?>" class="row g-2 mb-3" style="padding: 0 16px;">
                <div class="col-lg-2">
                    <select name="status" class="form-control">
                        <option value="">All statuses</option>
                        <?php $__currentLoopData = \App\Models\Place::STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($status); ?>" <?php if(($filters['status'] ?? null) === $status): echo 'selected'; endif; ?>><?php echo e(ucfirst($status)); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="col-lg-2">
                    <select name="type" class="form-control">
                        <option value="">All types</option>
                        <?php $__currentLoopData = \App\Models\Place::TYPES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($type); ?>" <?php if(($filters['type'] ?? null) === $type): echo 'selected'; endif; ?>><?php echo e($type); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="col-lg-3">
                    <select name="plan" class="form-control">
                        <option value="">All plans</option>
                        <?php $__currentLoopData = $planFilters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $plan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($plan); ?>" <?php if(($filters['plan'] ?? null) === $plan): echo 'selected'; endif; ?>><?php echo e($planLabels[$plan] ?? $plan); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="col-lg-3">
                    <select name="area" class="form-control">
                        <option value="">All areas</option>
                        <?php $__currentLoopData = $areas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $area): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($area->id); ?>" <?php if((int) ($filters['area'] ?? 0) === $area->id): echo 'selected'; endif; ?>><?php echo e($area->area_name_ar); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="col-lg-2">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="<?php echo e(route('place.index')); ?>" class="btn btn-secondary btn-sm">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-borderless table-data3">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Area</th>
                            <th>Owner</th>
                            <th>Status</th>
                            <th>Plan</th>
                            <th>Created</th>
                            <th>Setting</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $places; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $place): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td><?php echo e($place->id); ?></td>
                            <td><?php echo e($place->place_name); ?></td>
                            <td><?php echo e($place->place_type); ?><?php if($place->storeCategory): ?><br><small><?php echo e($place->storeCategory->store_category_name_ar); ?></small><?php endif; ?></td>
                            <td><?php echo e($place->area?->area_name_ar); ?></td>
                            <td><?php echo e($place->user?->user_full_name); ?><br><small><?php echo e($place->user?->user_phone); ?></small></td>
                            <td><span class="badge <?php echo e($badge[$place->place_status] ?? 'bg-secondary'); ?>"><?php echo e(ucfirst($place->place_status)); ?></span></td>
                            <td>
                                <?php if($place->place_plan === 'paid'): ?>
                                    <span class="badge bg-success">Paid</span>
                                    <br><small><?php echo e($place->place_plan_expires_at ? 'until '.$place->place_plan_expires_at->timezone(config('app.display_timezone'))->format('Y-m-d') : 'no expiry'); ?></small>
                                    <?php if($place->place_plan_expires_at): ?>
                                        <br><small class="<?php echo e($place->place_plan_expires_at->lte(now()->addDays($expiringDays)) ? 'text-danger' : 'text-muted'); ?>"><?php echo e(max(0, (int) ceil(now()->diffInSeconds($place->place_plan_expires_at, false) / 86400))); ?> day(s) left</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Free</span>
                                    <?php if($place->place_plan_expires_at): ?>
                                        <br><small class="text-muted">expired <?php echo e($place->place_plan_expires_at->timezone(config('app.display_timezone'))->format('Y-m-d')); ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($place->created_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i')); ?></td>
                            <td>
                                <div class="btn-group" role="group" aria-label="Toolbar group">
                                    <a href="<?php echo e(route('place.show', $place->id)); ?>" class="btn btn-info btn-sm">View</a>
                                    <button type="button" onclick="performDestroy(<?php echo e($place->id); ?>, this)" class="btn btn-danger btn-sm">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="9" class="text-center">No places found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php echo e($places->links()); ?>

    </div>
</div>
<script>
function performDestroy(id, reference){
    let url = '/cms/admins/place/'+id;
    confirmDestroy(url, reference);
}
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('cms.parent', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\gaza-market\resources\views/cms/place/index.blade.php ENDPATH**/ ?>