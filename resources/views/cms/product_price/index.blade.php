@extends('cms.parent')

@section('title', 'index product price')

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
@endphp
<div class="row row-tight" style="margin-top: 16px;">
    <div class="col-md-12">
        <section class="m-card" aria-labelledby="product-prices-title">
            <header class="m-card__header" style="width: 100%;">
                <h2 class="m-card__title" id="product-prices-title">Product prices</h2>
                <p class="m-card__subtitle">Review prices reported by users. Pending and flagged prices stay hidden until approved.</p>
            </header>

            @if (! empty($ignoredFilters))
                <div class="alert alert-warning" role="alert" style="margin: 0 16px 16px;">
                    Invalid filter value ignored ({{ implode(', ', $ignoredFilters) }}). Showing results without it.
                </div>
            @endif

            <form method="GET" action="{{ route('product_price.index') }}" class="row g-2 mb-3" style="padding: 0 16px;">
                <div class="col-lg-3">
                    <select name="status" class="form-control">
                        <option value="">All statuses</option>
                        @foreach (\App\Models\ProductPrice::STATUSES as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-4">
                    <select name="category" class="form-control">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) ($filters['category'] ?? 0) === $category->id)>{{ $category->category_name_ar }} / {{ $category->category_name_en }}</option>
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
                    <a href="{{ route('product_price.index') }}" class="btn btn-secondary btn-sm">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-borderless table-data3">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Store</th>
                            <th>Area</th>
                            <th>Reported by</th>
                            <th>Votes</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Setting</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($prices as $price)
                        <tr>
                            <td>{{ $price->id }}</td>
                            <td>{{ $price->product?->product_name_ar ?: $price->product?->product_name_en }}</td>
                            <td>{{ $price->product?->category?->category_name_ar }}</td>
                            <td>{{ $price->product?->product_quantity }} {{ $price->product?->unit?->unit_name_ar }}</td>
                            <td>{{ number_format((float) $price->product_price_value, 2) }} ₪</td>
                            <td>{{ $price->product_price_store_name }}</td>
                            <td>{{ $price->area?->area_name_ar }}</td>
                            <td>{{ $price->user?->user_full_name }}<br><small>{{ $price->user?->user_phone }}</small></td>
                            <td>👍 {{ $price->confirm_count }} / 🚩 {{ $price->report_count }}</td>
                            <td><span class="badge {{ $badge[$price->product_price_status] ?? 'bg-secondary' }}">{{ ucfirst($price->product_price_status) }}</span></td>
                            <td>{{ $price->created_at?->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="btn-group" role="group" aria-label="Toolbar group">
                                    <a href="{{ route('product_price.show', $price->id) }}" class="btn btn-info btn-sm">View</a>
                                    <button type="button" onclick="performDestroy({{ $price->id }}, this)" class="btn btn-danger btn-sm">Delete</button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="12" class="text-center">No prices found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        {{ $prices->links() }}
    </div>
</div>
<script>
function performDestroy(id, reference){
    let url = '/cms/admins/product_price/'+id;
    confirmDestroy(url, reference);
}
</script>
@endsection