@extends('cms.parent')

@section('title', 'edit category')

@section('styles')

@endsection


@section('content')
    <div class="card">
    <div class="card-header">
        <strong>Editing category details.</strong>
    </div>
    <div class="card-body card-body">
        <form id="create_form">
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="category_name_en" class="form-control-label">Category Name (English)</label>
                    <input type="text" id="category_name_en" name="category_name_en" maxlength="45"
                    value="{{ $category->category_name_en }}" placeholder="Enter Category Name in English.." class="form-control">
                    <span class="help-block">Please enter the category name in English</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="category_name_ar" class="form-control-label">Category Name (Arabic)</label>
                    <input type="text" id="category_name_ar" name="category_name_ar" maxlength="45"
                    value="{{ $category->category_name_ar }}" placeholder="Enter Category Name in Arabic.." class="form-control">
                    <span class="help-block">Please enter the category name in Arabic</span>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="category_group_id" class="form-control-label">Category Group</label>
                    <select id="category_group_id" name="category_group_id" class="form-control">
                        <option value="">Select Category Group</option>
                        @foreach ($category_groups as $category_group)
                            <option value="{{ $category_group->id }}" @selected($category->category_group_id == $category_group->id)>{{ $category_group->category_group_name_en }}</option>
                        @endforeach
                    </select>
                    <span class="help-block">Please select the group of this category</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="category_is_active" class="form-control-label">Status</label>
                    <select id="category_is_active" name="category_is_active" class="form-control">
                        <option value="1" @selected($category->category_is_active)>Active</option>
                        <option value="0" @selected(! $category->category_is_active)>Inactive</option>
                    </select>
                    <span class="help-block">Inactive categories will not appear when adding a product</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="category_sort_order" class="form-control-label">Sort Order</label>
                    <input type="number" id="category_sort_order" name="category_sort_order" min="0" max="32767"
                    value="{{ $category->category_sort_order }}" placeholder="Enter Sort Order.." class="form-control">
                    <span class="help-block">Please enter the sort order (0 - 32767)</span>
                </div>
            </div>
        </form>
    </div>
    <div class="card-footer">
        <button type="button" onclick="performUpdate({{ $category->id }})" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus preview-icon"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Update
        </button>
        <a href="{{ route('category.index') }}" type="button" class="btn btn-secondary btn-sm">
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
        formData.append('category_name_en', document.getElementById('category_name_en').value);
        formData.append('category_name_ar', document.getElementById('category_name_ar').value);
        formData.append('category_group_id', document.getElementById('category_group_id').value);
        formData.append('category_is_active', document.getElementById('category_is_active').value);
        formData.append('category_sort_order', document.getElementById('category_sort_order').value);
        storeRoute('/cms/admins/category_update/' + id, formData);
    }
    </script>
@endsectionw