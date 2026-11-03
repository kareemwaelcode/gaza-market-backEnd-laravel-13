@extends('cms.parent')

@section('title', 'edit point level')

@section('styles')

@endsection


@section('content')
    <div class="card">
    <div class="card-header">
        <strong>Editing point level details.</strong>
    </div>
    <div class="card-body card-body">
        <form id="create_form">
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="point_level_key" class="form-control-label">Level Key</label>
                    <input type="text" id="point_level_key" maxlength="45" value="{{ $pointLevel->point_level_key }}" class="form-control" disabled>
                    <span class="help-block">The key is fixed and cannot be changed</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="point_level_min_points" class="form-control-label">Min Points</label>
                    <input type="number" id="point_level_min_points" name="point_level_min_points" min="0" value="{{ $pointLevel->point_level_min_points }}" placeholder="Enter Min Points.." class="form-control">
                    <span class="help-block">Points needed to reach this level (the base level must be 0)</span>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="point_level_name_en" class="form-control-label">Level Name (English)</label>
                    <input type="text" id="point_level_name_en" name="point_level_name_en" maxlength="100" value="{{ $pointLevel->point_level_name_en }}" placeholder="Enter Level Name in English.." class="form-control">
                    <span class="help-block">Please enter the level name in English</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="point_level_name_ar" class="form-control-label">Level Name (Arabic)</label>
                    <input type="text" id="point_level_name_ar" name="point_level_name_ar" maxlength="100" value="{{ $pointLevel->point_level_name_ar }}" placeholder="Enter Level Name in Arabic.." class="form-control">
                    <span class="help-block">Please enter the level name in Arabic</span>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="point_level_sort_order" class="form-control-label">Sort Order</label>
                    <input type="number" id="point_level_sort_order" name="point_level_sort_order" min="0" max="127" value="{{ $pointLevel->point_level_sort_order }}" placeholder="Enter Sort Order.." class="form-control">
                    <span class="help-block">Please enter the sort order (0 - 127)</span>
                </div>
            </div>
        </form>
    </div>
    <div class="card-footer">
        <button type="button" onclick="performUpdate({{ $pointLevel->id }})" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus preview-icon"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Update
        </button>
        <a href="{{ route('point_level.index') }}" type="button" class="btn btn-secondary btn-sm">
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
        ['point_level_name_en','point_level_name_ar','point_level_min_points','point_level_sort_order'].forEach(function(f){
            formData.append(f, document.getElementById(f).value);
        });
        storeRoute('/cms/admins/point_level_update/' + id, formData);
    }
    </script>
@endsection