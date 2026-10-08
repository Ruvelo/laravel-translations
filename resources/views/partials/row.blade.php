@php
    $value = $entry->value();
    $warnings = $entry->warnings();
    $label = $entry->key->file() === '*' ? 'JSON' : $entry->key->file();
@endphp
<tr id="{{ $entry->id() }}" data-status="{{ $entry->status() }}">
    <td>
        <code class="trans-key">{{ $entry->key->item }}</code>
        <div class="trans-file"><span class="trans-chip trans-chip--quiet">{{ $label }}</span></div>
    </td>
    @unless ($isSource)
        <td>
            @if ($entry->source === null)
                <div class="trans-source trans-source--none">Not in {{ $sourceName }}</div>
            @else
                <div class="trans-source" lang="{{ $source }}">{{ $entry->source }}</div>
            @endif
        </td>
    @endunless
    <td class="trans-cell">
        <form method="post" action="{{ route('translations.entries.update', $locale) }}" data-trans-entry data-suggest="{{ $canSuggest && $entry->source !== null ? route('translations.suggest', $locale) : '' }}">
            @csrf
            <input type="hidden" name="namespace" value="{{ $entry->key->namespace }}">
            <input type="hidden" name="group" value="{{ $entry->key->group }}">
            <input type="hidden" name="key" value="{{ $entry->key->item }}">
            <label class="trans-sr" for="{{ $entry->id() }}-value">{{ $localeName }} for {{ $entry->key->full() }}</label>
            <textarea id="{{ $entry->id() }}-value" name="value" rows="1" lang="{{ $locale }}" dir="{{ $dir }}" spellcheck="true" data-source="{{ $entry->source }}" @if ($entry->file !== null) @if ($entry->file !== null) data-file="{{ $entry->file }}" @endif @endif @if ($entry->isMissing()) placeholder="Missing: type the {{ $localeName }} text" @endif>{{ $value }}</textarea>
            <ul class="trans-warnings" @if ($warnings === []) hidden @endif aria-live="polite">
                @foreach ($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
            <div class="trans-suggestion" hidden></div>
            <div class="trans-cell-foot">
                @if ($entry->isMissing())
                    <span class="trans-chip trans-chip--missing" data-trans-badge>Missing</span>
                @elseif ($entry->isPending())
                    <span class="trans-chip trans-chip--changed" data-trans-badge>Not exported</span>
                @else
                    <span class="trans-chip trans-chip--changed" data-trans-badge hidden></span>
                @endif
                <span class="trans-state" aria-live="polite"></span>
                <span class="trans-actions">
                    @if ($canSuggest && $entry->source !== null)
                        <button type="button" class="trans-link" data-trans-suggest hidden>Suggest</button>
                    @endif
                    <button type="submit" class="trans-link" name="_method" value="DELETE" formaction="{{ route('translations.entries.destroy', $locale) }}" data-trans-revert @if ($entry->override === null) hidden @endif>Revert</button>
                    <button type="submit" class="trans-btn trans-btn--small" name="_method" value="PUT" data-trans-save>Save</button>
                </span>
            </div>
        </form>
    </td>
</tr>
