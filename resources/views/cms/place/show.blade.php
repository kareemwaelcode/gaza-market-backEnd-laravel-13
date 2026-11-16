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
    $isPaid = $place->place_plan === 'paid';
    $canSubscribe = $status === 'approved';
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
        <tr><th>Plan</th><td>
            @if ($isPaid)
                <span class="badge bg-success">Paid</span>
                <small>{{ $place->place_plan_expires_at ? 'until '.$place->place_plan_expires_at->timezone(config('app.display_timezone'))->format('Y-m-d H:i') : 'no expiry' }}</small>
            @else
                <span class="badge bg-secondary">Free</span>
                @if ($place->place_plan_expires_at)
                    <small class="text-muted">expired {{ $place->place_plan_expires_at->timezone(config('app.display_timezone'))->format('Y-m-d H:i') }}</small>
                @endif
            @endif
        </td></tr>
        <tr><th>Owner</th><td>{{ $place->user?->user_full_name }} <small>({{ $place->user?->user_phone }})</small> <small>#{{ $place->user_id }}</small></td></tr>
        <tr><th>Created</th><td>{{ $place->created_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') }}</td></tr>
        <tr><th>Approved</th><td>{{ $place->place_approved_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?? '-' }}</td></tr>
        <tr><th>Activated</th><td>{{ $place->place_activated_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?? '-' }}</td></tr>
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
@if ($canSubscribe)
<button type="button" onclick="subscribePlace({{ $place->id }})" class="btn btn-primary btn-sm">{{ $isPaid ? 'Renew subscription' : 'Activate subscription' }}</button>
@endif
@if ($isPaid)
<button type="button" onclick="cancelSubscription({{ $place->id }})" class="btn btn-outline-danger btn-sm">Cancel subscription</button>
@endif
<button type="button" onclick="deletePlace({{ $place->id }})" class="btn btn-danger btn-sm">Delete</button>
<a href="{{ route('place.index') }}" class="btn btn-secondary btn-sm">Index</a>
</div>
</div>

<div class="card" style="margin-top: 16px;">
<div class="card-header"><strong>Subscription payments</strong> <small class="text-muted">(latest 10)</small></div>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead>
        <tr><th>#</th><th>Amount</th><th>Months</th><th>Starts</th><th>Ends</th><th>Notes</th><th>State</th></tr>
    </thead>
    <tbody>
        @forelse ($place->subscriptions as $sub)
        <tr>
            <td>{{ $sub->id }}</td>
            <td>{{ $sub->subscription_amount }} {{ $sub->subscription_currency }}</td>
            <td>{{ $sub->subscription_period_months }}</td>
            <td>{{ $sub->subscription_starts_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') }}</td>
            <td>{{ $sub->subscription_ends_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') }}</td>
            <td>{{ $sub->subscription_notes ?? '-' }}</td>
            <td>
                @if ($sub->subscription_cancelled_at)
                    <span class="badge bg-danger">Cancelled {{ $sub->subscription_cancelled_at->timezone(config('app.display_timezone'))->format('Y-m-d') }}</span>
                @elseif ($sub->subscription_ends_at?->isPast())
                    <span class="badge bg-secondary">Ended</span>
                @else
                    <span class="badge bg-success">Active</span>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="7" class="text-center">No payments yet.</td></tr>
        @endforelse
    </tbody>
</table>
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

function subscribePlace(id){
Swal.fire({
title: @json($isPaid ? 'تجديد الباقة؟' : 'تفعيل الباقة؟'),
html: @json('المبلغ: '.config('subscription.price').' '.config('subscription.currency').' — المدة: '.config('subscription.period_months').' شهر'.($isPaid ? '<br>التجديد يبدأ من نهاية الباقة الحالية.' : '')),
icon: 'question',
input: 'text',
inputPlaceholder: 'ملاحظة (اختياري)',
inputAttributes: { maxlength: 255 },
showCancelButton: true,
confirmButtonText: 'تأكيد',
cancelButtonText: 'إلغاء',
}).then((result) => {
if (result.isConfirmed) {
    const data = new FormData();
    if (result.value) {
        data.append('notes', result.value);
    }
    storeRoute('/cms/admins/place_subscribe/' + id, data);
}
});
}

function cancelSubscription(id){
Swal.fire({
title: 'إلغاء الباقة؟',
text: 'سيرجع المحل مجانياً فوراً ويبقى سجل الدفعات.',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'إلغاء الباقة',
cancelButtonText: 'رجوع',
}).then((result) => {
if (result.isConfirmed) {
    storeRoute('/cms/admins/place_cancel_subscription/' + id, new FormData());
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