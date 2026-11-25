<?php $__env->startSection('title', 'index point transaction'); ?>

<?php $__env->startSection('styles'); ?>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('content'); ?>
    <!-- Point transactions table -->
    <div class="row row-tight" style="margin-top: 16px;">
    <div class="col-md-12">
        <section class="m-card" aria-labelledby="point-transactions-title">
    <header class="m-card__header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <div>
            <h2 class="m-card__title" id="point-transactions-title">Point Transactions</h2>
            <p class="m-card__subtitle">History of every time a user earned or lost points</p>
        </div>
    </header>
        <div class="table-responsive">
            <table class="table table-borderless table-data3">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Phone</th>
                        <th>Rule</th>
                        <th>Points</th>
                        <th>Source</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $pointTransactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pointTransaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($pointTransactions->firstItem() + $loop->index); ?></td>
                        <td><?php echo e($pointTransaction->user?->user_full_name); ?></td>
                        <td><?php echo e($pointTransaction->user?->user_phone); ?></td>
                        <td><?php echo e($pointTransaction->pointRule?->point_rule_name_en); ?></td>
                        <td><?php echo e($pointTransaction->point_transaction_points > 0 ? '+' : ''); ?><?php echo e($pointTransaction->point_transaction_points); ?></td>
                        <td><?php echo e($pointTransaction->point_transaction_source_type ? $pointTransaction->point_transaction_source_type.' #'.$pointTransaction->point_transaction_source_id : '-'); ?></td>
                        <td><?php echo e($pointTransaction->point_transaction_created_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i')); ?></td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php echo e($pointTransactions->links()); ?>

</div>
</div>

<?php $__env->stopSection(); ?>
<?php echo $__env->make('cms.parent', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\gaza-market\resources\views/cms/point_transaction/index.blade.php ENDPATH**/ ?>