@extends('cms.parent')

@section('title', 'index point level')

@section('styles')
@endsection


@section('content')
    <!-- Point Levels table -->
    <div class="row row-tight" style="margin-top: 16px;">
    <div class="col-md-12">
        <section class="m-card" aria-labelledby="point-levels-title">
    <header class="m-card__header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <div>
            <h2 class="m-card__title" id="point-levels-title">Point Levels</h2>
            <p class="m-card__subtitle">User levels based on total points</p>
        </div>
    </header>
        <div class="table-responsive">
            <table class="table table-borderless table-data3">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Level Key</th>
                        <th>Level Name (EN)</th>
                        <th>Level Name (AR)</th>
                        <th>Min Points</th>
                        <th>Sort Order</th>
                        <th>Setting</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ( $pointLevels as $pointLevel )
                    <tr>
                        <td>{{ $pointLevels->firstItem() + $loop->index }}</td>
                        <td>{{ $pointLevel->point_level_key }}</td>
                        <td>{{ $pointLevel->point_level_name_en }}</td>
                        <td>{{ $pointLevel->point_level_name_ar }}</td>
                        <td>{{ number_format($pointLevel->point_level_min_points) }}</td>
                        <td>{{ $pointLevel->point_level_sort_order }}</td>
                        <td>
                            <div class="btn-group" role="group" aria-label="Toolbar group">
                            <a href="{{ route('point_level.edit' ,$pointLevel->id ) }}">
                                <button type="button" class="btn btn-info"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-pen preview-icon"><path d="M2 21a8 8 0 0 1 10.821-7.487"/><path d="M21.378 16.626a1 1 0 0 0-3.004-3.004l-4.01 4.012a2 2 0 0 0-.506.854l-.837 2.87a.5.5 0 0 0 .62.62l2.87-.837a2 2 0 0 0 .854-.506z"/><circle cx="10" cy="8" r="5"/></svg></button>
                            </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    {{ $pointLevels->links() }}
</div>
</div>

@endsection


@section('scripts')
@endsection