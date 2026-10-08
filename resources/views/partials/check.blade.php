<script>
(() => {
    if (window.RuveloTranslations && window.RuveloTranslations.check) return;
    // The same rules as Ruvelo\Translations\Support\Placeholders; keep them in step.
    const COLON = /(?<![\p{L}\p{N}_:]):([A-Za-z][A-Za-z0-9_]*)/gu;
    const BRACES = /\{\s*([A-Za-z_][A-Za-z0-9_.]*)\s*\}/g;
    const RANGE = /^\s*(\{\s*\d+\s*\}|\[\s*[\d*]+\s*,\s*[\d*]+\s*\])/;
    const TAG = /<([a-zA-Z][a-zA-Z0-9-]*)\b[^>]*>/g;

    const placeholders = text => {
        const found = new Map();
        const body = text.split('|').map(form => form.replace(RANGE, '')).join('\n');
        for (const m of body.matchAll(COLON)) { const k = ':' + m[1].toLowerCase(); if (!found.has(k)) found.set(k, ':' + m[1]); }
        for (const m of body.matchAll(BRACES)) { const k = '{' + m[1].toLowerCase() + '}'; if (!found.has(k)) found.set(k, '{' + m[1] + '}'); }
        return found;
    };
    const ranges = forms => forms.map(form => (form.match(RANGE) || [])[1]).filter(Boolean).map(r => r.replace(/\s+/g, ''));
    const tags = text => {
        const counts = {};
        for (const m of text.matchAll(TAG)) { const t = m[1].toLowerCase(); counts[t] = (counts[t] || 0) + 1; }
        return counts;
    };
    const check = (source, value) => {
        const warnings = [];
        if (source == null || !value || !value.trim()) return warnings;
        const expected = placeholders(source), actual = placeholders(value);
        for (const [k, spelling] of expected) if (!actual.has(k)) warnings.push(`Missing ${spelling}, which the source text uses.`);
        for (const [k, spelling] of actual) if (!expected.has(k)) warnings.push(`Adds ${spelling}, which the source text doesn't have.`);
        const sourceForms = source.split('|'), forms = value.split('|');
        if (sourceForms.length > 1 && forms.length === 1) {
            warnings.push(`The source text has ${sourceForms.length} plural forms separated by |; this has one.`);
        } else if (sourceForms.length === 1 && forms.length > 1) {
            warnings.push('This has plural forms separated by |, but the source text has none.');
        } else if (sourceForms.length > 1) {
            const a = ranges(sourceForms), b = ranges(forms);
            if (a.length && a.join(' ') !== b.join(' ')) {
                warnings.push(`The plural ranges differ: the source text uses ${a.join(' ')}` + (b.length ? `, this uses ${b.join(' ')}.` : ', this uses none.'));
            }
        }
        const sourceTags = tags(source), valueTags = tags(value);
        for (const t of Object.keys(sourceTags).sort()) if (!(t in valueTags)) warnings.push(`Missing the <${t}> tag.`);
        for (const t of Object.keys(valueTags).sort()) if (!(t in sourceTags)) warnings.push(`Adds a <${t}> tag, which the source text doesn't have.`);
        return warnings;
    };
    window.RuveloTranslations = Object.assign(window.RuveloTranslations || {}, { check });
})();
</script>
