{{-- Renders the editor inside the host app's layout (config: translations.layout and translations.section). --}}
@extends(config('translations.layout'))

@push('translations-head')
    <meta name="robots" content="noindex">
@endpush

@section(config('translations.section', 'content'))
    @include('translations::partials.styles')
    <div class="trans">
        @include('translations::partials.page')
    </div>
@endsection
