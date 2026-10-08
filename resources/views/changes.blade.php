@extends('translations::'.\Ruvelo\Translations\Translations::layout())

@section('title', 'Pending changes')

@section('translations')
    <div class="trans-head">
        <div>
            <h1>Pending changes</h1>
            <p class="trans-lede">Edits made here already show on the site. They're not in your lang files until you export them, so they won't reach your repository or your other environments until then.</p>
        </div>
    </div>

    @if ($count === 0)
        <div class="trans-table-wrap">
            <div class="trans-empty">
                <strong>No pending changes.</strong>
                Everything the site shows is in your lang files. <a href="{{ route('translations.index') }}">Pick a language to translate</a>
            </div>
        </div>
    @else
        <div class="trans-callout">
            <div>
                <strong>{{ $count }} {{ $count === 1 ? 'change' : 'changes' }} to export</strong>
                <p>Run this where your code lives, check the diff, then commit:</p>
                <div class="trans-commands"><span>$</span> php artisan translations:export --dry-run
<span>$</span> php artisan translations:export</div>
            </div>
        </div>

        @foreach ($groups as $locale => $entries)
            <section class="trans-section" aria-labelledby="trans-changes-{{ $locale }}">
                <h2 id="trans-changes-{{ $locale }}">{{ \Ruvelo\Translations\Translations::localeName($locale) }} <span class="trans-chip trans-chip--quiet">{{ $locale }}</span> <span class="trans-chip">{{ $entries->count() }}</span></h2>
                <ul class="trans-changes">
                    @foreach ($entries as $entry)
                        @php($editor = $editors[$entry->override?->updated_by ?? ''] ?? null)
                        <li class="trans-change" id="{{ $entry->id() }}">
                            <div>
                                <code class="trans-key">{{ $entry->key->item }}</code>
                                <div class="trans-file"><span class="trans-chip trans-chip--quiet">{{ $entry->key->file() === '*' ? 'JSON' : $entry->key->file() }}</span></div>
                            </div>
                            <div class="trans-diff">
                                <div>
                                    <span class="trans-diff-label">In the file</span>
                                    @if ($entry->file === null)
                                        <del class="is-none">Not there yet</del>
                                    @else
                                        <del>{{ $entry->file }}</del>
                                    @endif
                                </div>
                                <div>
                                    <span class="trans-diff-label">On the site</span>
                                    <ins>{{ $entry->value() }}</ins>
                                </div>
                            </div>
                            <div class="trans-change-tools">
                                <a class="trans-btn trans-btn--quiet trans-btn--small" href="{{ route('translations.editor', ['locale' => $locale, 'q' => $entry->key->item]) }}#{{ $entry->id() }}">Edit</a>
                                <form method="post" action="{{ route('translations.entries.destroy', $locale) }}" data-trans-revert-change>
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="namespace" value="{{ $entry->key->namespace }}">
                                    <input type="hidden" name="group" value="{{ $entry->key->group }}">
                                    <input type="hidden" name="key" value="{{ $entry->key->item }}">
                                    <button type="submit" class="trans-btn--danger trans-btn--small">Revert</button>
                                </form>
                            </div>
                            <div class="trans-change-meta">
                                @if ($editor !== null)
                                    <span><span class="trans-avatar" aria-hidden="true">{{ collect(explode(' ', $editor))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}</span>{{ $editor }}</span>
                                @endif
                                @if ($entry->override)
                                    <time datetime="{{ $entry->override->updated_at->toIso8601String() }}">{{ $entry->override->updated_at->copy()->locale('en')->diffForHumans() }}</time>
                                @endif
                                @if ($entry->fileChangedSinceEdit())
                                    <span class="is-warning">The lang file changed after this edit. Check which text is right.</span>
                                @endif
                                @foreach ($entry->warnings() as $warning)
                                    <span class="is-warning">{{ $warning }}</span>
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
@endsection
