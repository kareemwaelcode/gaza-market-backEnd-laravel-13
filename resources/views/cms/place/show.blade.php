@extends('cms.parent')

@section('title', 'show place')

@section('styles')
@endsection

@section('content')
@php
    $badge = [
        'pending' => 'bg-warning text-dark',
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
    ];
    $status = $place->place_status;
    $canApprove = in_array($status, ['pending', 'rejected'], true);
    $canReject = in_array($status, ['pending', 'approved'], true);
@endphp
<div class="card" style="margin-top: 16px;">
<div class="card-header">
<strong>Place #{{ $place->id }}</strong>
<span class="badge {{ $badge[$status] ?? 'bg-secondary' }}">{{ ucfirst($status) }}</span>
</div>
<div class="card-body">
<div class="row">
<div class="col-lg-6">
    <h4>{{ $place->place_name }}</h4>
    <p>{{ $place->place_address }}</p>
    <div class="mb-3" style="max-width: 420px;">
        @if ($place->logo_url)
            <img src="{{ $place->logo_url }}" class="d-block w-100 border rounded" style="aspect-ratio: 4 / 3; object-fit: cover;" alt="Place photo">
        @else
            <div class="ratio ratio-4x3 border rounded bg-light">
                <div class="d-flex align-items-center justify-content-center text-muted small">No photo</div>
            </div>
        @endif
    </div>
</div>
<div class="col-lg-6">
    <table class="table table-sm">
        <tr><th>Type</th><td>{{ $place->place_type }}</td></tr>
        <tr><th>Category</th><td>@if ($place->storeCategory){{ $place->storeCategory->store_category_name_ar }} / {{ $place->storeCategory->store_category_name_en }}@else - @endif</td></tr>
        <tr><th>Area</th><td>{{ $place->area?->region?->region_name_ar }} - {{ $place->area?->area_name_ar }}</td></tr>
        <tr><th>Phone</th><td>{{ $place->place_phone }}</td></tr>
        <tr><th>WhatsApp</th><td><a href="{{ $place->whatsapp_link }}" target="_blank" rel="noopener noreferrer" dir="ltr">{{ $place->place_whatsapp }}</a></td></tr>
        <tr><th>Plan</th><td>{{ ucfirst($place->place_plan) }}</td></tr>
        <tr><th>Owner</th><td>{{ $place->user?->user_full_name }} <small>({{ $place->user?->user_phone }})</small> <small>#{{ $place->user_id }}</small></td></tr>
        <tr><th>Created</th><td>{{ $place->created_at?->format('Y-m-d H:i') }}</td></tr>
        <tr><th>Approved</th><td>{{ $place->place_approved_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
        <tr><th>Activated</th><td>{{ $place->place_activated_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
    </table>
</div>
</div>
</div>
<div class="card-footer">
@if ($canApprove)
<button type="button" onclick="approvePlace({{ $place->id }})" class="btn btn-success btn-sm">Approve</button>
@endif
@if ($canReject)
<button type="button" onclick="rejectPlace({{ $place->id }})" class="btn btn-warning btn-sm">Reject</button>
@endif
<button type="button" onclick="deletePlace({{ $place->id }})" class="btn btn-danger btn-sm">Delete</button>
<a href="{{ route('place.index') }}" class="btn btn-secondary btn-sm">Index</a>
</div>
</div>
@endsection

@section('scripts')
<script>
function approvePlace(id){
storeRoute('/cms/admins/place_approve/' + id, new FormData());
}

function rejectPlace(id){
Swal.fire({
title: 'رفض المحل؟',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'رفض',
cancelButtonText: 'إلغاء',
}).then((result) => {
if (result.isConfirmed) {
    storeRoute('/cms/admins/place_reject/' + id, new FormData());
}
});
}

function deletePlace(id){
Swal.fire({
title: 'هل أنت متأكد من عملية الحذف ؟',
text: 'لا يمكن التراجع عن عملية الحذف',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'نعم',
cancelButtonText: 'لا',
}).then((result) => {
if (result.isConfirmed) {
    axios.delete('/cms/admins/place/' + id)
        .then(function () { window.location = '{{ route('place.index') }}'; })
        .catch(function (error) {
            showMessage(error.response ? error.response.data : { icon: 'error', title: 'Delete failed' });
        });
}
});
}
</script>
@endsection