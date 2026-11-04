@extends('cms.parent')

@section('title', 'edit navigation item')

@section('styles')

@endsection


@section('content')
    <div class="card">
    <div class="card-header">
        <strong>Editing navigation item details.</strong>
    </div>
    <div class="card-body card-body">
        <form id="create_form">
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="navigation_item_name_en" class="form-control-label">Name (English)</label>
                    <input type="text" id="navigation_item_name_en" name="navigation_item_name_en" maxlength="45" value="{{ $navigationItem->navigation_item_name_en }}" placeholder="Enter Name in English.." class="form-control">
                    <span class="help-block">Please enter the name in English</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="navigation_item_name_ar" class="form-control-label">Name (Arabic)</label>
                    <input type="text" id="navigation_item_name_ar" name="navigation_item_name_ar" maxlength="45" value="{{ $navigationItem->navigation_item_name_ar }}" placeholder="Enter Name in Arabic.." class="form-control">
                    <span class="help-block">Please enter the name in Arabic</span>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="navigation_item_icon" class="form-control-label">Icon</label>
                    <input type="text" id="navigation_item_icon" name="navigation_item_icon" maxlength="45" value="{{ $navigationItem->navigation_item_icon }}" placeholder="Enter Icon Name.." class="form-control">
                    <span class="help-block">Lucide icon name, e.g. house, store, shopping-cart</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="navigation_item_url" class="form-control-label">URL</label>
                    <input type="text" id="navigation_item_url" name="navigation_item_url" maxlength="255" value="{{ $navigationItem->navigation_item_url }}" placeholder="Enter URL.." class="form-control">
                    <span class="help-block">Page address, e.g. places.html or # when there is no page yet</span>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="navigation_item_sort_order" class="form-control-label">Sort Order</label>
                    <input type="number" id="navigation_item_sort_order" name="navigation_item_sort_order" min="0" max="255" value="{{ $navigationItem->navigation_item_sort_order }}" placeholder="Enter Sort Order.." class="form-control">
                    <span class="help-block">Please enter the sort order (0 - 255)</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="navigation_item_is_active" class="form-control-label">Status</label>
                    <select id="navigation_item_is_active" name="navigation_item_is_active" class="form-control">
                        <option value="1" @selected($navigationItem->navigation_item_is_active)>Yes</option>
                        <option value="0" @selected(! $navigationItem->navigation_item_is_active)>No</option>
                    </select>
                    <span class="help-block">Inactive items will not appear in the site navigation</span>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="navigation_item_is_mobile" class="form-control-label">Show on Mobile</label>
                    <select id="navigation_item_is_mobile" name="navigation_item_is_mobile" class="form-control">
                        <option value="1" @selected($navigationItem->navigation_item_is_mobile)>Yes</option>
                        <option value="0" @selected(! $navigationItem->navigation_item_is_mobile)>No</option>
                    </select>
                    <span class="help-block">Show in the mobile bottom navigation</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="navigation_item_is_desktop" class="form-control-label">Show on Desktop</label>
                    <select id="navigation_item_is_desktop" name="navigation_item_is_desktop" class="form-control">
                        <option value="1" @selected($navigationItem->navigation_item_is_desktop)>Yes</option>
                        <option value="0" @selected(! $navigationItem->navigation_item_is_desktop)>No</option>
                    </select>
                    <span class="help-block">Show in the desktop sidebar</span>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="navigation_item_is_coming_soon" class="form-control-label">Coming Soon Badge</label>
                    <select id="navigation_item_is_coming_soon" name="navigation_item_is_coming_soon" class="form-control">
                        <option value="1" @selected($navigationItem->navigation_item_is_coming_soon)>Yes</option>
                        <option value="0" @selected(! $navigationItem->navigation_item_is_coming_soon)>No</option>
                    </select>
                    <span class="help-block">Show the coming soon badge next to the item</span>
                </div>
            </div>
        </form>
    </div>
    <div class="card-footer">
        <button type="button" onclick="performUpdate({{ $navigationItem->id }})" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus preview-icon"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Update
        </button>
        <a href="{{ route('navigation_item.index') }}" type="button" class="btn btn-secondary btn-sm">
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
        document.querySelectorAll('#create_form [name]').forEach(function(el){
            formData.append(el.name, el.value);
        });
        storeRoute('/cms/admins/navigation_item_update/' + id, formData);
    }
    </script>
@endsection