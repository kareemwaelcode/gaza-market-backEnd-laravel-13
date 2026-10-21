@extends('cms.parent')

@section('title', 'index category')

@section('styles')
@endsection


@section('content')
    <!-- Categories table -->
    <div class="row row-tight" style="margin-top: 16px;">
    <div class="col-md-12">
        <section class="m-card" aria-labelledby="categories-title">
    <header class="m-card__header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <div>
            <h2 class="m-card__title" id="categories-title">Categories</h2>
            <p class="m-card__subtitle">All product categories of the Gaza Market system</p>
        </div>
        <a href="{{ route('category.create') }}" type="button" class="m-btn m-btn--primary" style="height:30px;padding:0 12px;font-size:12.5px;"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus preview-icon"><path d="M5 12h14"/><path d="M12 5v14"/></svg>Add Category</a>
    </header>
        <div class="table-responsive">
            <table class="table table-borderless table-data3">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category Slug</th>
                        <th>Category Name (EN)</th>
                        <th>Category Name (AR)</th>
                        <th>Category Group</th>
                        <th>Category Is Active</th>
                        <th>Category Sort Order</th>
                        <th>Setting</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ( $categories as $category )
                    <tr>
                        <td>{{ $categories->firstItem() + $loop->index }}</td>
                        <td>{{ $category->category_slug }}</td>
                        <td>{{ $category->category_name_en }}</td>
                        <td>{{ $category->category_name_ar }}</td>
                        <td>{{ $category->categoryGroup->category_group_name_en }}</td>
                        <td>{{ $category->category_is_active ? 'Active' : 'Inactive' }}</td>
                        <td>{{ $category->category_sort_order }}</td>
                        <td>
                            <div class="btn-group" role="group" aria-label="Toolbar group">
                            <a href="{{ route('category.edit' ,$category->id ) }}">
                                <button type="button" class="btn btn-info"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-pen preview-icon"><path d="M2 21a8 8 0 0 1 10.821-7.487"/><path d="M21.378 16.626a1 1 0 0 0-3.004-3.004l-4.01 4.012a2 2 0 0 0-.506.854l-.837 2.87a.5.5 0 0 0 .62.62l2.87-.837a2 2 0 0 0 .854-.506z"/><circle cx="10" cy="8" r="5"/></svg></button>
                            </a>
                            <a href="#">
                                <button type="button" onclick="performDestroy({{$category->id}}, this)"  class="btn btn-danger"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash preview-icon"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                            </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    {{ $categories->links() }}
</div>
</div>

@endsection


@section('scripts')
<script>
function performDestroy(id, reference){
    let url = '/cms/admins/category/'+id;
    confirmDestroy(url, reference);
}
</script>
@endsection