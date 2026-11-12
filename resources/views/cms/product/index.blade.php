@extends('cms.parent')

@section('title', 'index product')

@section('styles')
@endsection

@section('content')
@php
    $badge = [
        'pending' => 'bg-warning text-dark',
        'active' => 'bg-success',
        'hidden' => 'bg-secondary',
    ];
@endphp
<div class="row row-tight" style="margin-top: 16px;">
    <div class="col-md-12">
        <section class="m-card" aria-labelledby="products-title">
            <header class="m-card__header" style="width: 100%;">
                <h2 class="m-card__title" id="products-title">Products</h2>
                <p class="m-card__subtitle">Review prices submitted by users. Pending prices stay hidden until approved.</p>
            </header>

            @if (! empty($ignoredFilters))
                <div class="alert alert-warning" role="alert" style="margin: 0 16px 16px;">
                    Invalid filter value ignored ({{ implode(', ', $ignoredFilters) }}). Showing results without it.
                </div>
            @endif

            <form method="GET" action="{{ route('product.index') }}" class="row g-2 mb-3" style="padding: 0 16px;">
                <div class="col-lg-3">
                    <select name="status" class="form-control">
                        <option value="">All statuses</option>
                        @foreach (\App\Models\Product::STATUSES as $status)
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
                    <a href="{{ route('product.index') }}" class="btn btn-secondary btn-sm">Reset</a>
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
                            <th>Added by</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Setting</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                        <tr>
                            <td>{{ $product->id }}</td>
                            <td>{{ $product->product_name_ar ?: $product->product_name_en }}</td>
                            <td>{{ $product->category?->category_name_ar }}</td>
                            <td>{{ $product->product_quantity }} {{ $product->unit?->unit_name_ar }}</td>
                            <td>{{ number_format((float) $product->product_price, 2) }} ₪</td>
                            <td>{{ $product->product_store_name }}</td>
                            <td>{{ $product->area?->area_name_ar }}</td>
                            <td>{{ $product->user?->user_full_name }}<br><small>{{ $product->user?->user_phone }}</small></td>
                            <td><span class="badge {{ $badge[$product->product_status] ?? 'bg-secondary' }}">{{ ucfirst($product->product_status) }}</span></td>
                            <td>{{ $product->created_at?->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="btn-group" role="group" aria-label="Toolbar group">
                                    <a href="{{ route('product.show', $product->id) }}" class="btn btn-info btn-sm">View</a>
                                    <button type="button" onclick="performDestroy({{ $product->id }}, this)" class="btn btn-danger btn-sm">Delete</button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="11" class="text-center">No products found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        {{ $products->links() }}
    </div>
</div>
<script>
function performDestroy(id, reference){
    let url = '/cms/admins/product/'+id;
    confirmDestroy(url, reference);
}
</script>
@endsection