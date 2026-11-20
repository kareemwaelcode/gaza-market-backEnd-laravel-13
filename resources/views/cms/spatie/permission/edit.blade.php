@extends('cms.parent')

@section('title', 'edit region')

@section('styles')

@endsection


@section('content')
    <div class="card">
    <div class="card-header">
        <strong>Editing region details.</strong>
    </div>
    <div class="card-body card-body">
        <form id="create_form">
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="region_name_en" class="form-control-label">Region Name (English)</label>
                    <input type="text" id="region_name_en" name="region_name_en" maxlength="45"
                    value="{{ $region->region_name_en }}" placeholder="Enter Region Name in English.." class="form-control">
                    <span class="help-block">Please enter the region name in English</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="region_name_ar" class="form-control-label">Region Name (Arabic)</label>
                    <input type="text" id="region_name_ar" name="region_name_ar" maxlength="45"
                    value="{{ $region->region_name_ar }}" placeholder="Enter Region Name in Arabic.." class="form-control">
                    <span class="help-block">Please enter the region name in Arabic</span>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="region_sort_order" class="form-control-label">Sort Order</label>
                    <input type="number" id="region_sort_order" name="region_sort_order" min="0" max="127"
                    value="{{ $region->region_sort_order }}" placeholder="Enter Sort Order.." class="form-control">
                    <span class="help-block">Please enter the sort order (0 - 127)</span>
                </div>
            </div>
        </form>
    </div>
    <div class="card-footer">
        <button type="button" onclick="performUpdate({{ $region->id }})" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus preview-icon"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Update
        </button>
        <a href="{{ route('region.index') }}" type="button" class="btn btn-secondary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-table-of-contents preview-icon"><path d="M16 5H3"/><path d="M16 12H3"/><path d="M16 19H3"/><path d="M21 5h.01"/><path d="M21 12h.01"/><path d="M21 19h.01"/></svg>
            Index
        </a>

    </div>
</div>
@endsection


@section('scripts')
<script>
    function performUpdate(id){
        let formData = new FormData();
        formData.append('region_name_en', document.getElementById('region_name_en').value);
        formData.append('region_name_ar', document.getElementById('region_name_ar').value);
        formData.append('region_sort_order', document.getElementById('region_sort_order').value);
        storeRoute('/cms/admins/region_update/' + id, formData);
    }
    </script>
@endsection