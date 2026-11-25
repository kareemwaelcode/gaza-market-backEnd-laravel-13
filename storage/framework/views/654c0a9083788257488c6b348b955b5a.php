

<?php $__env->startSection('title', 'Index'); ?>

<?php $__env->startSection('styles'); ?>

<?php $__env->stopSection(); ?>


<?php $__env->startSection('content'); ?>

<section class="region-selector-section">
  <div class="region-selector-wrapper">
    <h1 data-i18n="regionSelectorTitle">Where do you live?</h1>
    <p class="region-selector-title" data-i18n="regionSelectorSubtitle">Select your region to view prices near you</p>
    <div class="region-accordion">
      <div class="region-card" data-region="north">
        <div class="region-card-header">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down-icon lucide-chevron-down region-chevron"><path d="m6 9 6 6 6-6"/></svg>
          <div class="region-card-header-right">
            <span class="region-name fs-16 fw-700" data-i18n="regionNorthGaza">North Gaza</span>
            <span class="region-dot dot-north"></span>
          </div>
          <span class="region-count fs-13 border-rad-20" data-i18n="regionCountNorth">8 regions</span>
        </div>
        <div class="region-card-body">
          <div class="region-tags">
            <span class="region-tag" data-area="Alrimal"       data-region-name="regionNorthGaza" data-i18n="Alrimal">Al-Rimal</span>
            <span class="region-tag" data-area="Alshaati"      data-region-name="regionNorthGaza" data-i18n="Alshaati">Al-Shaati</span>
            <span class="region-tag" data-area="Sheikh Radwan" data-region-name="regionNorthGaza" data-i18n="SheikhRadwan">Sheikh Radwan</span>
            <span class="region-tag" data-area="Saftawi"       data-region-name="regionNorthGaza" data-i18n="Saftawi">Saftawi</span>
            <span class="region-tag" data-area="Beit Hanoun"   data-region-name="regionNorthGaza" data-i18n="BeitHanoun">Beit Hanoun</span>
            <span class="region-tag" data-area="Beit Lahia"    data-region-name="regionNorthGaza" data-i18n="BeitLahia">Beit Lahia</span>
            <span class="region-tag" data-area="Tal Al-Hawa"   data-region-name="regionNorthGaza" data-i18n="TalHawa">Tal Al-Hawa</span>
            <span class="region-tag" data-area="Jabalia"       data-region-name="regionNorthGaza" data-i18n="Jabalia">Jabalia</span>
          </div>
        </div>
      </div>
      <div class="region-card" data-region="central">
        <div class="region-card-header">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down-icon lucide-chevron-down region-chevron"><path d="m6 9 6 6 6-6"/></svg>
          <div class="region-card-header-right">
            <span class="region-name fs-16 fw-700" data-i18n="regionCentralGaza">Central Gaza</span>
            <span class="region-dot dot-central"></span>
          </div>
          <span class="region-count fs-13 border-rad-20" data-i18n="regionCountCentral">5 regions</span>
        </div>
        <div class="region-card-body">
          <div class="region-tags">
            <span class="region-tag" data-area="Nuseirat"      data-region-name="regionCentralGaza" data-i18n="Nuseirat">Nuseirat</span>
            <span class="region-tag" data-area="Breij"         data-region-name="regionCentralGaza" data-i18n="Breij">Breij</span>
            <span class="region-tag" data-area="Maghazi"       data-region-name="regionCentralGaza" data-i18n="Maghazi">Maghazi</span>
            <span class="region-tag" data-area="Zawayda"       data-region-name="regionCentralGaza" data-i18n="Zawayda">Zawayda</span>
            <span class="region-tag" data-area="Deir al-Balah" data-region-name="regionCentralGaza" data-i18n="DeirBalah">Deir al-Balah</span>
          </div>
        </div>
      </div>
      <div class="region-card" data-region="south">
        <div class="region-card-header">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down-icon lucide-chevron-down region-chevron"><path d="m6 9 6 6 6-6"/></svg>
          <div class="region-card-header-right">
            <span class="region-name fs-16 fw-700" data-i18n="regionSouthGaza">South Gaza</span>
            <span class="region-dot dot-south"></span>
          </div>
          <span class="region-count fs-13 border-rad-20" data-i18n="regionCountSouth">4 regions</span>
        </div>
        <div class="region-card-body">
          <div class="region-tags">
            <span class="region-tag" data-area="Khan Yunis"         data-region-name="regionSouthGaza" data-i18n="KhanYunis">Khan Yunis</span>
            <span class="region-tag" data-area="Rafah"              data-region-name="regionSouthGaza" data-i18n="Rafah">Rafah</span>
            <span class="region-tag" data-area="Mawasi al-Qarara"  data-region-name="regionSouthGaza" data-i18n="MawasiQarara">Mawasi al-Qarara</span>
            <span class="region-tag" data-area="Mawasi Khan Yunis" data-region-name="regionSouthGaza" data-i18n="MawasiKhanYunis">Mawasi Khan Yunis</span>
          </div>
        </div>
      </div>
    </div>
    <div class="selected-region-display" id="selectedRegionDisplay">
        <svg class="selected-region-icon" width="16" height="16" aria-hidden="true" focusable="false"><use href="#gm-i-map-pin"/></svg>
      <div class="selected-region-info">
        <span class="selected-region-label fs-12 fw-600" data-i18n="regionSelectionLabel">Region selection</span>
        <span class="selected-region-value" id="selectedRegionValue" data-i18n="regionSelectionDefault">—</span>
      </div>
    </div>

    <a href="home.html" class="start-btn" id="startBtn" data-i18n="regionStartBtn">
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