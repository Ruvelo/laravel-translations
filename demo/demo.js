// The demo is static files, so this script answers what the server would.
// Saving, reverting and filtering all work, in this browser only: nothing
// is stored, and a reload brings the demo back as it was.
(() => {
    const realFetch = window.fetch.bind(window);
    const json = (data, status = 200) => new Response(JSON.stringify(data), { status, headers: { 'Content-Type': 'application/json' } });
    const check = (source, value) => (window.RuveloTranslations && window.RuveloTranslations.check ? window.RuveloTranslations.check(source, value) : []);

    // The textarea a request is about, found by the key it sends.
    const findTextarea = (body) => {
        for (const form of document.querySelectorAll('form')) {
            const field = (name) => form.querySelector(`input[name="${name}"]`)?.value;
            if (field('key') === body.get('key') && field('group') === body.get('group') && field('namespace') === body.get('namespace')) {
                const textarea = form.querySelector('textarea');
                if (textarea) return textarea;
            }
        }
        return null;
    };

    const entryFor = (textarea, value) => {
        const file = textarea && textarea.hasAttribute('data-file') ? textarea.dataset.file : null;
        const source = textarea && textarea.hasAttribute('data-source') ? textarea.dataset.source : null;
        const missing = value === null || value.trim() === '';
        const pending = !missing && value !== file;
        return { value, file_value: file, status: missing ? 'missing' : (pending ? 'changed' : 'translated'), pending, warnings: missing ? [] : check(source, value) };
    };

    window.fetch = async (input, init = {}) => {
        const url = typeof input === 'string' ? input : input.url;
        if (!/\/entries$|\/suggest$|\/edit-mode$/.test(url) || !(init.body instanceof FormData)) {
            return realFetch(input, init);
        }

        await new Promise((resolve) => setTimeout(resolve, 250));
        const body = init.body;
        const textarea = findTextarea(body);

        if (url.endsWith('/suggest')) {
            return json({ message: 'Suggestions need the Laravel AI SDK; the demo has none.' }, 503);
        }
        if (url.endsWith('/edit-mode')) {
            return json({ on: body.get('on') === '1' });
        }
        if (body.get('_method') === 'DELETE') {
            const file = textarea && textarea.hasAttribute('data-file') ? textarea.dataset.file : null;
            return json({ entry: entryFor(textarea, file), message: 'Reverted to the lang file.' });
        }
        return json({ entry: entryFor(textarea, body.get('value') ?? ''), message: 'Saved.' });
    };

    const params = new URLSearchParams(location.search);

    document.addEventListener('DOMContentLoaded', () => {
        // The locale switcher submits to the overview with ?locale=xx.
        if (params.get('locale') && document.querySelector('.trans-cards')) {
            location.replace(location.pathname.replace(/\/?$/, '/') + encodeURIComponent(params.get('locale')) + '/');
            return;
        }

        // Adding a language needs a server.
        document.querySelectorAll('form[action$="/locales"]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                let note = form.querySelector('.trans-error');
                if (!note) {
                    note = Object.assign(document.createElement('p'), { className: 'trans-error' });
                    form.querySelector('input[name=code]').after(note);
                }
                note.textContent = 'The demo can’t add languages. Install the package and this creates one in a click.';
            });
        });

        filterEditor();
        inContext();
    });

    // The editor: ?filter=, ?q= and ?file= answered in the browser, since
    // every string of the language is on the page.
    function filterEditor() {
        const table = document.querySelector('[data-trans-editor]');
        if (!table) return;

        const filter = params.get('filter') || 'all';
        const query = (params.get('q') || '').trim().toLowerCase();
        const file = params.get('file') || '';
        const rows = [...table.querySelectorAll('tbody tr')];

        const matchesSearch = (row) => !query || row.textContent.toLowerCase().includes(query) || row.querySelector('textarea').value.toLowerCase().includes(query);
        const matchesFile = (row) => {
            if (!file) return true;
            const chip = row.querySelector('.trans-file .trans-chip').textContent.trim();
            return (file === '*' ? 'JSON' : file) === chip;
        };
        const matchesFilter = (row, name) => {
            if (name === 'missing') return row.dataset.status === 'missing';
            if (name === 'changed') return row.dataset.status === 'changed';
            if (name === 'warnings') return !row.querySelector('.trans-warnings').hidden;
            return true;
        };

        const base = rows.filter((row) => matchesSearch(row) && matchesFile(row));
        rows.forEach((row) => { row.hidden = !(base.includes(row) && matchesFilter(row, filter)); });

        document.querySelectorAll('.trans-tabs a').forEach((tab) => {
            const name = new URL(tab.href).searchParams.get('filter') || 'all';
            tab.toggleAttribute('aria-current', name === filter);
            if (name === filter) tab.setAttribute('aria-current', 'page');
            tab.querySelector('span').textContent = base.filter((row) => matchesFilter(row, name)).length;
        });

        const search = document.getElementById('trans-q');
        if (search) search.value = params.get('q') || '';
        const select = document.getElementById('trans-file');
        if (select) select.value = file;
        let hidden = document.querySelector('.trans-filters input[name=filter]');
        if (filter !== 'all') {
            hidden ??= Object.assign(document.createElement('input'), { type: 'hidden', name: 'filter' });
            hidden.value = filter;
            document.querySelector('.trans-filters').prepend(hidden);
        } else {
            hidden?.remove();
        }

        if (!rows.some((row) => !row.hidden)) {
            const empty = Object.assign(document.createElement('div'), { className: 'trans-empty' });
            empty.innerHTML = '<strong>Nothing here.</strong> ';
            empty.append(Object.assign(document.createElement('a'), { href: location.pathname, textContent: 'Show every string' }));
            table.replaceWith(empty);
        }
    }

    // The Halyard page: Done closes the panel, the button opens it again,
    // and a save rewrites the text on the page instead of reloading it.
    function inContext() {
        const toolbar = document.getElementById('trans-toolbar');
        const template = document.getElementById('trans-demo-toggle');
        if (!toolbar || !template) return;

        const panel = toolbar.querySelector('.trans-tb-panel');
        const toggle = template.content.firstElementChild.cloneNode(true);
        toggle.hidden = true;
        toolbar.append(toggle);

        toolbar.querySelector('.trans-tb-head form').addEventListener('submit', (event) => {
            event.preventDefault();
            panel.hidden = true;
            toggle.hidden = false;
        });
        toggle.addEventListener('submit', (event) => {
            event.preventDefault();
            toggle.hidden = true;
            panel.hidden = false;
        });

        toolbar.addEventListener('translations:saved', (event) => {
            event.preventDefault();
            const { textarea } = event.detail;
            const before = textarea.defaultValue;
            const after = textarea.value;
            textarea.defaultValue = after;
            if (before && before !== after) rewrite(before, after);
        });
    }

    // Find the old text on the page (placeholders filled in) and put the new
    // text there, with the same values in its placeholders.
    function rewrite(before, after) {
        const parts = before.split(/(:[A-Za-z][A-Za-z0-9_]*)/);
        const names = parts.filter((_, i) => i % 2).map((p) => p.slice(1).toLowerCase());
        const pattern = new RegExp(parts.map((p, i) => (i % 2 ? '(.+?)' : p.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))).join(''));
        const swap = (text) => text.replace(pattern, (...match) => {
            const values = Object.fromEntries(names.map((name, i) => [name, match[i + 1]]));
            return after.replace(/:([A-Za-z][A-Za-z0-9_]*)/g, (all, name) => values[name.toLowerCase()] ?? all);
        });

        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        const nodes = [];
        while (walker.nextNode()) {
            if (!walker.currentNode.parentElement.closest('#trans-toolbar, script, style, template')) nodes.push(walker.currentNode);
        }
        nodes.forEach((node) => {
            const next = swap(node.nodeValue);
            if (next !== node.nodeValue) {
                node.nodeValue = next;
                const el = node.parentElement;
                el.style.transition = 'background-color 1.2s';
                el.style.backgroundColor = 'rgb(61 78 255 / .18)';
                setTimeout(() => { el.style.backgroundColor = ''; }, 900);
            }
        });
        document.querySelectorAll('[title]').forEach((el) => {
            if (!el.closest('#trans-toolbar')) el.title = swap(el.title);
        });
    }
})();
