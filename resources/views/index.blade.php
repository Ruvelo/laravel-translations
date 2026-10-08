@extends('translations::'.\Ruvelo\Translations\Translations::layout())

@section('title', 'Languages')

@section('translations')
    @php
        $sourceProgress = $locales->firstWhere('code', $source)['progress'] ?? null;
        $initials = fn (string $name) => collect(explode(' ', $name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('');
        $status = fn ($progress) => match (true) {
            $progress->isSource => ['Source', 'source'],
            $progress->isComplete() => ['Complete', 'done'],
            $progress->percent() >= 90 => ['Almost there', 'close'],
            $progress->percent() >= 50 => ['In progress', 'going'],
            default => ['Just started', 'early'],
        };
        // Ring geometry: r = 26, so the circumference is 2πr.
        $circumference = 2 * M_PI * 26;
    @endphp

    <div class="trans-head">
        <div>
            <h1>Languages</h1>
            <p class="trans-lede">
                {{ number_format($sourceProgress?->total ?? 0) }} {{ ($sourceProgress?->total ?? 0) === 1 ? 'string' : 'strings' }}, written first in {{ \Ruvelo\Translations\Translations::localeName($source) }}.
            </p>
        </div>
    </div>

    <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
        <defs>
            <linearGradient id="trans-ring-gradient" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" style="stop-color: var(--trans-accent)"/>
                <stop offset="1" style="stop-color: var(--trans-accent-2)"/>
            </linearGradient>
        </defs>
    </svg>

    <section class="trans-summary" aria-label="Summary">
        <div class="trans-summary-ring">
            <svg viewBox="0 0 64 64" aria-hidden="true">
                <circle class="trans-ring-track" cx="32" cy="32" r="26"/>
                <circle class="trans-ring-value" cx="32" cy="32" r="26" stroke-dasharray="{{ round($circumference * $overall['percent'] / 100, 2) }} {{ round($circumference, 2) }}"/>
            </svg>
            <span><strong>{{ $overall['percent'] }}<small>%</small></strong>translated</span>
        </div>
        <dl class="trans-summary-stats">
            <div><dt>Languages</dt><dd>{{ $overall['languages'] }}</dd></div>
            <div><dt>Strings</dt><dd>{{ number_format($sourceProgress?->total ?? 0) }}</dd></div>
            <div><dt>Missing</dt><dd @class(['is-missing' => $overall['missing'] > 0])>{{ number_format($overall['missing']) }}</dd></div>
            <div><dt>Not exported</dt><dd @class(['is-changed' => $pending > 0])>{{ number_format($pending) }}</dd></div>
        </dl>
        @if ($pending > 0)
            <div class="trans-summary-action">
                <p>{{ $pending === 1 ? 'One edit is' : $pending.' edits are' }} live on the site but not in your lang files yet.</p>
                <a class="trans-btn" href="{{ route('translations.changes') }}">Review changes</a>
            </div>
        @else
            <div class="trans-summary-action">
                <p>Your lang files hold every edit. Nothing to export.</p>
            </div>
        @endif
    </section>

    <ul class="trans-langs">
        @foreach ($locales as $locale)
            @php
                $progress = $locale['progress'];
                [$label, $tone] = $status($progress);
                $percent = $progress->percent();
                $done = max(0, $progress->translated - $progress->pending);
                $width = fn (int $count) => $progress->total > 0 ? round($count / $progress->total * 100, 2) : 0;
            @endphp
            <li class="trans-lang trans-lang--{{ $tone }}">
                <a class="trans-lang-link" href="{{ route('translations.editor', $locale['code']) }}" aria-label="Open {{ $locale['name'] }}">
                    <span class="trans-lang-ring">
                        <svg viewBox="0 0 64 64" aria-hidden="true">
                            <circle class="trans-ring-track" cx="32" cy="32" r="26"/>
                            <circle class="trans-ring-value" cx="32" cy="32" r="26" stroke-dasharray="{{ round($circumference * $percent / 100, 2) }} {{ round($circumference, 2) }}"/>
                        </svg>
                        <span>{{ $percent }}<small>%</small></span>
                    </span>
                    <span class="trans-lang-name">
                        <strong>@if ($flag = \Ruvelo\Translations\Support\Flags::for($locale['code']))<img class="trans-flag" src="{{ $flag }}" alt="" width="20" height="20">@endif{{ $locale['name'] }}</strong>
                        <span class="trans-lang-tags">
                            <span class="trans-code">{{ $locale['code'] }}</span>
                            <span class="trans-status trans-status--{{ $tone }}">{{ $label }}</span>
                        </span>
                    </span>
                </a>

                <div class="trans-segments" role="img" aria-label="{{ number_format($done) }} translated, {{ number_format($progress->pending) }} not exported, {{ number_format($progress->missing()) }} missing">
                    <span class="seg-done" style="width: {{ $width($done) }}%"></span>
                    <span class="seg-pending" style="width: {{ $width($progress->pending) }}%"></span>
                    <span class="seg-missing" style="width: {{ $width($progress->missing()) }}%"></span>
                </div>
                <p class="trans-legend">
                    <span><i class="seg-done"></i>{{ number_format($done) }} done</span>
                    @if ($progress->pending > 0)<span><i class="seg-pending"></i>{{ number_format($progress->pending) }} not exported</span>@endif
                    @if ($progress->missing() > 0)<span><i class="seg-missing"></i>{{ number_format($progress->missing()) }} missing</span>@endif
                </p>

                <div class="trans-lang-actions">
                    @if (! $progress->isSource && $progress->missing() > 0)
                        <a class="trans-btn trans-btn--small" href="{{ route('translations.editor', ['locale' => $locale['code'], 'filter' => 'missing']) }}">Translate {{ number_format($progress->missing()) }} missing</a>
                    @endif
                    <a class="trans-btn trans-btn--quiet trans-btn--small" href="{{ route('translations.editor', $locale['code']) }}">{{ $progress->isSource ? 'Edit source text' : 'Open' }}</a>
                </div>
            </li>
        @endforeach
    </ul>

    <div class="trans-split">
        <section class="trans-panel" aria-labelledby="trans-recent-title">
            <h2 id="trans-recent-title">Recent edits</h2>
            @if ($recent === [])
                <p class="trans-empty">No edits waiting for export. Changes made here or in context will show up as they happen.</p>
            @else
                <ol class="trans-feed">
                    @foreach ($recent as $entry)
                        @php($editor = $editors[$entry->override?->updated_by ?? ''] ?? null)
                        <li>
                            <span class="trans-avatar" aria-hidden="true">{{ $editor !== null ? $initials($editor) : '?' }}</span>
                            <div>
                                <p><strong>{{ $editor ?? 'Someone' }}</strong> changed <a href="{{ route('translations.editor', ['locale' => $entry->locale, 'q' => $entry->key->item]) }}#{{ $entry->id() }}"><code>{{ $entry->key->item }}</code></a> in @if ($flag = \Ruvelo\Translations\Support\Flags::for($entry->locale))<img class="trans-flag trans-flag--small" src="{{ $flag }}" alt="" width="14" height="14">@endif{{ \Ruvelo\Translations\Translations::localeName($entry->locale) }}</p>
                                <p class="trans-feed-value">{{ \Illuminate\Support\Str::limit((string) $entry->value(), 90) }}</p>
                                @if ($entry->warnings() !== [])
                                    <p class="trans-feed-warn">{{ $entry->warnings()[0] }}</p>
                                @endif
                            </div>
                            @if ($entry->override?->updated_at)
                                <time datetime="{{ $entry->override->updated_at->toIso8601String() }}">{{ $entry->override->updated_at->diffForHumans(short: true) }}</time>
                            @endif
                        </li>
                    @endforeach
                </ol>
                @if ($pending > count($recent))
                    <a class="trans-more" href="{{ route('translations.changes') }}">See all {{ number_format($pending) }} changes</a>
                @endif
            @endif
        </section>

        <section class="trans-panel trans-panel--add" aria-labelledby="trans-add-title">
            <h2 id="trans-add-title">Add a language</h2>
            <form method="post" action="{{ route('translations.locales.store') }}">
                @csrf
                <label for="trans-new-locale">Its code, as in your lang folder: <code>it</code>, <code>pt_BR</code>, <code>zh-Hant</code>.</label>
                <div class="trans-add-row">
                    <input id="trans-new-locale" type="text" name="code" value="{{ old('code') }}" placeholder="it" required maxlength="20" autocomplete="off" spellcheck="false" @error('code') aria-invalid="true" aria-describedby="trans-new-locale-error" @enderror>
                    <button type="submit">Add</button>
                </div>
                @error('code')
                    <p class="trans-error" id="trans-new-locale-error">{{ $message }}</p>
                @enderror
            </form>
            <ul class="trans-hints">
                <li><strong>Edit in context.</strong> Turn on <em>Edit translations</em> on any page of your app to fix the strings you see.</li>
                <li><strong>Find missing keys.</strong> <code>php artisan translations:scan</code></li>
                <li><strong>Commit your edits.</strong> <code>php artisan translations:export</code></li>
            </ul>
        </section>
    </div>
@endsection
