@extends('cms.parent')

@section('title', 'show listing')

@section('styles')
@endsection

@section('content')
@php
    $badge = [
        'pending' => 'bg-warning text-dark',
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
        'sold' => 'bg-secondary',
    ];
    $status = $listing->listing_status;
    $canApprove = in_array($status, ['pending', 'rejected'], true);
    $canReject = in_array($status, ['pending', 'approved'], true);

    $images = $listing->images;
@endphp
<div class="card" style="margin-top: 16px;">
<div class="card-header">
<strong>Listing #{{ $listing->id }}</strong>
<span class="badge {{ $badge[$status] ?? 'bg-secondary' }}">{{ ucfirst($status) }}</span>
</div>
<div class="card-body">
<div class="row">
<div class="col-lg-8">
    <h4>{{ $listing->listing_title }}</h4>
    <p style="white-space: pre-line;">{{ $listing->listing_description }}</p>

    {{-- ===== صور الإعلان (سلايدر) ===== --}}
    <h6 class="mt-3">
        Photos <small class="text-muted">({{ $images->count() }}/3)</small>
    </h6>
    <div class="mb-3" style="max-width: 520px;">
        @if ($images->isEmpty())
            <div class="ratio ratio-4x3 border rounded bg-light">
                <div class="d-flex align-items-center justify-content-center text-muted small">No photos</div>
            </div>
        @else
            <div id="listingPhotos" class="carousel slide border rounded overflow-hidden">
                @if ($images->count() > 1)
                    <div class="carousel-indicators">
                        @foreach ($images as $image)
                            <button type="button" data-bs-target="#listingPhotos" data-bs-slide-to="{{ $loop->index }}"
                                    @class(['active' => $loop->first]) @if ($loop->first) aria-current="true" @endif
                                    aria-label="Photo {{ $loop->iteration }}"></button>
                        @endforeach
                    </div>
                @endif

                <div class="carousel-inner">
                    @foreach ($images as $image)
                        <div @class(['carousel-item', 'active' => $loop->first])>
                            <img src="{{ $image->url }}" class="d-block w-100"
                                    style="aspect-ratio: 4 / 3; object-fit: cover;"
                                    alt="Listing photo {{ $loop->iteration }}">
                        </div>
                    @endforeach
                </div>

                @if ($images->count() > 1)
                    <button class="carousel-control-prev" type="button" data-bs-target="#listingPhotos" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#listingPhotos" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                @endif
            </div>
        @endif
    </div>
    {{-- ===== نهاية الصور ===== --}}

    @if ($listing->listing_rejection_reason)
        <div class="alert alert-danger">
            <strong>Rejection reason:</strong> {{ $listing->listing_rejection_reason }}
        </div>
    @endif
</div>

<div class="col-lg-4">
    <table class="table table-sm">
        <tr><th>Price</th><td>{{ number_format((float) $listing->listing_price, 2) }} ₪ @if ($listing->listing_is_negotiable)<small>(negotiable)</small>@endif</td></tr>
        <tr><th>Condition</th><td>{{ ucfirst($listing->listing_condition) }}</td></tr>
        <tr><th>Category</th><td>{{ $listing->category?->listing_category_name_ar }} / {{ $listing->category?->listing_category_name_en }}</td></tr>
        <tr><th>Area</th><td>{{ $listing->area?->region?->region_name_ar }} - {{ $listing->area?->area_name_ar }}</td></tr>
        <tr><th>Phone</th><td>{{ $listing->listing_phone }}</td></tr>
        <tr><th>WhatsApp</th><td>{{ $listing->listing_whatsapp ?? '-' }}</td></tr>
        <tr><th>Owner</th><td>{{ $listing->user?->user_full_name }} <small>({{ $listing->user?->user_phone }})</small></td></tr>
        <tr><th>Created</th><td>{{ $listing->created_at?->format('Y-m-d H:i') }}</td></tr>
        <tr><th>Published</th><td>{{ $listing->listing_published_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
    </table>
</div>
</div>
</div>
<div class="card-footer">
@if ($canApprove)
<button type="button" onclick="approveListing({{ $listing->id }})" class="btn btn-success btn-sm">Approve</button>
@endif
@if ($canReject)
<button type="button" onclick="rejectListing({{ $listing->id }})" class="btn btn-warning btn-sm">Reject</button>
@endif
<button type="button" onclick="deleteListing({{ $listing->id }})" class="btn btn-danger btn-sm">Delete</button>
<a href="{{ route('listing.index') }}" class="btn btn-secondary btn-sm">Index</a>
</div>
</div>
@endsection

@section('scripts')
<script>
function approveListing(id){
storeRoute('/cms/admins/listing_approve/' + id, new FormData());
}

function rejectListing(id){
Swal.fire({
title: 'سبب الرفض',
input: 'textarea',
inputAttributes: { maxlength: 255 },
showCancelButton: true,
confirmButtonText: 'رفض',
cancelButtonText: 'إلغاء',
inputValidator: (value) => (!value || !value.trim()) ? 'اكتب سبب الرفض' : null,
}).then((result) => {
if (result.isConfirmed) {
    let formData = new FormData();
    formData.append('listing_rejection_reason', result.value);
    storeRoute('/cms/admins/listing_reject/' + id, formData);
}
});
}

function deleteListing(id){
Swal.fire({
title: 'هل أنت متأكد من عملية الحذف ؟',
text: 'لا يمكن التراجع عن عملية الحذف',
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'نعم',
cancelButtonText: 'لا',
}).then((result) => {
if (result.isConfirmed) {
    axios.delete('/cms/admins/listing/' + id)
        .then(function () { window.location = '{{ route('listing.index') }}'; })
        .catch(function (error) {
            showMessage(error.response ? error.response.data : { icon: 'error', title: 'Delete failed' });
        });
}
});
}
</script>
@endsection