@extends('cms.parent')

@section('title', 'role-permission')

@section('styles')
@endsection


@section('content')
<div class="row row-tight" style="margin-top: 16px;">
<div class="col-md-12">
<section class="m-card" aria-labelledby="role-permission-title">
<header class="m-card__header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
<div>
<h2 class="m-card__title" id="role-permission-title">{{ $role->name }}</h2>
<p class="m-card__subtitle">
    Permissions of this role
    <span class="badge bg-success">{{ $role->guard_name }}</span>
</p>
</div>
<a href="{{ route('roles.index') }}" class="btn btn-secondary btn-sm">Back to roles</a>
</header>

<div class="table-responsive">
<table class="table table-borderless table-data3">
<thead>
    <tr>
        <th>ID</th>
        <th>Guard Name</th>
        <th>Permission</th>
        <th style="width: 120px;">Status</th>
    </tr>
</thead>
<tbody>
    @forelse ($permissions as $permission)
        <tr>
            <td>{{ $loop->iteration }}</td>
            <td><span class="badge bg-success">{{ $permission->guard_name }}</span></td>
            <td>{{ $permission->name }}</td>
            <td>
                <label class="au-checkbox">
                    <input type="checkbox"
                            id="permission_{{ $permission->id }}"
                            @checked(in_array($permission->id, $assignedIds, true))
                            onchange="storeRolePermission({{ $permission->id }}, this)">
                    <span class="au-checkmark"></span>
                </label>
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="4" class="text-center">
                No permissions for guard "{{ $role->guard_name }}" yet.
                <a href="{{ route('permissions.create') }}">Create one</a>
            </td>
        </tr>
    @endforelse
</tbody>
</table>
</div>
</section>
</div>
</div>
@endsection


@section('scripts')
<script>
function storeRolePermission(permissionId, checkbox) {
let formData = new FormData();
formData.append('permission_id', permissionId);
formData.append('active', checkbox.checked ? 1 : 0);

axios.post('{{ route('roles.permissions.store', $role) }}', formData)
.then(function (response) {
showMessage(response.data);
})
.catch(function (error) {
checkbox.checked = !checkbox.checked;
showMessage(error.response && error.response.data
? error.response.data
: {icon: 'error', title: 'Request failed'});
});
}
</script>
@endsection