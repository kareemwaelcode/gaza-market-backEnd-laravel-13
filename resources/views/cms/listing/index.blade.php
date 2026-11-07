@extends('cms.parent')

@section('title', 'index listing')

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
@endphp
<div class="row row-tight" style="margin-top: 16px;">
    <div class="col-md-12">
        <section class="m-card" aria-labelledby="listings-title">
            <header class="m-card__header" style="width: 100%;">
                <h2 class="m-card__title" id="listings-title">Listings</h2>
                <p class="m-card__subtitle">Review user listings. Pending listings stay hidden until approved.</p>
            </header>

            @if (! empty($ignoredFilters))
                <div class="alert alert-warning" role="alert" style="margin: 0 16px 16px;">
                    Invalid filter value ignored ({{ implode(', ', $ignoredFilters) }}). Showing results without it.
                </div>
            @endif

            <form method="GET" action="{{ route('listing.index') }}" class="row g-2 mb-3" style="padding: 0 16px;">
                <div class="col-lg-3">
                    <select name="status" class="form-control">
                        <option value="">All statuses</option>
                        @foreach (\App\Models\Listing::STATUSES as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-4">
                    <select name="category" class="form-control">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) ($filters['category'] ?? 0) === $category->id)>{{ $category->listing_category_name_ar }} / {{ $category->listing_category_name_en }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3">
                    <select name="area" class="form-control">
                        <option value="">All areas</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}" @selected((int) ($filters['area'] ?? 0) === $area->id)>{{ $area->area_name_ar }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="{{ route('listing.index') }}" class="btn btn-secondary btn-sm">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-borderless table-data3">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Area</th>
                            <th>Owner</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Setting</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($listings as $listing)
                        <tr>
                            <td>{{ $listing->id }}</td>
                            <td>{{ $listing->listing_title }}</td>
                            <td>{{ $listing->category?->listing_category_name_ar }}</td>
                            <td>{{ $listing->area?->area_name_ar }}</td>
                            <td>{{ $listing->user?->user_full_name }}<br><small>{{ $listing->user?->user_phone }}</small></td>
                            <td>{{ number_format((float) $listing->listing_price, 2) }} ₪</td>
                            <td><span class="badge {{ $badge[$listing->listing_status] ?? 'bg-secondary' }}">{{ ucfirst($listing->listing_status) }}</span></td>
                            <td>{{ $listing->created_at?->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="btn-group" role="group" aria-label="Toolbar group">
                                    <a href="{{ route('listing.show', $listing->id) }}" class="btn btn-info btn-sm">View</a>
                                    <button type="button" onclick="performDestroy({{ $listing->id }}, this)" class="btn btn-danger btn-sm">Delete</button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center">No listings found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        {{ $listings->links() }}
    </div>
</div>
<script>
function performDestroy(id, reference){
    let url = '/cms/admins/listing/'+id;
    confirmDestroy(url, reference);
}
</script>
@endsection