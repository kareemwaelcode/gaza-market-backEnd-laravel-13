@extends('cms.parent')

@section('title', 'index place')

@section('styles')
@endsection

@section('content')
@php
    $badge = [
        'pending' => 'bg-warning text-dark',
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
    ];
@endphp
<div class="row row-tight" style="margin-top: 16px;">
    <div class="col-md-12">
        <section class="m-card" aria-labelledby="places-title">
            <header class="m-card__header" style="width: 100%;">
                <h2 class="m-card__title" id="places-title">Places</h2>
                <p class="m-card__subtitle">Review registered places. Pending places are listed first.</p>
            </header>

            @if (! empty($ignoredFilters))
                <div class="alert alert-warning" role="alert" style="margin: 0 16px 16px;">
                    Invalid filter value ignored ({{ implode(', ', $ignoredFilters) }}). Showing results without it.
                </div>
            @endif

            <form method="GET" action="{{ route('place.index') }}" class="row g-2 mb-3" style="padding: 0 16px;">
                <div class="col-lg-3">
                    <select name="status" class="form-control">
                        <option value="">All statuses</option>
                        @foreach (\App\Models\Place::STATUSES as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3">
                    <select name="type" class="form-control">
                        <option value="">All types</option>
                        @foreach (\App\Models\Place::TYPES as $type)
                            <option value="{{ $type }}" @selected(($filters['type'] ?? null) === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-4">
                    <select name="area" class="form-control">
                        <option value="">All areas</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}" @selected((int) ($filters['area'] ?? 0) === $area->id)>{{ $area->area_name_ar }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="{{ route('place.index') }}" class="btn btn-secondary btn-sm">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-borderless table-data3">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Area</th>
                            <th>Owner</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Setting</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($places as $place)
                        <tr>
                            <td>{{ $place->id }}</td>
                            <td>{{ $place->place_name }}</td>
                            <td>{{ $place->place_type }}@if ($place->storeCategory)<br><small>{{ $place->storeCategory->store_category_name_ar }}</small>@endif</td>
                            <td>{{ $place->area?->area_name_ar }}</td>
                            <td>{{ $place->user?->user_full_name }}<br><small>{{ $place->user?->user_phone }}</small></td>
                            <td><span class="badge {{ $badge[$place->place_status] ?? 'bg-secondary' }}">{{ ucfirst($place->place_status) }}</span></td>
                            <td>{{ $place->created_at?->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="btn-group" role="group" aria-label="Toolbar group">
                                    <a href="{{ route('place.show', $place->id) }}" class="btn btn-info btn-sm">View</a>
                                    <button type="button" onclick="performDestroy({{ $place->id }}, this)" class="btn btn-danger btn-sm">Delete</button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center">No places found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        {{ $places->links() }}
    </div>
</div>
<script>
function performDestroy(id, reference){
    let url = '/cms/admins/place/'+id;
    confirmDestroy(url, reference);
}
</script>
@endsection