@extends('cms.parent')

@section('title', 'create category group')

@section('styles')

@endsection


@section('content')
    <div class="card">
    <div class="card-header">
        <strong>Adding category group details.</strong>
    </div>
    <div class="card-body card-body">
        <form id="create_form">
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="category_group_name_en" class="form-control-label">Category Group Name (English)</label>
                    <input type="text" id="category_group_name_en" name="category_group_name_en" maxlength="45" placeholder="Enter Category Group Name in English.." class="form-control">
                    <span class="help-block">Please enter the category group name in English</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="category_group_name_ar" class="form-control-label">Category Group Name (Arabic)</label>
                    <input type="text" id="category_group_name_ar" name="category_group_name_ar" maxlength="45" placeholder="Enter Category Group Name in Arabic.." class="form-control">
                    <span class="help-block">Please enter the category group name in Arabic</span>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="category_group_sort_order" class="form-control-label">Sort Order</label>
                    <input type="number" id="category_group_sort_order" name="category_group_sort_order" min="0" max="127" placeholder="Enter Sort Order.." class="form-control">
                    <span class="help-block">Please enter the sort order (0 - 127)</span>
                </div>
            </div>
        </form>
    </div>
    <div class="card-footer">
        <button type="button" onclick="performStore()" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus preview-icon"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Create
        </button>
        <a href="{{ route('category_group.index') }}" type="button" class="btn btn-secondary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-table-of-contents preview-icon"><path d="M16 5H3"/><path d="M16 12H3"/><path d="M16 19H3"/><path d="M21 5h.01"/><path d="M21 12h.01"/><path d="M21 19h.01"/></svg>
            Index
        </a>

    </div>
</div>
@endsection


@section('scripts')
<script>
    function performStore(){
    let formData = new FormData();
    formData.append('category_group_name_en', document.getElementById('category_group_name_en').value);
    formData.append('category_group_name_ar', document.getElementById('category_group_name_ar').value);
    formData.append('category_group_sort_order', document.getElementById('category_group_sort_order').value);
    store('/cms/admins/category_group' , formData);
    }
    </script>
@endsection