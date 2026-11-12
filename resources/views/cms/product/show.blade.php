@extends('cms.parent')

@section('title', 'show product')

@section('styles')
@endsection

@section('content')
@php
    $badge = [
        'pending' => 'bg-warning text-dark',
        'active' => 'bg-success',
        'hidden' => 'bg-secondary',
    ];
    $status = $product->product_status;
@endphp
<div class="card" style="margin-top: 16px;">
<div class="card-header">
<strong>Product #{{ $product->id }}</strong>
<span class="badge {{ $badge[$status] ?? 'bg-secondary' }}">{{ ucfirst($status) }}</span>
</div>
<div class="card-body">
<div class="row">
<div class="col-lg-8">
    <h4>{{ $product->product_name_ar ?: $product->product_name_en }}</h4>

    <h6 class="mt-3">Receipt</h6>
    <div class="mb-3" style="max-width: 520px;">
        @if ($product->product_receipt_image)
            <a href="{{ route('product_receipt', $product->id) }}" target="_blank" rel="noopener noreferrer">
                <img src="{{ route('product_receipt', $product->id) }}" class="d-block w-100 border rounded"
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
        <tr><th>Name (AR)</th><td>{{ $product->product_name_ar ?: '-' }}</td></tr>
        <tr><th>Name (EN)</th><td>{{ $product->product_name_en ?: '-' }}</td></tr>
        <tr><th>Category</th><td>{{ $product->category?->category_name_ar }} / {{ $product->category?->category_name_en }}</td></tr>
        <tr><th>Quantity</th><td>{{ $product->product_quantity }} {{ $product->unit?->unit_name_ar }}</td></tr>
        <tr><th>Price</th><td>{{ number_format((float) $product->product_price, 2) }} ₪</td></tr>
        <tr><th>Store</th><td>{{ $product->product_store_name }}</td></tr>
        <tr><th>Store address</th><td>{{ $product->product_store_address ?: '-' }}</td></tr>
        <tr><th>Store phone</th><td>{{ $product->product_store_phone ?: '-' }}</td></tr>
        <tr><th>Area</th><td>{{ $product->area?->region?->region_name_ar }} - {{ $product->area?->area_name_ar }}</td></tr>
        <tr><th>Added by</th><td>{{ $product->user?->user_full_name }} <small>({{ $product->user?->user_phone }})</small></td></tr>
        <tr><th>Created</th><td>{{ $product->created_at?->format('Y-m-d H:i') }}</td></tr>
        <tr><th>Updated</th><td>{{ $product->updated_at?->format('Y-m-d H:i') }}</td></tr>
    </table>
</div>
</div>
</div>
<div class="card-footer">
@if ($status === 'pending')
<button type="button" onclick="approveProduct({{ $product->id }})" class="btn btn-success btn-sm">Approve</button>
@endif
@if ($status === 'active')
<button type="button" onclick="hideProduct({{ $product->id }})" class="btn btn-warning btn-sm">Hide</button>
@endif
@if ($status === 'hidden')
<button type="button" onclick="unhideProduct({{ $product->id }})" class="btn btn-success btn-sm">Show</button>
@endif
<button type="button" onclick="deleteProduct({{ $product->id }})" class="btn btn-danger btn-sm">Delete</button>
<a href="{{ route('product.index') }}" class="btn btn-secondary btn-sm">Index</a>
</div>
</div>
@endsection

@section('scripts')
<script>
function approveProduct(id){
storeRoute('/cms/admins/product_approve/' + id, new FormData());
}

function hideProduct(id){
storeRoute('/cms/admins/product_hide/' + id, new FormData());
}

function unhideProduct(id){
storeRoute('/cms/admins/product_unhide/' + id, new FormData());
}

function deleteProduct(id){
Swal.fire({
title: 'هل أنت متأكد من عملية الحذف ؟',
text: 'لا يمكن التراجع عن عملية الحذف',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'نعم',
cancelButtonText: 'لا',
}).then((result) => {
if (result.isConfirmed) {
    axios.delete('/cms/admins/product/' + id)
        .then(function () { window.location = '{{ route('product.index') }}'; })
        .catch(function (error) {
            showMessage(error.response ? error.response.data : { icon: 'error', title: 'Delete failed' });
        });
}
});
}
</script>
@endsection