@extends('cms.parent')

@section('title', 'edit unit')

@section('styles')

@endsection


@section('content')
    <div class="card">
    <div class="card-header">
        <strong>Editing unit details.</strong>
    </div>
    <div class="card-body card-body">
        <form id="create_form">
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="unit_name_en" class="form-control-label">Unit Name (English)</label>
                    <input type="text" id="unit_name_en" name="unit_name_en" maxlength="45"
                    value="{{ $unit->unit_name_en }}" placeholder="Enter Unit Name in English.." class="form-control">
                    <span class="help-block">Please enter the unit name in English</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="unit_name_ar" class="form-control-label">Unit Name (Arabic)</label>
                    <input type="text" id="unit_name_ar" name="unit_name_ar" maxlength="45"
                    value="{{ $unit->unit_name_ar }}" placeholder="Enter Unit Name in Arabic.." class="form-control" dir="rtl">
                    <span class="help-block">Please enter the unit name in Arabic</span>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="unit_is_active" class="form-control-label">Status</label>
                    <select id="unit_is_active" name="unit_is_active" class="form-control">
                        <option value="1" @selected($unit->unit_is_active)>Active</option>
                        <option value="0" @selected(! $unit->unit_is_active)>Inactive</option>
                    </select>
                    <span class="help-block">Inactive units will not appear when adding a product</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="unit_sort_order" class="form-control-label">Sort Order</label>
                    <input type="number" id="unit_sort_order" name="unit_sort_order" min="0" max="127"
                    value="{{ $unit->unit_sort_order }}" placeholder="Enter Sort Order.." class="form-control">
                    <span class="help-block">Please enter the sort order (0 - 127)</span>
                </div>
            </div>
        </form>
    </div>
    <div class="card-footer">
        <button type="button" onclick="performUpdate({{ $unit->id }})" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus preview-icon"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Update
        </button>
        <a href="{{ route('unit.index') }}" type="button" class="btn btn-secondary btn-sm">
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
        formData.append('unit_name_en', document.getElementById('unit_name_en').value);
        formData.append('unit_name_ar', document.getElementById('unit_name_ar').value);
        formData.append('unit_is_active', document.getElementById('unit_is_active').value);
        formData.append('unit_sort_order', document.getElementById('unit_sort_order').value);
        storeRoute('/cms/admins/unit_update/' + id, formData);
    }
    </script>
@endsection