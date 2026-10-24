@extends('cms.parent')

@section('title', 'index user')

@section('styles')
@endsection


@section('content')
    @php
        $roles = [
            'user' => ['مستخدم', 'text-bg-secondary'],
            'store_owner' => ['صاحب نشاط', 'text-bg-primary'],
            'admin' => ['أدمن', 'text-bg-info'],
        ];
        $statuses = [
            'active' => ['فعّال', 'text-bg-success'],
            'suspended' => ['موقوف', 'text-bg-danger'],
            'deleted' => ['محذوف', 'text-bg-secondary'],
        ];
        $langs = ['ar' => 'العربية', 'en' => 'English'];
    @endphp

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
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Area</th>
                        <th>Language / Theme</th>
                        <th>Registered / Last Login</th>
                        <th>Setting</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ( $users as $user )
                    @php
                        $role = $roles[$user->user_role] ?? [$user->user_role, 'text-bg-secondary'];
                        $status = $statuses[$user->user_status] ?? [$user->user_status, 'text-bg-secondary'];
                    @endphp
                    <tr>
                        <td>
                            <span class="d-block fw-semibold">{{ $user->user_full_name }}</span>
                            <small class="d-block text-muted" dir="ltr">{{ $user->user_phone }}</small>
                        </td>
                        <td><span class="badge {{ $role[1] }}">{{ $role[0] }}</span></td>
                        <td><span class="badge {{ $status[1] }}">{{ $status[0] }}</span></td>
                        <td>{{ $user->area?->area_name_ar ?? '-' }}</td>
                        <td>
                            <span class="d-block">{{ $langs[$user->user_preferred_lang] ?? '-' }}</span>
                            <small class="d-flex align-items-center gap-1 text-muted">
                            @if ($user->user_theme === 'dark')
                            Dark
                            @else
                            Light
                            @endif
                            </small>
                        </td>
                        <td>
                            <span class="d-block"><small class="text-muted">Registered:</small> {{ $user->user_created_at?->format('Y-m-d') ?? '-' }}</span>
                            <span class="d-block"><small class="text-muted">Last login:</small> {{ $user->user_last_login_at?->format('Y-m-d') ?? '-' }}</span>
                        </td>
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
                        <td colspan="7" class="text-center">No users yet</td>
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