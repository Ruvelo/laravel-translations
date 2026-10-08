@extends('translations::'.\Ruvelo\Translations\Translations::layout())

@section('title', 'Languages')

@section('translations')
    @php($sourceProgress = $locales->firstWhere('code', $source)['progress'] ?? null)

    <div class="trans-head">
        <div>
            <h1>Languages</h1>
            <p class="trans-lede">
                {{ number_format($sourceProgress?->total ?? 0) }} {{ ($sourceProgress?->total ?? 0) === 1 ? 'string' : 'strings' }} in {{ $locales->count() }} {{ $locales->count() === 1 ? 'language' : 'languages' }}, written first in {{ \Ruvelo\Translations\Translations::localeName($source) }}.
            </p>
        </div>
    </div>

    @if ($pending > 0)
        <div class="trans-callout">
            <div>
                <strong>{{ $pending }} {{ $pending === 1 ? 'change is' : 'changes are' }} not in your lang files yet</strong>
                <p>They already show on the site. Export them to commit: <code>php artisan translations:export</code></p>
            </div>
            <a class="trans-btn" href="{{ route('translations.changes') }}">Review changes</a>
        </div>
    @endif

    <ul class="trans-cards">
        @foreach ($locales as $locale)
            @php($progress = $locale['progress'])
            <li>
                <a class="trans-card" href="{{ route('translations.editor', $locale['code']) }}">
                    <span class="trans-card-top">
                        <strong>{{ $locale['name'] }}</strong>
                        @if ($progress->isSource)
                            <span class="trans-chip">Source</span>
                        @endif
                        <span class="trans-chip trans-chip--quiet">{{ $locale['code'] }}</span>
                    </span>
                    <span class="trans-percent">{{ $progress->percent() }}<small>%</small></span>
                    <span class="trans-meter @if ($progress->isComplete()) trans-meter--done @endif" role="progressbar" aria-label="{{ $locale['name'] }} translated" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress->percent() }}"><span style="width: {{ $progress->percent() }}%"></span></span>
                    <span class="trans-card-meta">
                        @if ($progress->isComplete())
                            <span>All {{ number_format($progress->total) }} translated</span>
                        @else
                            <span class="is-missing">{{ number_format($progress->missing()) }} missing</span>
                            <span>{{ number_format($progress->translated) }} of {{ number_format($progress->total) }} done</span>
                        @endif
                        @if ($progress->pending > 0)
                            <span class="is-changed">{{ $progress->pending }} not exported</span>
                        @endif
                    </span>
                </a>
            </li>
        @endforeach
        <li>
            <div class="trans-card trans-card--add">
                <form method="post" action="{{ route('translations.locales.store') }}">
                    @csrf
                    <label for="trans-new-locale">Add a language</label>
                    <p>Its code, as in your lang folder: <code>it</code>, <code>pt_BR</code>, <code>zh-Hant</code>.</p>
                    <input id="trans-new-locale" type="text" name="code" value="{{ old('code') }}" placeholder="Locale code" required maxlength="20" autocomplete="off" spellcheck="false" @error('code') aria-invalid="true" aria-describedby="trans-new-locale-error" @enderror>
                    @error('code')
                        <p class="trans-error" id="trans-new-locale-error">{{ $message }}</p>
                    @enderror
                    <button type="submit">Add language</button>
                </form>
            </div>
        </li>
    </ul>

    <div class="trans-tips">
        <section class="trans-tip">
            <h2>Fix text where you see it</h2>
            <p>On any page of your app, turn on <strong>Edit translations</strong> in the corner. A panel lists every string on that page, ready to edit.</p>
        </section>
        <section class="trans-tip">
            <h2>Your lang files stay in charge</h2>
            <p>Edits apply at once without touching the server's files. <code>translations:export</code> writes them into <code>lang/</code> so you can commit them.</p>
        </section>
        <section class="trans-tip">
            <h2>Find strings nobody added</h2>
            <p><code>php artisan translations:scan</code> lists keys your code uses that the source language doesn't have yet.</p>
        </section>
    </div>
@endsection
