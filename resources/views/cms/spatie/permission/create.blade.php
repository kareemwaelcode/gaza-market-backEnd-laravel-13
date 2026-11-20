@extends('cms.parent')

@section('title', 'create permission')

@section('styles')

@endsection


@section('content')
    <div class="card">
    <div class="card-header">
        <strong>Create permission</strong>
    </div>
    <div class="card-body card-body">
        <form id="create_form">
            @csrf
            <div class="row">
        <div class="col-12 col-md-6">
            <label for="guard_name" class="form-control-label">Guard Name</label>
        <select name="guard_name" id="guard_name" class="form-select">
            <option value="admins">Admin</option>
            <option value="users">User</option>
        </select>
    </div>
                
                <div class="col-lg-6 mb-3">
                    <label for="name" class="form-control-label">Permission Name</label>
                    <input type="text" id="name" name="name" maxlength="45" placeholder="Enter Role Name in Arabic.." class="form-control">
                    <span class="help-block">Please enter the Role name in Arabic</span>
                </div>
            </div>
            {{--  <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="region_sort_order" class="form-control-label">Sort Order</label>
                    <input type="number" id="region_sort_order" name="region_sort_order" min="0" max="127" placeholder="Enter Sort Order.." class="form-control">
                    <span class="help-block">Please enter the sort order (0 - 127)</span>
                </div>
            </div>  --}}
        </form>
    </div>
    <div class="card-footer">
        <button type="button" onclick="performStore()" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus preview-icon"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Create
        </button>
        <a href="{{ route('permissions.index') }}" type="button" class="btn btn-secondary btn-sm">
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
    formData.append('guard_name', document.getElementById('guard_name').value);
    formData.append('name', document.getElementById('name').value);
    store('/cms/admins/permissions' , formData);
    }
    </script>
@endsection