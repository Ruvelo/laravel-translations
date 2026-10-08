<header class="trans-bar">
    <a class="trans-brand" href="{{ route('translations.index') }}"><span class="trans-mark" aria-hidden="true">{{ mb_strtoupper(mb_substr(config('translations.name'), 0, 1)) }}</span>{{ config('translations.name') }}</a>
    <nav aria-label="Translations">
        <a href="{{ route('translations.index') }}" @if (request()->routeIs('translations.index', 'translations.editor')) aria-current="page" @endif>Languages</a>
        <a href="{{ route('translations.changes') }}" @if (request()->routeIs('translations.changes')) aria-current="page" @endif>
            Pending changes
            @if (($pendingCount ?? 0) > 0)
                <span class="trans-chip">{{ $pendingCount }}</span>
            @endif
        </a>
    </nav>
</header>

<main class="trans-page" id="translations">
    @if (session('translations.status'))
        <p class="trans-flash" role="status">{{ session('translations.status') }}</p>
    @endif

    @yield('translations')
</main>

<footer class="trans-foot">
    <div class="trans-foot-in">
        <span>Edits apply at once. Run <code>php artisan translations:export</code> to write them into your lang files.</span>
        <span>Powered by <a href="https://github.com/Ruvelo/laravel-translations">Laravel Translations</a> by Ruvelo</span>
    </div>
</footer>

@include('translations::partials.scripts')
