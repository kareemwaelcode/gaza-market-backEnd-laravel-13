

<?php $__env->startSection('title', 'index hero section'); ?>

<?php $__env->startSection('styles'); ?>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('content'); ?>
    <!-- Hero Section table -->
    <div class="row row-tight" style="margin-top: 16px;">
    <div class="col-md-12">
        <section class="m-card" aria-labelledby="hero-sections-title">
    <header class="m-card__header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <div>
            <h2 class="m-card__title" id="hero-sections-title">Hero Section</h2>
            <p class="m-card__subtitle">Texts of the home page hero section</p>
        </div>
    </header>
        <div class="table-responsive">
            <table class="table table-borderless table-data3">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Eyebrow (EN)</th>
                        <th>Eyebrow (AR)</th>
                        <th>Title (EN)</th>
                        <th>Title (AR)</th>
                        <th>Location (EN)</th>
                        <th>Location (AR)</th>
                        <th>Setting</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $heroSections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $heroSection): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td><?php echo e($loop->iteration); ?></td>
                        <td><?php echo e($heroSection->hero_section_eyebrow_en); ?></td>
                        <td><?php echo e($heroSection->hero_section_eyebrow_ar); ?></td>
                        <td><?php echo e($heroSection->hero_section_title_en); ?></td>
                        <td><?php echo e($heroSection->hero_section_title_ar); ?></td>
                        <td><?php echo e($heroSection->hero_section_location_en); ?></td>
                        <td><?php echo e($heroSection->hero_section_location_ar); ?></td>
                        <td>
                            <div class="btn-group" role="group" aria-label="Toolbar group">
                            <a href="<?php echo e(route('hero_section.edit' ,$heroSection->id )); ?>">
                                <button type="button" class="btn btn-info"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-pen preview-icon"><path d="M2 21a8 8 0 0 1 10.821-7.487"/><path d="M21.378 16.626a1 1 0 0 0-3.004-3.004l-4.01 4.012a2 2 0 0 0-.506.854l-.837 2.87a.5.5 0 0 0 .62.62l2.87-.837a2 2 0 0 0 .854-.506z"/><circle cx="10" cy="8" r="5"/></svg></button>
                            </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="8">No hero section yet.</td>
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
<?php $__env->stopSection(); ?>
<?php echo $__env->make('cms.parent', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\gaza-market\resources\views/cms/hero_section/index.blade.php ENDPATH**/ ?>