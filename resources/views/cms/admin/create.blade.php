@extends('cms.parent')

@section('title', 'create')

@section('styles')

@endsection


@section('content')
    <div class="card">
    <div class="card-header">
        <strong>Adding supervisor details.</strong>
    </div>
    <div class="card-body card-body">
        <form>
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="full_name" class="form-control-label">Full name</label>
                    <input type="text" id="full_name" name="full_name" placeholder="Enter Full Name.." class="form-control">
                    <span class="help-block">Please enter your Full Name</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="mobile" class="form-control-label">Mobile</label>
                    <input type="text" id="mobile" name="mobile" placeholder="Enter Mobile.." class="form-control">
                    <span class="help-block">Please enter your Mobile</span>
                </div>
            </div>
        </form>
    </div>
    <div class="card-body card-body">
        <form>
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="email" class="form-control-label">Email</label>
                    <input type="email" id="email" name="email" placeholder="Enter Email.." class="form-control">
                    <span class="help-block">Please enter your email</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="password" class="form-control-label">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter Password.." class="form-control">
                    <span class="help-block">Please enter your password</span>
                </div>
            </div>
        </form>
    </div>
    <div class="card-footer">
        <button type="button" onclick="performStore()" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-plus preview-icon"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
            Create
        </button>
        <a href="{{ route('admin.index') }}" type="button"class="btn btn-secondary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-table-of-contents preview-icon"><path d="M16 5H3"/><path d="M16 12H3"/><path d="M16 19H3"/><path d="M21 5h.01"/><path d="M21 12h.01"/><path d="M21 19h.01"/></svg>
            Index
        </a>

    </div>
</div>
@endsection


@section('scripts')
<script>
    function performStore(){
    let formData = new FormData();
    formData.append('full_name', document.getElementById('full_name').value);
    formData.append('email', document.getElementById('email').value);
    formData.append('password', document.getElementById('password').value);
    formData.append('mobile', document.getElementById('mobile').value);
    store('/cms/admins/admin' , formData);
    }
    </script>
@endsection