@extends('translations::'.\Ruvelo\Translations\Translations::layout())

@section('title', $localeName)

@section('translations')
    @php
        $isSource = $locale === $source;
        $dir = in_array(strtok($locale, '_-'), ['ar', 'he', 'fa', 'ur', 'yi', 'ps', 'dv', 'ku', 'sd'], true) ? 'rtl' : 'ltr';
        $fileLabel = fn (string $id) => $id === '*' ? 'JSON' : $id;
        $tab = fn (string $name) => route('translations.editor', array_filter(['locale' => $locale, 'filter' => $name === 'all' ? null : $name, 'q' => $search, 'file' => $file]));
    @endphp

    <ol class="trans-crumbs">
        <li><a href="{{ route('translations.index') }}">Languages</a></li>
        <li>{{ $localeName }}</li>
    </ol>

    <div class="trans-head">
        <div>
            <h1>@if ($flag = \Ruvelo\Translations\Support\Flags::for($locale))<img class="trans-flag trans-flag--large" src="{{ $flag }}" alt="" width="34" height="34">@endif{{ $localeName }} <span class="trans-chip trans-chip--quiet">{{ $locale }}</span> @if ($isSource)<span class="trans-chip">Source</span>@endif</h1>
            <div class="trans-progress" style="margin-top: .6rem">
                <span class="trans-meter @if ($progress->isComplete()) trans-meter--done @endif" role="progressbar" aria-label="Translated" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress->percent() }}"><span style="width: {{ $progress->percent() }}%"></span></span>
                <span>{{ $progress->percent() }}% · {{ number_format($progress->missing()) }} missing</span>
            </div>
        </div>
        <form class="trans-switch" method="get" action="{{ route('translations.index') }}">
            <label class="trans-sr" for="trans-locale">Language</label>
            <select id="trans-locale" name="locale" data-trans-autosubmit>
                @foreach ($locales as $code)
                    <option value="{{ $code }}" @selected($code === $locale)>{{ \Ruvelo\Translations\Translations::localeName($code) }} ({{ $code }})</option>
                @endforeach
            </select>
            <button type="submit" class="trans-btn--quiet" data-trans-nojs>Open</button>
        </form>
    </div>

    <nav class="trans-tabs" aria-label="Show">
        <a href="{{ $tab('all') }}" @if ($filter === 'all') aria-current="page" @endif>All <span>{{ number_format($counts['all']) }}</span></a>
        <a href="{{ $tab('missing') }}" @if ($filter === 'missing') aria-current="page" @endif>Missing <span>{{ number_format($counts['missing']) }}</span></a>
        <a href="{{ $tab('changed') }}" @if ($filter === 'changed') aria-current="page" @endif>Not exported <span>{{ number_format($counts['changed']) }}</span></a>
        <a href="{{ $tab('warnings') }}" @if ($filter === 'warnings') aria-current="page" @endif>Needs a look <span>{{ number_format($counts['warnings']) }}</span></a>
    </nav>

    <form class="trans-filters" method="get" action="{{ route('translations.editor', $locale) }}" role="search">
        @if ($filter !== 'all')
            <input type="hidden" name="filter" value="{{ $filter }}">
        @endif
        <label class="trans-sr" for="trans-q">Search keys and text</label>
        <input id="trans-q" type="search" name="q" value="{{ $search }}" placeholder="Search keys and text…">
        <label class="trans-sr" for="trans-file">File</label>
        <select id="trans-file" name="file" data-trans-autosubmit>
            <option value="">All files</option>
            @foreach ($files as $id)
                <option value="{{ $id }}" @selected($id === $file)>{{ $fileLabel($id) }}</option>
            @endforeach
        </select>
        <button type="submit" class="trans-btn--quiet">Search</button>
    </form>

    <p class="trans-keys-help" data-trans-js hidden>Changes save when you leave a field. <kbd>Enter</kbd> saves and moves to the next string, <kbd>Shift</kbd>+<kbd>Enter</kbd> adds a line break, <kbd>Esc</kbd> undoes.</p>

    <div class="trans-table-wrap">
        @if ($entries->isEmpty())
            <div class="trans-empty">
                @if ($search !== '' || $file !== null)
                    <strong>Nothing matches.</strong>
                    <a href="{{ route('translations.editor', $locale) }}">Show every string</a>
                @elseif ($filter === 'missing')
                    <strong>Nothing missing.</strong> Every string in {{ $localeName }} is translated.
                @elseif ($filter === 'changed')
                    <strong>Nothing to export.</strong> The lang files say what the app shows.
                @elseif ($filter === 'warnings')
                    <strong>Nothing to check.</strong> Every translation keeps its placeholders.
                @else
                    <strong>No strings yet.</strong> Add some to your lang files, or run <code>php artisan translations:scan --create</code>.
                @endif
            </div>
        @else
            <table class="trans-table" data-trans-editor data-locale="{{ $locale }}">
                <colgroup>
                    <col class="trans-col-key">
                    @unless ($isSource)<col class="trans-col-source">@endunless
                    <col>
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col">Key</th>
                        @unless ($isSource)<th scope="col">{{ $sourceName }}</th>@endunless
                        <th scope="col">{{ $localeName }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        @include('translations::partials.row', ['entry' => $entry])
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if ($entries->lastPage() > 1)
        <div class="trans-pager">
            <span>Showing {{ number_format($entries->firstItem() ?? 0) }}–{{ number_format($entries->lastItem() ?? 0) }} of {{ number_format($entries->total()) }}</span>
            <nav aria-label="Pages">
                @if ($entries->onFirstPage())
                    <span class="trans-btn trans-btn--quiet trans-btn--small" aria-disabled="true" style="opacity: .5">Previous</span>
                @else
                    <a class="trans-btn trans-btn--quiet trans-btn--small" href="{{ $entries->previousPageUrl() }}" rel="prev">Previous</a>
                @endif
                @if ($entries->hasMorePages())
                    <a class="trans-btn trans-btn--quiet trans-btn--small" href="{{ $entries->nextPageUrl() }}" rel="next">Next</a>
                @else
                    <span class="trans-btn trans-btn--quiet trans-btn--small" aria-disabled="true" style="opacity: .5">Next</span>
                @endif
            </nav>
        </div>
    @endif
@endsection
