@extends('cms.parent')

@section('title', 'edit point rule')

@section('styles')

@endsection


@section('content')
    <div class="card">
    <div class="card-header">
        <strong>Editing point rule details.</strong>
    </div>
    <div class="card-body card-body">
        <form id="create_form">
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="point_rule_key" class="form-control-label">Rule Key</label>
                    <input type="text" id="point_rule_key" value="{{ $pointRule->point_rule_key }}" class="form-control" readonly>
                    <span class="help-block">The key cannot be changed after creation (the code depends on it)</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="point_rule_points" class="form-control-label">Points</label>
                    <input type="number" id="point_rule_points" name="point_rule_points" min="-32768" max="32767" value="{{ $pointRule->point_rule_points }}" placeholder="Enter Points.." class="form-control">
                    <span class="help-block">Points given for this action (negative value deducts points)</span>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="point_rule_name_en" class="form-control-label">Rule Name (English)</label>
                    <input type="text" id="point_rule_name_en" name="point_rule_name_en" maxlength="100" value="{{ $pointRule->point_rule_name_en }}" placeholder="Enter Rule Name in English.." class="form-control">
                    <span class="help-block">Please enter the rule name in English</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="point_rule_name_ar" class="form-control-label">Rule Name (Arabic)</label>
                    <input type="text" id="point_rule_name_ar" name="point_rule_name_ar" maxlength="100" value="{{ $pointRule->point_rule_name_ar }}" placeholder="Enter Rule Name in Arabic.." class="form-control">
                    <span class="help-block">Please enter the rule name in Arabic</span>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-4 mb-3">
                    <label for="point_rule_daily_limit" class="form-control-label">Daily Limit</label>
                    <input type="number" id="point_rule_daily_limit" name="point_rule_daily_limit" min="1" max="65535" value="{{ $pointRule->point_rule_daily_limit }}" placeholder="Leave empty for unlimited" class="form-control">
                    <span class="help-block">Max times per day a user can earn points (empty = unlimited)</span>
                </div>
                <div class="col-lg-4 mb-3">
                    <label for="point_rule_is_active" class="form-control-label">Status</label>
                    <select id="point_rule_is_active" name="point_rule_is_active" class="form-control">
                        <option value="1" @selected($pointRule->point_rule_is_active)>Active</option>
                        <option value="0" @selected(! $pointRule->point_rule_is_active)>Inactive</option>
                    </select>
                    <span class="help-block">Inactive rules do not give points</span>
                </div>
                <div class="col-lg-4 mb-3">
                    <label for="point_rule_sort_order" class="form-control-label">Sort Order</label>
                    <input type="number" id="point_rule_sort_order" name="point_rule_sort_order" min="0" max="127" value="{{ $pointRule->point_rule_sort_order }}" placeholder="Enter Sort Order.." class="form-control">
                    <span class="help-block">Please enter the sort order (0 - 127)</span>
                </div>
            </div>
        </form>
    </div>
    <div class="card-footer">
        <button type="button" onclick="performUpdate({{ $pointRule->id }})" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus preview-icon"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Update
        </button>
        <a href="{{ route('point_rule.index') }}" type="button" class="btn btn-secondary btn-sm">
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
        formData.append('point_rule_name_en', document.getElementById('point_rule_name_en').value);
        formData.append('point_rule_name_ar', document.getElementById('point_rule_name_ar').value);
        formData.append('point_rule_points', document.getElementById('point_rule_points').value);
        formData.append('point_rule_daily_limit', document.getElementById('point_rule_daily_limit').value);
        formData.append('point_rule_is_active', document.getElementById('point_rule_is_active').value);
        formData.append('point_rule_sort_order', document.getElementById('point_rule_sort_order').value);
        storeRoute('/cms/admins/point_rule_update/' + id, formData);
    }
    </script>
@endsection