

<?php $__env->startSection('title', 'Index'); ?>

<?php $__env->startSection('styles'); ?>

<?php $__env->stopSection(); ?>


<?php $__env->startSection('content'); ?>

<section class="region-selector-section">
  <div class="region-selector-wrapper">
    <h1 data-i18n="regionSelectorTitle">Where do you live?</h1>
    <p class="region-selector-title" data-i18n="regionSelectorSubtitle">Select your region to view prices near you</p>
    <div class="region-accordion">
      <?php $__currentLoopData = $regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
          $regionAreas = $areas->where('region_id', $region->id);
          $dot = ['north', 'central', 'south'][$loop->index % 3];
        ?>
        <div class="region-card" data-region="<?php echo e($region->region_slug); ?>">
          <div class="region-card-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down-icon lucide-chevron-down region-chevron"><path d="m6 9 6 6 6-6"/></svg>
            <div class="region-card-header-right">
              <span class="region-name fs-16 fw-700"
                    data-name-ar="<?php echo e($region->region_name_ar); ?>"
                    data-name-en="<?php echo e($region->region_name_en); ?>"><?php echo e($region->region_name_ar); ?></span>
              <span class="region-dot dot-<?php echo e($dot); ?>"></span>
            </div>
            <span class="region-count fs-13 border-rad-20">
              <span><?php echo e($regionAreas->count()); ?></span>
              <span data-i18n="regionCountUnit">مناطق</span>
            </span>
          </div>
          <div class="region-card-body">
            <div class="region-tags">
              <?php $__currentLoopData = $regionAreas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $area): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <span class="region-tag"
                      data-area="<?php echo e($area->area_name_en); ?>"
                      data-name-ar="<?php echo e($area->area_name_ar); ?>"
                      data-name-en="<?php echo e($area->area_name_en); ?>"
                      data-region-ar="<?php echo e($region->region_name_ar); ?>"
                      data-region-en="<?php echo e($region->region_name_en); ?>"><?php echo e($area->area_name_ar); ?></span>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
          </div>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    <div class="selected-region-display" id="selectedRegionDisplay">
        <svg class="selected-region-icon" width="16" height="16" aria-hidden="true" focusable="false"><use href="#gm-i-map-pin"/></svg>
      <div class="selected-region-info">
        <span class="selected-region-label fs-12 fw-600" data-i18n="regionSelectionLabel">Region selection</span>
        <span class="selected-region-value" id="selectedRegionValue" data-i18n="regionSelectionDefault">—</span>
      </div>
    </div>

    <a href="<?php echo e(route('gazamarket.home')); ?>" class="start-btn" id="startBtn" data-i18n="regionStartBtn">
      Start browsing prices in your area
    </a>
    <span class="region-error-msg" id="regionErrorMsg" role="alert" aria-live="polite">
  Please select a region before continuing
</span>
  </div>
</section>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>

<?php $__env->stopSection(); ?>
<?php echo $__env->make('gazamarket.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\gaza-market\resources\views/gazamarket/index.blade.php ENDPATH**/ ?>