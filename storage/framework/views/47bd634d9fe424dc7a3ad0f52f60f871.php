<?php $__env->startSection('title', 'edit hero section'); ?>

<?php $__env->startSection('styles'); ?>

<?php $__env->stopSection(); ?>


<?php $__env->startSection('content'); ?>
    <div class="card">
    <div class="card-header">
        <strong>Editing hero section details.</strong>
    </div>
    <div class="card-body card-body">
        <form id="create_form">
            <?php $__currentLoopData = $fields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => [$label, $max]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="hero_section_<?php echo e($field); ?>_en" class="form-control-label"><?php echo e($label); ?> (English)</label>
                    <?php if($max > 200): ?>
                    <textarea id="hero_section_<?php echo e($field); ?>_en" name="hero_section_<?php echo e($field); ?>_en" maxlength="<?php echo e($max); ?>" rows="2" class="form-control"><?php echo e($heroSection->{'hero_section_'.$field.'_en'}); ?></textarea>
                    <?php else: ?>
                    <input type="text" id="hero_section_<?php echo e($field); ?>_en" name="hero_section_<?php echo e($field); ?>_en" maxlength="<?php echo e($max); ?>" value="<?php echo e($heroSection->{'hero_section_'.$field.'_en'}); ?>" class="form-control">
                    <?php endif; ?>
                    <span class="help-block">Max <?php echo e($max); ?> characters</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="hero_section_<?php echo e($field); ?>_ar" class="form-control-label"><?php echo e($label); ?> (Arabic)</label>
                    <?php if($max > 200): ?>
                    <textarea id="hero_section_<?php echo e($field); ?>_ar" name="hero_section_<?php echo e($field); ?>_ar" maxlength="<?php echo e($max); ?>" rows="2" class="form-control"><?php echo e($heroSection->{'hero_section_'.$field.'_ar'}); ?></textarea>
                    <?php else: ?>
                    <input type="text" id="hero_section_<?php echo e($field); ?>_ar" name="hero_section_<?php echo e($field); ?>_ar" maxlength="<?php echo e($max); ?>" value="<?php echo e($heroSection->{'hero_section_'.$field.'_ar'}); ?>" class="form-control">
                    <?php endif; ?>
                    <span class="help-block">Max <?php echo e($max); ?> characters</span>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </form>
    </div>
    <div class="card-footer">
        <button type="button" onclick="performUpdate(<?php echo e($heroSection->id); ?>)" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus preview-icon"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Update
        </button>
        <a href="<?php echo e(route('hero_section.index')); ?>" type="button" class="btn btn-secondary btn-sm">
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
        document.querySelectorAll('#create_form [name]').forEach(function(el){
            formData.append(el.name, el.value);
        });
        storeRoute('/cms/admins/hero_section_update/' + id, formData);
    }
    </script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('cms.parent', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\gaza-market\resources\views/cms/hero_section/edit.blade.php ENDPATH**/ ?>