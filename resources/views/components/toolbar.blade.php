{!! $marker !!}
<div class="trans-tb" id="trans-toolbar">
<style>
    /* Scoped to .trans-tb: safe inside any app. Override --trans-* to re-theme. */
    .trans-tb {
        --trans-bg: #ffffff; --trans-subtle: #f8f8fc; --trans-muted: #f0f0f7; --trans-line: #e4e4ef;
        --trans-ink: #16162a; --trans-text-2: #4b4b63; --trans-text-3: #74748b;
        --trans-accent: #3d4eff; --trans-accent-2: #a78bfa; --trans-accent-ink: #2b38d6; --trans-accent-soft: #eef0ff; --trans-on-accent: #ffffff;
        --trans-danger: #e5384f; --trans-danger-soft: #fdecef; --trans-warning: #b26a00; --trans-warning-soft: #fff4e0; --trans-success: #167a4a;
        --trans-sans: "Geist", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        --trans-mono: "Geist Mono", ui-monospace, "SF Mono", "Cascadia Code", Menlo, Consolas, monospace;
        all: initial; display: block; font: 14px/1.5 var(--trans-sans); color: var(--trans-ink); -webkit-font-smoothing: antialiased; color-scheme: light;
    }
    @media (prefers-color-scheme: dark) {
        .trans-tb {
            --trans-bg: #11111c; --trans-subtle: #171725; --trans-muted: #1f1f30; --trans-line: #2a2a3f;
            --trans-ink: #f1f1f8; --trans-text-2: #b6b6cc; --trans-text-3: #8787a3;
            --trans-accent: #8f9bff; --trans-accent-2: #c4b5fd; --trans-accent-ink: #b3bbff; --trans-accent-soft: #1e2150; --trans-on-accent: #0b0b1a;
            --trans-danger: #ff6b80; --trans-danger-soft: #331520; --trans-warning: #ffc266; --trans-warning-soft: #2e2210; --trans-success: #74d6a2;
            color-scheme: dark;
        }
    }
    .trans-tb *, .trans-tb *::before, .trans-tb *::after { box-sizing: border-box; font-family: inherit; letter-spacing: normal; }
    .trans-tb [hidden] { display: none !important; }
    .trans-tb :focus-visible { outline: 2px solid var(--trans-accent); outline-offset: 2px; }
    .trans-tb form { margin: 0; }
    .trans-tb a { color: var(--trans-accent); text-decoration: none; }
    .trans-tb a:hover { text-decoration: underline; text-underline-offset: 3px; }
    .trans-tb button { font: 500 13px/1 var(--trans-sans); cursor: pointer; border-radius: 8px; padding: 8px 12px; border: 1px solid var(--trans-accent); background: var(--trans-accent); color: var(--trans-on-accent); box-shadow: 0 6px 16px -8px color-mix(in srgb, var(--trans-accent) 70%, transparent); }
    .trans-tb button:hover { background: var(--trans-accent-ink); border-color: var(--trans-accent-ink); }
    .trans-tb .trans-tb-quiet { background: transparent; color: var(--trans-ink); border-color: var(--trans-line); box-shadow: none; }
    .trans-tb .trans-tb-quiet:hover { background: var(--trans-accent-soft); border-color: var(--trans-accent); color: var(--trans-accent-ink); }

    .trans-tb-toggle { position: fixed; right: 20px; bottom: 20px; z-index: 2147483000; }
    .trans-tb-toggle button { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px 10px 10px; border-radius: 999px; font-size: 13px; box-shadow: 0 12px 30px -12px color-mix(in srgb, var(--trans-accent) 80%, transparent); }
    .trans-tb-mark { display: grid; place-items: center; width: 22px; height: 22px; border-radius: 50%; background: color-mix(in srgb, #fff 22%, transparent); font-size: 12px; font-weight: 700; }

    .trans-tb-panel { position: fixed; z-index: 2147483000; top: 12px; right: 12px; bottom: 12px; width: min(27rem, calc(100vw - 24px)); display: flex; flex-direction: column; background: var(--trans-bg); border: 1px solid var(--trans-line); border-radius: 14px; box-shadow: 0 30px 80px -30px color-mix(in srgb, var(--trans-accent) 55%, transparent), 0 2px 8px rgb(0 0 0 / .06); overflow: hidden; }
    .trans-tb-panel::before { content: ""; display: block; height: 3px; flex: none; background: linear-gradient(90deg, var(--trans-accent), var(--trans-accent-2)); }
    .trans-tb-head { display: flex; align-items: start; gap: 12px; padding: 14px 16px 12px; border-bottom: 1px solid var(--trans-line); }
    .trans-tb-head h2 { all: unset; display: block; font-size: 15px; font-weight: 600; letter-spacing: -.01em; color: var(--trans-ink); }
    .trans-tb-head p { margin: 2px 0 0; font-size: 12.5px; color: var(--trans-text-3); }
    .trans-tb-head form { margin-left: auto; }
    .trans-tb-search { padding: 10px 16px; border-bottom: 1px solid var(--trans-line); background: var(--trans-subtle); }
    .trans-tb input[type=search], .trans-tb textarea { display: block; width: 100%; font: 13.5px/1.5 var(--trans-sans); color: var(--trans-ink); background: var(--trans-bg); border: 1px solid var(--trans-line); border-radius: 8px; padding: 7px 10px; margin: 0; }
    .trans-tb input[type=search]:focus, .trans-tb textarea:focus { outline: none; border-color: var(--trans-accent); box-shadow: 0 0 0 3px var(--trans-accent-soft); }
    .trans-tb textarea { min-height: 38px; resize: vertical; field-sizing: content; }
    .trans-tb-list { list-style: none; margin: 0; padding: 0; overflow-y: auto; flex: 1; overscroll-behavior: contain; }
    .trans-tb-item { padding: 12px 16px 14px; border-bottom: 1px solid var(--trans-line); }
    .trans-tb-item:focus-within { background: color-mix(in srgb, var(--trans-accent-soft) 45%, transparent); }
    .trans-tb-item label { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin: 0 0 6px; font-size: 12px; font-weight: 500; color: var(--trans-text-2); }
    .trans-tb-item code { font: 12px/1.4 var(--trans-mono); color: var(--trans-ink); overflow-wrap: anywhere; background: none; padding: 0; }
    .trans-tb-source { margin: 0 0 6px; font-size: 12.5px; color: var(--trans-text-3); white-space: pre-wrap; overflow-wrap: anywhere; }
    .trans-tb-source::before { content: attr(data-label) " · "; font-weight: 500; }
    .trans-tb-chip { display: inline-block; border-radius: 999px; padding: 1px 8px; font-size: 11px; font-weight: 500; line-height: 1.6; background: var(--trans-danger-soft); color: var(--trans-danger); }
    .trans-tb-chip--changed { background: var(--trans-warning-soft); color: var(--trans-warning); }
    .trans-tb-warnings { list-style: none; margin: 6px 0 0; padding: 6px 9px; border-radius: 8px; background: var(--trans-warning-soft); color: var(--trans-warning); font-size: 12px; }
    .trans-tb-row { display: flex; align-items: center; gap: 10px; margin-top: 8px; font-size: 12px; }
    .trans-tb-row a { color: var(--trans-text-3); margin-right: auto; }
    .trans-tb-row span { color: var(--trans-text-3); }
    .trans-tb-row span.is-error { color: var(--trans-danger); }
    .trans-tb-row button { padding: 6px 10px; font-size: 12px; }
    .trans-tb-item:not(:focus-within) .trans-tb-row button { background: transparent; color: var(--trans-text-2); border-color: var(--trans-line); box-shadow: none; }
    .trans-tb-empty { padding: 28px 18px; color: var(--trans-text-3); font-size: 13px; text-align: center; }
    .trans-tb-empty strong { display: block; color: var(--trans-ink); margin-bottom: 4px; font-size: 14px; }
    .trans-tb-foot { display: flex; gap: 14px; padding: 10px 16px; border-top: 1px solid var(--trans-line); background: var(--trans-subtle); font-size: 12.5px; }
    @media (max-width: 40rem) {
        .trans-tb-panel { top: auto; left: 8px; right: 8px; bottom: 8px; width: auto; height: 72vh; }
        .trans-tb-toggle { right: 12px; bottom: 12px; }
    }
</style>

@if (! $editMode)
    <form class="trans-tb-toggle" method="post" action="{{ route('translations.edit-mode') }}">
        @csrf
        <input type="hidden" name="on" value="1">
        <button type="submit" title="List the strings on this page and edit them"><span class="trans-tb-mark" aria-hidden="true">T</span>Edit translations</button>
    </form>
@else
    <aside class="trans-tb-panel" aria-labelledby="trans-tb-title">
        <div class="trans-tb-head">
            <div>
                <h2 id="trans-tb-title">Translations on this page</h2>
                <p>
                    {{ count($entries) }} {{ count($entries) === 1 ? 'string' : 'strings' }} in {{ $localeName }}@if ($missing > 0) · <span style="color: var(--trans-danger)">{{ $missing }} missing</span>@endif
                </p>
            </div>
            <form method="post" action="{{ route('translations.edit-mode') }}">
                @csrf
                <input type="hidden" name="on" value="0">
                <button type="submit" class="trans-tb-quiet">Done</button>
            </form>
        </div>

        @if (! $knownLocale)
            <div class="trans-tb-empty">
                <strong>“{{ $locale }}” isn't one of your languages.</strong>
                <a href="{{ route('translations.index') }}">Add it</a> to translate this page.
            </div>
        @elseif ($entries === [])
            <div class="trans-tb-empty">
                <strong>No strings on this page.</strong>
                Text shows up here when the page uses <code>__()</code>, <code>trans()</code> or <code>@@lang</code>.
            </div>
        @else
            <div class="trans-tb-search" data-trans-tb-js hidden>
                <label for="trans-tb-filter" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">Filter strings</label>
                <input id="trans-tb-filter" type="search" placeholder="Filter by key or text…" autocomplete="off">
            </div>
            <ol class="trans-tb-list">
                @foreach ($entries as $entry)
                    <li class="trans-tb-item" data-search="{{ mb_strtolower($entry->key->full().' '.$entry->value().' '.$entry->source) }}">
                        <form method="post" action="{{ route('translations.entries.update', $locale) }}" data-trans-tb-entry>
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="namespace" value="{{ $entry->key->namespace }}">
                            <input type="hidden" name="group" value="{{ $entry->key->group }}">
                            <input type="hidden" name="key" value="{{ $entry->key->item }}">
                            <label for="trans-tb-{{ $entry->id() }}">
                                <code>{{ $entry->key->full() }}</code>
                                @if ($entry->isMissing())
                                    <span class="trans-tb-chip">Missing</span>
                                @elseif ($entry->isPending())
                                    <span class="trans-tb-chip trans-tb-chip--changed">Not exported</span>
                                @endif
                            </label>
                            @if (! $isSource && $entry->source !== null && ! $entry->key->isJson())
                                <p class="trans-tb-source" data-label="{{ $sourceName }}">{{ $entry->source }}</p>
                            @endif
                            <textarea id="trans-tb-{{ $entry->id() }}" name="value" rows="1" lang="{{ $locale }}" data-source="{{ $entry->source }}" @if ($entry->file !== null) @if ($entry->file !== null) data-file="{{ $entry->file }}" @endif @endif>{{ $entry->value() }}</textarea>
                            @php($warnings = $entry->warnings())
                            <ul class="trans-tb-warnings" @if ($warnings === []) hidden @endif aria-live="polite">
                                @foreach ($warnings as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                            <div class="trans-tb-row">
                                <a href="{{ route('translations.editor', ['locale' => $locale, 'q' => $entry->key->item]) }}#{{ $entry->id() }}">Open in editor</a>
                                <span aria-live="polite"></span>
                                <button type="submit">Save</button>
                            </div>
                        </form>
                    </li>
                @endforeach
            </ol>
        @endif

        <div class="trans-tb-foot">
            <a href="{{ route('translations.editor', $locale) }}">All {{ $localeName }} strings</a>
            <a href="{{ route('translations.changes') }}">Pending changes</a>
        </div>
    </aside>
    @include('translations::partials.check')
    <script>
    (() => {
        const panel = document.getElementById('trans-toolbar');
        if (!panel || panel.dataset.ready) return;
        panel.dataset.ready = '1';
        const check = window.RuveloTranslations.check;

        panel.querySelectorAll('[data-trans-tb-js]').forEach(el => { el.hidden = false; });
        const filter = panel.querySelector('#trans-tb-filter');
        filter?.addEventListener('input', () => {
            const q = filter.value.trim().toLowerCase();
            panel.querySelectorAll('.trans-tb-item').forEach(item => { item.hidden = q !== '' && !item.dataset.search.includes(q); });
        });

        panel.querySelectorAll('form[data-trans-tb-entry]').forEach(form => {
            const textarea = form.querySelector('textarea');
            const list = form.querySelector('.trans-tb-warnings');
            const state = form.querySelector('.trans-tb-row span');
            textarea.addEventListener('input', () => {
                const warnings = check(textarea.dataset.source || null, textarea.value);
                list.replaceChildren(...warnings.map(w => Object.assign(document.createElement('li'), { textContent: w })));
                list.hidden = warnings.length === 0;
            });
            textarea.addEventListener('keydown', event => {
                if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); form.requestSubmit(); }
            });
            form.addEventListener('submit', async event => {
                event.preventDefault();
                state.textContent = 'Saving…';
                state.className = '';
                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        body: new FormData(form),
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(data.message || 'That didn’t save. Try again.');
                    // Apps that re-render without a reload (Livewire, Inertia) can
                    // listen for this event and call preventDefault().
                    const saved = new CustomEvent('translations:saved', { bubbles: true, cancelable: true, detail: { entry: data.entry, textarea } });
                    if (!panel.dispatchEvent(saved)) {
                        state.textContent = 'Saved';
                        return;
                    }
                    state.textContent = 'Saved. Reloading…';
                    // Remember where we were, then show the page with the new text.
                    try { sessionStorage.setItem('trans-tb-focus', textarea.id); sessionStorage.setItem('trans-tb-scroll', panel.querySelector('.trans-tb-list').scrollTop); } catch (e) {}
                    location.reload();
                } catch (error) {
                    state.textContent = error.message;
                    state.className = 'is-error';
                }
            });
        });

        try {
            const list = panel.querySelector('.trans-tb-list');
            const scroll = sessionStorage.getItem('trans-tb-scroll');
            if (list && scroll) list.scrollTop = +scroll;
            const focus = sessionStorage.getItem('trans-tb-focus');
            if (focus) document.getElementById(focus)?.focus({ preventScroll: true });
            sessionStorage.removeItem('trans-tb-scroll');
            sessionStorage.removeItem('trans-tb-focus');
        } catch (e) {}
    })();
    </script>
@endif
</div>
