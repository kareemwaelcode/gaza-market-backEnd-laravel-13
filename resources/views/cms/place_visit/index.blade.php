@extends('cms.parent')

@section('title', 'place visits')

@section('styles')
@endsection


@section('content')
    <div class="row row-tight" style="margin-top: 16px;">
    <div class="col-md-12">
        <section class="m-card" aria-labelledby="place-visits-title">
    <header class="m-card__header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <div>
            <h2 class="m-card__title" id="place-visits-title">Place Visits</h2>
            <p class="m-card__subtitle">Places ranked by visits (approximate count)</p>
        </div>
        <form method="GET" action="{{ route('place_visit.index') }}">
            <select name="period" class="form-control" onchange="this.form.submit()">
                <option value="today" @selected($period === 'today')>Today</option>
                <option value="7" @selected($period === '7')>Last 7 days</option>
                <option value="30" @selected($period === '30')>Last 30 days</option>
                <option value="all" @selected($period === 'all')>All time</option>
            </select>
        </form>
    </header>
        <div class="table-responsive">
            <table class="table table-borderless table-data3">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Place</th>
                        <th>Type</th>
                        <th>Area</th>
                        <th>Visits</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                    <tr>
                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                        <td>
                            @if ($row->place)
                                <a href="{{ route('place.show', $row->place_id) }}">{{ $row->place->place_name }}</a>
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $row->place?->place_type }}</td>
                        <td>{{ $row->place?->area?->area_name_ar }}</td>
                        <td>{{ number_format((int) $row->total) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5">No visits in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    {{ $rows->links() }}
</div>
</div>

@endsection