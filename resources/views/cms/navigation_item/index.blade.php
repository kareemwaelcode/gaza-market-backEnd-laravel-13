@extends('cms.parent')

@section('title', 'index navigation item')

@section('styles')
@endsection


@section('content')
    <!-- Navigation Items table -->
    <div class="row row-tight" style="margin-top: 16px;">
    <div class="col-md-12">
        <section class="m-card" aria-labelledby="navigation-items-title">
    <header class="m-card__header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <div>
            <h2 class="m-card__title" id="navigation-items-title">Navigation Items</h2>
            <p class="m-card__subtitle">Sidebar and mobile navigation links of the Gaza Market site</p>
        </div>
        <a href="{{ route('navigation_item.create') }}" type="button" class="m-btn m-btn--primary" style="height:30px;padding:0 12px;font-size:12.5px;"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus preview-icon"><path d="M5 12h14"/><path d="M12 5v14"/></svg>Add Navigation Item</a>
    </header>
        <div class="table-responsive">
            <table class="table table-borderless table-data3">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Slug</th>
                        <th>Name (EN)</th>
                        <th>Name (AR)</th>
                        <th>Icon</th>
                        <th>URL</th>
                        <th>Mobile</th>
                        <th>Desktop</th>
                        <th>Status</th>
                        <th>Coming Soon</th>
                        <th>Sort Order</th>
                        <th>Setting</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ( $navigationItems as $navigationItem )
                    <tr>
                        <td>{{ $navigationItems->firstItem() + $loop->index }}</td>
                        <td>{{ $navigationItem->navigation_item_slug }}</td>
                        <td>{{ $navigationItem->navigation_item_name_en }}</td>
                        <td>{{ $navigationItem->navigation_item_name_ar }}</td>
                        <td>{{ $navigationItem->navigation_item_icon }}</td>
                        <td>{{ $navigationItem->navigation_item_url }}</td>
                        <td>{{ $navigationItem->navigation_item_is_mobile ? 'Yes' : 'No' }}</td>
                        <td>{{ $navigationItem->navigation_item_is_desktop ? 'Yes' : 'No' }}</td>
                        <td>{{ $navigationItem->navigation_item_is_active ? 'Active' : 'Inactive' }}</td>
                        <td>{{ $navigationItem->navigation_item_is_coming_soon ? 'Yes' : 'No' }}</td>
                        <td>{{ $navigationItem->navigation_item_sort_order }}</td>
                        <td>
                            <div role="group" aria-label="Toolbar group" style="display:flex;gap:8px;">
                            <a href="{{ route('navigation_item.edit' ,$navigationItem->id ) }}">
                                <button type="button" class="btn btn-info"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-pen preview-icon"><path d="M2 21a8 8 0 0 1 10.821-7.487"/><path d="M21.378 16.626a1 1 0 0 0-3.004-3.004l-4.01 4.012a2 2 0 0 0-.506.854l-.837 2.87a.5.5 0 0 0 .62.62l2.87-.837a2 2 0 0 0 .854-.506z"/><circle cx="10" cy="8" r="5"/></svg></button>
                            </a>
                            <a href="#">
                                <button type="button" onclick="performDestroy({{$navigationItem->id}}, this)"  class="btn btn-danger"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash preview-icon"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                            </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    {{ $navigationItems->links() }}
</div>
</div>

@endsection


@section('scripts')
<script>
function performDestroy(id, reference){
    let url = '/cms/admins/navigation_item/'+id;
    confirmDestroy(url, reference);
}
</script>
@endsection