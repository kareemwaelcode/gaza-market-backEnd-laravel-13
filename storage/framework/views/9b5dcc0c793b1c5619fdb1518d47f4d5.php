

<?php $__env->startSection('title', 'place visits'); ?>

<?php $__env->startSection('styles'); ?>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('content'); ?>
    <div class="row row-tight" style="margin-top: 16px;">
    <div class="col-md-12">
        <section class="m-card" aria-labelledby="place-visits-title">
    <header class="m-card__header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <div>
            <h2 class="m-card__title" id="place-visits-title">Place Visits</h2>
            <p class="m-card__subtitle">Places ranked by visits (approximate count)</p>
        </div>
        <form method="GET" action="<?php echo e(route('place_visit.index')); ?>">
            <select name="period" class="form-control" onchange="this.form.submit()">
                <option value="today" <?php if($period === 'today'): echo 'selected'; endif; ?>>Today</option>
                <option value="7" <?php if($period === '7'): echo 'selected'; endif; ?>>Last 7 days</option>
                <option value="30" <?php if($period === '30'): echo 'selected'; endif; ?>>Last 30 days</option>
                <option value="all" <?php if($period === 'all'): echo 'selected'; endif; ?>>All time</option>
            </select>
        </form>
    </header>
        <div class="table-responsive">
            <table class="table table-borderless table-data3">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Place</th>
                        <th>Type</th>
                        <th>Area</th>
                        <th>Visits</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td><?php echo e($rows->firstItem() + $loop->index); ?></td>
                        <td>
                            <?php if($row->place): ?>
                                <a href="<?php echo e(route('place.show', $row->place_id)); ?>"><?php echo e($row->place->place_name); ?></a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo e($row->place?->place_type); ?></td>
                        <td><?php echo e($row->place?->area?->area_name_ar); ?></td>
                        <td><?php echo e(number_format((int) $row->total)); ?></td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="5">No visits in this period.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php echo e($rows->links()); ?>

</div>
</div>

<?php $__env->stopSection(); ?>
<?php echo $__env->make('cms.parent', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\gaza-market\resources\views/cms/place_visit/index.blade.php ENDPATH**/ ?>