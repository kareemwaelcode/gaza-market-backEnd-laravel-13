@extends('cms.parent')

@section('title', 'edit hero section')

@section('styles')

@endsection


@section('content')
    <div class="card">
    <div class="card-header">
        <strong>Editing hero section details.</strong>
    </div>
    <div class="card-body card-body">
        <form id="create_form">
            @foreach ($fields as $field => [$label, $max])
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label for="hero_section_{{ $field }}_en" class="form-control-label">{{ $label }} (English)</label>
                    @if ($max > 200)
                    <textarea id="hero_section_{{ $field }}_en" name="hero_section_{{ $field }}_en" maxlength="{{ $max }}" rows="2" class="form-control">{{ $heroSection->{'hero_section_'.$field.'_en'} }}</textarea>
                    @else
                    <input type="text" id="hero_section_{{ $field }}_en" name="hero_section_{{ $field }}_en" maxlength="{{ $max }}" value="{{ $heroSection->{'hero_section_'.$field.'_en'} }}" class="form-control">
                    @endif
                    <span class="help-block">Max {{ $max }} characters</span>
                </div>
                <div class="col-lg-6 mb-3">
                    <label for="hero_section_{{ $field }}_ar" class="form-control-label">{{ $label }} (Arabic)</label>
                    @if ($max > 200)
                    <textarea id="hero_section_{{ $field }}_ar" name="hero_section_{{ $field }}_ar" maxlength="{{ $max }}" rows="2" class="form-control">{{ $heroSection->{'hero_section_'.$field.'_ar'} }}</textarea>
                    @else
                    <input type="text" id="hero_section_{{ $field }}_ar" name="hero_section_{{ $field }}_ar" maxlength="{{ $max }}" value="{{ $heroSection->{'hero_section_'.$field.'_ar'} }}" class="form-control">
                    @endif
                    <span class="help-block">Max {{ $max }} characters</span>
                </div>
            </div>
            @endforeach
        </form>
    </div>
    <div class="card-footer">
        <button type="button" onclick="performUpdate({{ $heroSection->id }})" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus preview-icon"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Update
        </button>
        <a href="{{ route('hero_section.index') }}" type="button" class="btn btn-secondary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-table-of-contents preview-icon"><path d="M16 5H3"/><path d="M16 12H3"/><path d="M16 19H3"/><path d="M21 5h.01"/><path d="M21 12h.01"/><path d="M21 19h.01"/></svg>
            Index
        </a>
    </div>
</div>
@endsection


@section('scripts')
<script>
    function performUpdate(id){
        let formData = new FormData();
        document.querySelectorAll('#create_form [name]').forEach(function(el){
            formData.append(el.name, el.value);
        });
        storeRoute('/cms/admins/hero_section_update/' + id, formData);
    }
    </script>
@endsection