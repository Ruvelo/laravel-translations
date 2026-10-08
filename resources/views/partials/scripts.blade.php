@include('translations::partials.check')
<script>
(() => {
    const root = document.getElementById('translations');
    if (!root || root.dataset.ready) return;
    root.dataset.ready = '1';

    root.querySelectorAll('[data-trans-js]').forEach(el => { el.hidden = false; });
    root.querySelectorAll('[data-trans-nojs]').forEach(el => { el.hidden = true; });
    root.querySelectorAll('select[data-trans-autosubmit]').forEach(select => {
        select.addEventListener('change', () => select.form.submit());
    });

    const check = window.RuveloTranslations.check;

    const send = async (url, form, method, extra = {}) => {
        const body = new FormData(form);
        body.set('_method', method);
        for (const [k, v] of Object.entries(extra)) body.set(k, v);
        const response = await fetch(url, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body,
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const first = data.errors ? Object.values(data.errors)[0] : null;
            throw new Error((first && first[0]) || data.message || (response.status === 419 ? 'Your session expired. Reload the page.' : 'That didn’t save. Try again.'));
        }
        return data;
    };

    const autosize = textarea => {
        if (CSS.supports && CSS.supports('field-sizing', 'content')) return;
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight + 2) + 'px';
    };

    // The editor table: save on blur, Enter to the next string, Esc to undo.
    const forms = [...root.querySelectorAll('form[data-trans-entry]')];
    const textareas = forms.map(form => form.querySelector('textarea'));

    forms.forEach((form, index) => {
        const row = form.closest('tr');
        const textarea = textareas[index];
        const state = form.querySelector('.trans-state');
        const badge = form.querySelector('[data-trans-badge]');
        const list = form.querySelector('.trans-warnings');
        const revert = form.querySelector('[data-trans-revert]');
        const suggest = form.querySelector('[data-trans-suggest]');
        const suggestion = form.querySelector('.trans-suggestion');
        let saved = textarea.value;
        let saving = null;

        form.querySelector('[data-trans-save]').hidden = true;
        if (suggest && form.dataset.suggest) suggest.hidden = false;

        const say = (text, kind = '') => {
            state.textContent = text;
            state.className = 'trans-state' + (kind ? ' is-' + kind : '');
        };
        const showWarnings = warnings => {
            list.replaceChildren(...warnings.map(w => Object.assign(document.createElement('li'), { textContent: w })));
            list.hidden = warnings.length === 0;
        };
        const showStatus = entry => {
            row.dataset.status = entry.status;
            badge.hidden = entry.status === 'translated';
            badge.textContent = entry.status === 'missing' ? 'Missing' : 'Not exported';
            badge.className = 'trans-chip ' + (entry.status === 'missing' ? 'trans-chip--missing' : 'trans-chip--changed');
            revert.hidden = !entry.pending;
            textarea.placeholder = entry.status === 'missing' ? textarea.placeholder || 'Missing' : '';
        };
        const live = () => showWarnings(check(textarea.dataset.source || null, textarea.value));

        const save = () => {
            if (textarea.value === saved) return Promise.resolve();
            const value = textarea.value;
            say('Saving…');
            saving = send(form.action, form, 'PUT', { value })
                .then(data => {
                    saved = value;
                    showStatus(data.entry);
                    showWarnings(data.entry.warnings || []);
                    say('Saved', 'saved');
                    setTimeout(() => { if (state.textContent === 'Saved') say(''); }, 2500);
                })
                .catch(error => say(error.message, 'error'))
                .finally(() => { saving = null; });
            return saving;
        };

        textarea.addEventListener('input', () => { live(); autosize(textarea); if (state.classList.contains('is-error')) say(''); });
        textarea.addEventListener('focus', () => row.classList.add('is-focus'));
        textarea.addEventListener('blur', () => { row.classList.remove('is-focus'); save(); });
        textarea.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                save();
                const next = textareas[index + 1];
                if (next) { next.focus(); next.setSelectionRange(next.value.length, next.value.length); }
                else textarea.blur();
            } else if (event.key === 'Escape') {
                textarea.value = saved;
                live();
                autosize(textarea);
                say('Undone');
            }
        });
        autosize(textarea);

        revert.addEventListener('click', event => {
            event.preventDefault();
            say('Reverting…');
            send(form.getAttribute('action'), form, 'DELETE')
                .then(data => {
                    textarea.value = saved = data.entry.value ?? '';
                    showStatus(data.entry);
                    showWarnings(data.entry.warnings || []);
                    autosize(textarea);
                    say('Back to the lang file', 'saved');
                })
                .catch(error => say(error.message, 'error'));
        });

        suggest?.addEventListener('click', () => {
            say('Drafting…');
            send(form.dataset.suggest, form, 'POST')
                .then(data => {
                    say('');
                    const label = Object.assign(document.createElement('small'), { textContent: 'Suggested. Check it, then use it or not.' });
                    const text = Object.assign(document.createElement('p'), { textContent: data.suggestion });
                    const use = Object.assign(document.createElement('button'), { type: 'button', className: 'trans-btn trans-btn--small', textContent: 'Use this' });
                    const dismiss = Object.assign(document.createElement('button'), { type: 'button', className: 'trans-link', textContent: 'Dismiss' });
                    use.addEventListener('click', () => {
                        textarea.value = data.suggestion;
                        suggestion.hidden = true;
                        live();
                        autosize(textarea);
                        textarea.focus();
                    });
                    dismiss.addEventListener('click', () => { suggestion.hidden = true; });
                    const warnings = (data.warnings || []).map(w => Object.assign(document.createElement('li'), { textContent: w }));
                    const extra = warnings.length ? [Object.assign(document.createElement('ul'), { className: 'trans-warnings' })] : [];
                    extra.forEach(ul => ul.append(...warnings));
                    const buttons = document.createElement('div');
                    buttons.style.cssText = 'display:flex;gap:.75rem;align-items:center';
                    buttons.append(use, dismiss);
                    suggestion.replaceChildren(label, text, ...extra, buttons);
                    suggestion.hidden = false;
                })
                .catch(error => say(error.message, 'error'));
        });

        // Don't lose an edit to a page change mid-save.
        form.addEventListener('submit', event => { event.preventDefault(); save(); });
    });

    // Pending changes: revert without leaving the page.
    root.querySelectorAll('form[data-trans-revert-change]').forEach(form => {
        form.addEventListener('submit', event => {
            event.preventDefault();
            const item = form.closest('li');
            send(form.action, form, 'DELETE')
                .then(() => {
                    const list = item.parentElement;
                    item.remove();
                    const section = list.closest('section');
                    const chip = section.querySelector('h2 .trans-chip:last-child');
                    if (chip) chip.textContent = list.children.length;
                    if (!list.children.length) section.remove();
                })
                .catch(error => alert(error.message));
        });
    });

    // Open the row a link pointed at.
    if (/^#t[0-9a-f]{12}$/.test(location.hash)) {
        const row = root.querySelector(location.hash + ' textarea');
        if (row) { row.focus({ preventScroll: true }); row.closest('tr').scrollIntoView({ block: 'center' }); }
    }
})();
</script>
