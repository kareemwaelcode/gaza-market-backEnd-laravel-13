@extends('cms.parent')

@section('title', 'index user')

@section('styles')
@endsection


@section('content')
    <!-- Users table -->
    <div class="row row-tight" style="margin-top: 16px;">
    <div class="col-md-12">
        <section class="m-card" aria-labelledby="users-title">
    <header class="m-card__header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <div>
            <h2 class="m-card__title" id="users-title">Users</h2>
            <p class="m-card__subtitle">Users who registered on Gaza Market</p>
        </div>
    </header>
        <div class="table-responsive">
            <table class="table table-borderless table-data3">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Mobile</th>
                        <th>Role</th>
                        <th>Area</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Last Login</th>
                        <th>Setting</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ( $users as $user )
                    <tr>
                        <td>{{ $users->firstItem() + $loop->index }}</td>
                        <td>{{ $user->user_full_name }}</td>
                        <td>{{ $user->user_phone }}</td>
                        <td>{{ $user->user_role }}</td>
                        <td>{{ $user->area?->area_name_en ?? '-' }}</td>
                        <td>
                            @if ($user->user_status === 'active')
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-danger">Blocked</span>
                            @endif
                        </td>
                        <td>{{ $user->user_created_at?->format('Y-m-d') ?? '-' }}</td>
                        <td>{{ $user->user_last_login_at?->format('Y-m-d H:i') ?? '-' }}</td>
                        <td>
                            <div class="btn-group" role="group" aria-label="Toolbar group">
                            @if ($user->user_status === 'active')
                                <button type="button" onclick="performToggle({{ $user->id }}, 'block')" class="btn btn-warning" title="Block"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-ban preview-icon"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg></button>
                            @else
                                <button type="button" onclick="performToggle({{ $user->id }}, 'unblock')" class="btn btn-success" title="Unblock"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-check-big preview-icon"><path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/></svg></button>
                            @endif
                                <button type="button" onclick="performDestroy({{ $user->id }}, this)" class="btn btn-danger" title="Delete"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash preview-icon"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center">No users yet</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    {{ $users->links() }}
</div>
</div>

@endsection


@section('scripts')
<script>
function performDestroy(id, reference){
    let url = '/cms/admins/user/'+id;
    confirmDestroy(url, reference);
}

function performToggle(id, action){
    let isBlock = action === 'block';

    Swal.fire({
        title: isBlock ? 'هل تريد حظر هذا المستخدم ؟' : 'هل تريد فك الحظر عن هذا المستخدم ؟',
        text: isBlock ? 'سيتم إخراجه من الموقع ولن يستطيع تسجيل الدخول' : 'سيتمكن من تسجيل الدخول مرة أخرى',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'نعم',
        cancelButtonText: 'لا',
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        axios.post('/cms/admins/user_toggle_status/' + id)
            .then(function (response) {
                showMessage(response.data);
                setTimeout(function () { location.reload(); }, 1200);
            })
            .catch(function (error) {
                showMessage(error.response.data);
            });
    });
}
</script>
@endsection