@extends('cms.parent')

@section('title', 'show product price')

@section('styles')
@endsection

@section('content')
@php
    $badge = [
        'pending' => 'bg-warning text-dark',
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
        'flagged' => 'bg-dark',
    ];
    $status = $price->product_price_status;
    $statusLabel = ['rejected' => 'Hidden', 'flagged' => 'Hidden (reports)'][$status] ?? ucfirst($status);
    $canShow = $status !== 'approved';
    $canHide = in_array($status, ['pending', 'approved', 'flagged'], true);
    $product = $price->product;
@endphp
<div class="card" style="margin-top: 16px;">
<div class="card-header">
<strong>Price #{{ $price->id }}</strong>
<span class="badge {{ $badge[$status] ?? 'bg-secondary' }}">{{ $statusLabel }}</span>
</div>
<div class="card-body">
<div class="row">
<div class="col-lg-8">
    <h4>{{ $product?->product_name_ar ?: $product?->product_name_en }}</h4>

    @if ($status === 'flagged')
        <div class="alert alert-warning" role="alert">
            Hidden automatically after {{ $price->report_count }} reports. Show it to dismiss the reports.
        </div>
    @endif

    <div class="row mt-3">
        <div class="col-md-6">
            <h6>👍 Confirmed by ({{ $confirmedBy->count() }})</h6>
            <ul class="list-unstyled small">
                @forelse ($confirmedBy as $vote)
                    <li>{{ $vote->user?->user_full_name ?: '-' }} <small class="text-muted">({{ $vote->user?->user_phone }})</small></li>
                @empty
                    <li class="text-muted">No confirmations.</li>
                @endforelse
            </ul>
        </div>
        <div class="col-md-6">
            <h6>🚩 Reported by ({{ $reportedBy->count() }})</h6>
            <ul class="list-unstyled small">
                @forelse ($reportedBy as $vote)
                    <li>{{ $vote->user?->user_full_name ?: '-' }} <small class="text-muted">({{ $vote->user?->user_phone }})</small></li>
                @empty
                    <li class="text-muted">No reports.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <h6 class="mt-3">Receipt</h6>
    <div class="mb-3" style="max-width: 520px;">
        @if ($price->product_price_receipt_image)
            <a href="{{ route('product_price_receipt', $price->id) }}" target="_blank" rel="noopener noreferrer">
                <img src="{{ route('product_price_receipt', $price->id) }}" class="d-block w-100 border rounded"
                        style="object-fit: contain;" alt="Receipt">
            </a>
        @else
            <div class="ratio ratio-4x3 border rounded bg-light">
                <div class="d-flex align-items-center justify-content-center text-muted small">No receipt</div>
            </div>
        @endif
    </div>
</div>

<div class="col-lg-4">
    <table class="table table-sm">
        <tr><th>Product</th><td>{{ $product?->product_name_ar ?: '-' }} / {{ $product?->product_name_en ?: '-' }}</td></tr>
        <tr><th>Category</th><td>{{ $product?->category?->category_name_ar }} / {{ $product?->category?->category_name_en }}</td></tr>
        <tr><th>Quantity</th><td>{{ $product?->product_quantity }} {{ $product?->unit?->unit_name_ar }}</td></tr>
        <tr><th>Price</th><td>{{ number_format((float) $price->product_price_value, 2) }} ₪</td></tr>
        <tr><th>Store</th><td>{{ $price->product_price_store_name }}</td></tr>
        <tr><th>Store address</th><td>{{ $price->product_price_store_address ?: '-' }}</td></tr>
        <tr><th>Store phone</th><td>{{ $price->product_price_store_phone ?: '-' }}</td></tr>
        <tr><th>Area</th><td>{{ $price->area?->region?->region_name_ar }} - {{ $price->area?->area_name_ar }}</td></tr>
        <tr><th>Reported by</th><td>{{ $price->user?->user_full_name }} <small>({{ $price->user?->user_phone }})</small></td></tr>
        <tr><th>Votes</th><td>👍 {{ $price->confirm_count }} / 🚩 {{ $price->report_count }}</td></tr>
        <tr><th>Reviewed</th><td>{{ $price->reviewed_at?->format('Y-m-d H:i') ?: '-' }}</td></tr>
        <tr><th>Created</th><td>{{ $price->created_at?->format('Y-m-d H:i') }}</td></tr>
        <tr><th>Updated</th><td>{{ $price->updated_at?->format('Y-m-d H:i') }}</td></tr>
    </table>
</div>
</div>
</div>
<div class="card-footer">
@if ($canShow)
<button type="button" onclick="showPrice({{ $price->id }})" class="btn btn-success btn-sm">Show</button>
@endif
@if ($canHide)
<button type="button" onclick="hidePrice({{ $price->id }})" class="btn btn-warning btn-sm">Hide</button>
@endif
<button type="button" onclick="deletePrice({{ $price->id }})" class="btn btn-danger btn-sm">Delete</button>
<a href="{{ route('product_price.index') }}" class="btn btn-secondary btn-sm">Index</a>
</div>
</div>
@endsection

@section('scripts')
<script>
function showPrice(id){
storeRoute('/cms/admins/product_price_approve/' + id, new FormData());
}

function hidePrice(id){
Swal.fire({
title: 'إخفاء هذا السعر؟',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'إخفاء',
cancelButtonText: 'إلغاء',
}).then((result) => {
if (result.isConfirmed) {
    storeRoute('/cms/admins/product_price_reject/' + id, new FormData());
}
});
}

function deletePrice(id){
Swal.fire({
title: 'هل أنت متأكد من عملية الحذف ؟',
text: 'لا يمكن التراجع عن عملية الحذف',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'نعم',
cancelButtonText: 'لا',
}).then((result) => {
if (result.isConfirmed) {
    axios.delete('/cms/admins/product_price/' + id)
        .then(function () { window.location = '{{ route('product_price.index') }}'; })
        .catch(function (error) {
            showMessage(error.response ? error.response.data : { icon: 'error', title: 'Delete failed' });
        });
}
});
}
</script>
@endsection