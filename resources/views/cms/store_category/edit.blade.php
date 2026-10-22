@extends('cms.parent')

@section('title', 'edit store category')

@section('styles')

@endsection


@section('content')
    <div class="card">
    <div class="card-header">
        <strong>Editing store category details.</strong>
    </div>
    <div class="card-body card-body">
        <form id="create_form">
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="store_category_variant_key" class="form-control-label">Variant Key</label>
                    <select id="store_category_variant_key" name="store_category_variant_key" class="form-control">
                        <option value="">Select Variant Key</option>
                        @foreach ($store_category_variants as $store_category_variant)
                            <option value="{{ $store_category_variant->value }}" @selected($store_category->store_category_variant_key === $store_category_variant->value)>{{ $store_category_variant->value }}</option>
                        @endforeach
                    </select>
                    <span class="help-block">Selects which dashboard configuration this store category uses (it must match a variant in the dashboard store type config)</span>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="store_category_name_en" class="form-control-label">Store Category Name (English)</label>
                    <input type="text" id="store_category_name_en" name="store_category_name_en" maxlength="45"
                    value="{{ $store_category->store_category_name_en }}" placeholder="Enter Store Category Name in English.." class="form-control">
                    <span class="help-block">Please enter the store category name in English</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="store_category_name_ar" class="form-control-label">Store Category Name (Arabic)</label>
                    <input type="text" id="store_category_name_ar" name="store_category_name_ar" maxlength="45"
                    value="{{ $store_category->store_category_name_ar }}" placeholder="Enter Store Category Name in Arabic.." class="form-control">
                    <span class="help-block">Please enter the store category name in Arabic</span>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="store_category_group_id" class="form-control-label">Store Category Group</label>
                    <select id="store_category_group_id" name="store_category_group_id" class="form-control">
                        <option value="">Select Store Category Group</option>
                        @foreach ($store_category_groups as $store_category_group)
                            <option value="{{ $store_category_group->id }}" @selected($store_category->store_category_group_id == $store_category_group->id)>{{ $store_category_group->store_category_group_name_en }}</option>
                        @endforeach
                    </select>
                    <span class="help-block">Please select the group of this store category</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="store_category_is_active" class="form-control-label">Status</label>
                    <select id="store_category_is_active" name="store_category_is_active" class="form-control">
                        <option value="1" @selected($store_category->store_category_is_active)>Active</option>
                        <option value="0" @selected(! $store_category->store_category_is_active)>Inactive</option>
                    </select>
                    <span class="help-block">Inactive store categories will not be available when adding a place</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="store_category_sort_order" class="form-control-label">Sort Order</label>
                    <input type="number" id="store_category_sort_order" name="store_category_sort_order" min="0" max="32767"
                    value="{{ $store_category->store_category_sort_order }}" placeholder="Enter Sort Order.." class="form-control">
                    <span class="help-block">Please enter the sort order (0 - 32767)</span>
                </div>
            </div>
        </form>
    </div>
    <div class="card-footer">
        <button type="button" onclick="performUpdate({{ $store_category->id }})" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus preview-icon"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Update
        </button>
        <a href="{{ route('store_category.index') }}" type="button" class="btn btn-secondary btn-sm">
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
        formData.append('store_category_variant_key', document.getElementById('store_category_variant_key').value);
        formData.append('store_category_name_en', document.getElementById('store_category_name_en').value);
        formData.append('store_category_name_ar', document.getElementById('store_category_name_ar').value);
        formData.append('store_category_group_id', document.getElementById('store_category_group_id').value);
        formData.append('store_category_is_active', document.getElementById('store_category_is_active').value);
        formData.append('store_category_sort_order', document.getElementById('store_category_sort_order').value);
        storeRoute('/cms/admins/store_category_update/' + id, formData);
    }
    </script>
@endsection