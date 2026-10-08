<style>
    /* Ruvelo house style, scoped to .trans so it can't leak into a host layout.
       Override any --trans-* variable to re-theme. */
    .trans {
        --trans-bg: #ffffff;
        --trans-subtle: #f8f8fc;
        --trans-muted: #f0f0f7;
        --trans-line: #e4e4ef;
        --trans-ink: #16162a;
        --trans-text-2: #4b4b63;
        --trans-text-3: #74748b;
        --trans-accent: #3d4eff;
        --trans-accent-2: #a78bfa;
        --trans-accent-ink: #2b38d6;
        --trans-accent-soft: #eef0ff;
        --trans-on-accent: #ffffff;
        --trans-danger: #e5384f;
        --trans-danger-soft: #fdecef;
        --trans-success: #167a4a;
        --trans-success-soft: #e8f8ef;
        --trans-warning: #b26a00;
        --trans-warning-soft: #fff4e0;
        --trans-radius: 8px;
        --trans-radius-lg: 14px;
        --trans-sans: "Geist", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        --trans-mono: "Geist Mono", ui-monospace, "SF Mono", "Cascadia Code", Menlo, Consolas, monospace;
        background: var(--trans-bg); color: var(--trans-ink); font: 15px/1.6 var(--trans-sans); -webkit-font-smoothing: antialiased;
        color-scheme: light;
    }
    @media (prefers-color-scheme: dark) {
        .trans {
            --trans-bg: #11111c;
            --trans-subtle: #171725;
            --trans-muted: #1f1f30;
            --trans-line: #2a2a3f;
            --trans-ink: #f1f1f8;
            --trans-text-2: #b6b6cc;
            --trans-text-3: #8787a3;
            --trans-accent: #8f9bff;
            --trans-accent-2: #c4b5fd;
            --trans-accent-ink: #b3bbff;
            --trans-accent-soft: #1e2150;
            --trans-on-accent: #0b0b1a;
            --trans-danger: #ff6b80;
            --trans-danger-soft: #331520;
            --trans-success: #74d6a2;
            --trans-success-soft: #0f2b1f;
            --trans-warning: #ffc266;
            --trans-warning-soft: #2e2210;
            color-scheme: dark;
        }
    }
    .trans *, .trans *::before, .trans *::after { box-sizing: border-box; }
    .trans a { color: var(--trans-accent); text-decoration: none; }
    .trans a:hover { text-decoration: underline; text-underline-offset: 3px; }
    .trans :focus-visible { outline: 2px solid var(--trans-accent); outline-offset: 2px; border-radius: 4px; }
    .trans [hidden] { display: none !important; }
    .trans-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

    /* Header */
    .trans-bar { position: sticky; top: 0; z-index: 10; display: flex; flex-wrap: wrap; align-items: center; gap: .75rem 1.5rem; padding: .75rem max(16px, calc((100% - 80rem) / 2 + 24px)); background: color-mix(in srgb, var(--trans-bg) 88%, transparent); backdrop-filter: blur(12px); border-bottom: 1px solid var(--trans-line); }
    .trans .trans-brand { display: inline-flex; align-items: center; gap: .6rem; font-weight: 600; font-size: .95rem; color: var(--trans-ink); letter-spacing: -.01em; }
    .trans .trans-brand:hover { text-decoration: none; }
    .trans-mark { display: grid; place-items: center; width: 1.75rem; height: 1.75rem; border-radius: 7px; background: linear-gradient(135deg, var(--trans-accent), var(--trans-accent-2)); color: #fff; font-size: .85rem; font-weight: 700; box-shadow: 0 4px 12px -4px color-mix(in srgb, var(--trans-accent) 60%, transparent); }
    .trans-bar nav { display: flex; flex-wrap: wrap; gap: .25rem 1.25rem; align-items: center; font-size: .9rem; margin-left: auto; }
    .trans .trans-bar nav a { color: var(--trans-text-2); display: inline-flex; align-items: center; gap: .4rem; }
    .trans .trans-bar nav a:hover, .trans .trans-bar nav a[aria-current] { color: var(--trans-ink); text-decoration: none; }
    .trans .trans-bar nav a[aria-current] { font-weight: 500; }

    .trans-page { max-width: 80rem; margin: 0 auto; padding: 2.25rem 24px 3rem; }
    .trans-foot { max-width: 80rem; margin: 0 auto; padding: 0 24px 2rem; color: var(--trans-text-3); font-size: .8rem; }
    .trans-foot-in { border-top: 1px solid var(--trans-line); padding-top: 1.25rem; display: flex; flex-wrap: wrap; gap: .5rem 1.25rem; justify-content: space-between; }
    .trans .trans-foot a { color: inherit; text-decoration: underline; text-underline-offset: 2px; }

    /* Type */
    .trans h1, .trans h2, .trans h3 { line-height: 1.25; font-weight: 600; letter-spacing: -.02em; color: var(--trans-ink); }
    .trans h1 { font-size: 2rem; margin: 0; overflow-wrap: anywhere; }
    .trans-lede { color: var(--trans-text-2); font-size: 1.025rem; margin: .5rem 0 0; max-width: 46rem; }
    .trans-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: end; gap: 1rem 1.5rem; margin-bottom: 2rem; }
    .trans-crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; list-style: none; margin: 0 0 .75rem; padding: 0; font-size: .85rem; color: var(--trans-text-3); }
    .trans-crumbs li + li::before { content: "/"; margin-right: .35rem; color: var(--trans-line); }
    .trans .trans-crumbs a { color: var(--trans-text-2); }
    .trans-muted { color: var(--trans-text-3); }
    .trans code, .trans kbd { font: .85em var(--trans-mono); background: var(--trans-muted); padding: .1em .4em; border-radius: 5px; }
    .trans-chip { display: inline-flex; align-items: center; gap: .3rem; border-radius: 999px; padding: .05rem .6rem; font-size: .75rem; font-weight: 500; background: var(--trans-accent-soft); color: var(--trans-accent-ink); white-space: nowrap; line-height: 1.6; }
    .trans-chip--quiet { background: var(--trans-muted); color: var(--trans-text-2); }
    .trans-chip--missing { background: var(--trans-danger-soft); color: var(--trans-danger); }
    .trans-chip--changed { background: var(--trans-warning-soft); color: var(--trans-warning); }
    .trans-chip--good { background: var(--trans-success-soft); color: var(--trans-success); }
    .trans-flash { display: flex; align-items: center; gap: .6rem; background: var(--trans-subtle); border: 1px solid var(--trans-line); padding: .6rem .9rem; border-radius: var(--trans-radius); margin: 0 0 1.75rem; font-size: .9rem; }
    .trans-flash::before { content: ""; width: .5rem; height: .5rem; border-radius: 50%; background: var(--trans-accent); flex: none; }

    /* Controls */
    .trans .trans-btn, .trans button { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; font: 500 .875rem/1 var(--trans-sans); padding: .6rem .95rem; border-radius: var(--trans-radius); border: 1px solid var(--trans-accent); background: var(--trans-accent); color: var(--trans-on-accent); cursor: pointer; box-shadow: 0 6px 16px -8px color-mix(in srgb, var(--trans-accent) 70%, transparent); text-decoration: none; white-space: nowrap; }
    .trans .trans-btn:hover, .trans button:hover { background: var(--trans-accent-ink); border-color: var(--trans-accent-ink); text-decoration: none; }
    .trans .trans-btn--quiet, .trans button.trans-btn--quiet { background: transparent; color: var(--trans-ink); border-color: var(--trans-line); box-shadow: none; }
    .trans .trans-btn--quiet:hover, .trans button.trans-btn--quiet:hover { background: var(--trans-accent-soft); border-color: var(--trans-accent); color: var(--trans-accent-ink); }
    .trans button.trans-btn--danger { background: transparent; color: var(--trans-danger); border-color: var(--trans-line); box-shadow: none; }
    .trans button.trans-btn--danger:hover { background: var(--trans-danger-soft); border-color: var(--trans-danger); }
    .trans .trans-btn--small, .trans button.trans-btn--small { padding: .3rem .55rem; font-size: .78rem; }
    .trans button.trans-link { background: none; border: 0; box-shadow: none; padding: 0; color: var(--trans-text-3); font-weight: 500; font-size: .8rem; }
    .trans button.trans-link:hover { background: none; color: var(--trans-accent); text-decoration: underline; text-underline-offset: 3px; }
    .trans input[type=text], .trans input[type=search], .trans select, .trans textarea { font: inherit; font-size: .925rem; color: inherit; background: var(--trans-bg); border: 1px solid var(--trans-line); border-radius: var(--trans-radius); padding: .55rem .75rem; width: 100%; }
    .trans input:focus, .trans textarea:focus, .trans select:focus { outline: none; border-color: var(--trans-accent); box-shadow: 0 0 0 3px var(--trans-accent-soft); }
    .trans label { font-weight: 500; font-size: .85rem; }
    .trans-error { color: var(--trans-danger); font-size: .85rem; margin: .4rem 0 0; }

    /* Overview */
    .trans-callout { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem 1.5rem; padding: 1.15rem 1.35rem; background: var(--trans-accent-soft); border-radius: var(--trans-radius-lg); margin: 0 0 2rem; }
    .trans-callout strong { display: block; color: var(--trans-accent-ink); font-size: 1rem; letter-spacing: -.01em; }
    .trans-callout p { margin: .15rem 0 0; color: var(--trans-text-2); font-size: .9rem; }
    .trans-callout code { background: color-mix(in srgb, var(--trans-bg) 70%, transparent); }
    .trans-cards { list-style: none; margin: 0; padding: 0; display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(min(100%, 17rem), 1fr)); }
    .trans .trans-card { display: flex; flex-direction: column; gap: .2rem; height: 100%; padding: 1.2rem 1.3rem 1.15rem; border: 1px solid var(--trans-line); border-radius: var(--trans-radius-lg); color: var(--trans-ink); background: var(--trans-bg); transition: border-color .15s, background .15s; }
    .trans .trans-card:hover { border-color: var(--trans-accent); background: linear-gradient(160deg, var(--trans-accent-soft), var(--trans-bg) 70%); text-decoration: none; }
    .trans-card-top { display: flex; align-items: center; gap: .5rem; }
    .trans-card-top strong { font-size: 1.05rem; font-weight: 600; letter-spacing: -.01em; margin-right: auto; }
    .trans-percent { font-size: 2.25rem; font-weight: 600; letter-spacing: -.045em; line-height: 1.1; margin-top: .6rem; font-variant-numeric: tabular-nums; }
    .trans-percent small { font-size: 1rem; color: var(--trans-text-3); font-weight: 500; letter-spacing: 0; margin-left: .1rem; }
    .trans-meter { height: .45rem; border-radius: 999px; background: var(--trans-muted); overflow: hidden; margin: .55rem 0 .65rem; }
    .trans-meter span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, var(--trans-accent), var(--trans-accent-2)); }
    .trans-meter--done span { background: var(--trans-success); }
    .trans-card-meta { display: flex; flex-wrap: wrap; gap: .25rem .9rem; color: var(--trans-text-3); font-size: .85rem; }
    .trans-card-meta .is-missing { color: var(--trans-danger); }
    .trans-card-meta .is-changed { color: var(--trans-warning); }
    .trans-card--add { justify-content: center; border-style: dashed !important; background: var(--trans-subtle) !important; }
    .trans-card--add form { display: grid; gap: .5rem; }
    .trans-card--add p { margin: 0 0 .25rem; color: var(--trans-text-2); font-size: .875rem; }
    .trans-tips { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 22rem), 1fr)); margin-top: 2.5rem; }
    .trans-tip { padding: 1.15rem 1.3rem; border: 1px solid var(--trans-line); border-radius: var(--trans-radius-lg); font-size: .9rem; color: var(--trans-text-2); }
    .trans-tip h2 { font-size: .95rem; margin: 0 0 .35rem; }
    .trans-tip p { margin: 0; }

    /* Editor */
    .trans-progress { display: flex; align-items: center; gap: .75rem; font-size: .9rem; color: var(--trans-text-2); }
    .trans-progress .trans-meter { width: 9rem; margin: 0; }
    .trans-switch { display: flex; gap: .5rem; align-items: center; }
    .trans-switch select { width: auto; }
    .trans-tabs { display: flex; flex-wrap: wrap; gap: .25rem; margin: 0 0 1rem; border-bottom: 1px solid var(--trans-line); }
    .trans .trans-tabs a { display: inline-flex; align-items: center; gap: .45rem; padding: .6rem .85rem; margin-bottom: -1px; border-bottom: 2px solid transparent; color: var(--trans-text-2); font-size: .9rem; font-weight: 500; }
    .trans .trans-tabs a:hover { color: var(--trans-ink); text-decoration: none; }
    .trans .trans-tabs a[aria-current] { color: var(--trans-accent-ink); border-bottom-color: var(--trans-accent); }
    .trans-tabs a span { font-size: .75rem; font-weight: 500; color: var(--trans-text-3); background: var(--trans-muted); border-radius: 999px; padding: 0 .45rem; font-variant-numeric: tabular-nums; }
    .trans-tabs a[aria-current] span { background: var(--trans-accent-soft); color: var(--trans-accent-ink); }
    .trans-filters { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin: 0 0 1.25rem; }
    .trans-filters input[type=search] { flex: 1 1 18rem; width: auto; }
    .trans-filters select { flex: 0 1 14rem; width: auto; }
    .trans-keys-help { margin: 0 0 1rem; font-size: .8rem; color: var(--trans-text-3); }
    .trans-keys-help kbd { font-size: .75rem; border: 1px solid var(--trans-line); background: var(--trans-subtle); }

    .trans-table-wrap { border: 1px solid var(--trans-line); border-radius: var(--trans-radius-lg); overflow: hidden; }
    .trans-table { width: 100%; border-collapse: collapse; font-size: .9rem; table-layout: fixed; }
    .trans-table th { text-align: left; font-weight: 500; color: var(--trans-text-2); background: var(--trans-subtle); font-size: .8rem; padding: .6rem 1rem; border-bottom: 1px solid var(--trans-line); }
    .trans-table td { padding: .85rem 1rem; border-bottom: 1px solid var(--trans-line); vertical-align: top; }
    .trans-table tr:last-child td { border-bottom: 0; }
    .trans-table col.trans-col-key { width: 24%; }
    .trans-table col.trans-col-source { width: 34%; }
    .trans-table tr.is-focus td { background: color-mix(in srgb, var(--trans-accent-soft) 55%, transparent); }
    .trans-key { display: block; font: .8rem/1.5 var(--trans-mono); color: var(--trans-ink); overflow-wrap: anywhere; background: none !important; padding: 0 !important; }
    .trans-file { margin-top: .35rem; }
    .trans-source { white-space: pre-wrap; overflow-wrap: anywhere; color: var(--trans-text-2); }
    .trans-source--none { color: var(--trans-text-3); font-style: italic; }
    .trans-cell textarea { display: block; min-height: 2.6rem; resize: vertical; line-height: 1.5; padding: .5rem .7rem; font: .925rem/1.5 var(--trans-sans); field-sizing: content; }
    tr[data-status=missing] .trans-cell textarea { background: color-mix(in srgb, var(--trans-danger-soft) 45%, var(--trans-bg)); border-color: color-mix(in srgb, var(--trans-danger) 30%, var(--trans-line)); }
    tr[data-status=missing] .trans-cell textarea:focus { background: var(--trans-bg); border-color: var(--trans-accent); }
    .trans-cell-foot { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .6rem; margin-top: .45rem; min-height: 1.4rem; font-size: .8rem; color: var(--trans-text-3); }
    .trans-cell-foot .trans-actions { display: flex; gap: .6rem; margin-left: auto; align-items: center; }
    .trans-state { font-size: .78rem; color: var(--trans-text-3); }
    .trans-state.is-saved { color: var(--trans-success); }
    .trans-state.is-error { color: var(--trans-danger); }
    .trans-warnings { list-style: none; margin: .5rem 0 0; padding: .5rem .7rem; border-radius: var(--trans-radius); background: var(--trans-warning-soft); color: var(--trans-warning); font-size: .8rem; line-height: 1.45; }
    .trans-warnings li { display: flex; gap: .45rem; }
    .trans-warnings li::before { content: "!"; display: grid; place-items: center; flex: none; width: 1rem; height: 1rem; margin-top: .05rem; border-radius: 50%; background: var(--trans-warning); color: var(--trans-bg); font-size: .65rem; font-weight: 700; }
    .trans-warnings code { background: color-mix(in srgb, var(--trans-bg) 60%, transparent); }
    .trans-suggestion { margin-top: .5rem; padding: .55rem .7rem; border-radius: var(--trans-radius); background: var(--trans-accent-soft); font-size: .85rem; }
    .trans-suggestion p { margin: 0 0 .4rem; white-space: pre-wrap; color: var(--trans-ink); }
    .trans-suggestion small { color: var(--trans-accent-ink); font-weight: 500; display: block; margin-bottom: .2rem; }
    .trans-empty { padding: 3rem 1rem; text-align: center; color: var(--trans-text-3); }
    .trans-empty strong { display: block; color: var(--trans-ink); font-size: 1.05rem; margin-bottom: .25rem; }
    .trans-pager { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin-top: 1.25rem; font-size: .875rem; color: var(--trans-text-3); }
    .trans-pager nav { display: flex; gap: .5rem; }

    /* Pending changes */
    .trans-section { margin-top: 2.5rem; }
    .trans-section > h2 { display: flex; align-items: center; gap: .6rem; font-size: 1.15rem; margin: 0 0 .75rem; }
    .trans-changes { list-style: none; margin: 0; padding: 0; border: 1px solid var(--trans-line); border-radius: var(--trans-radius-lg); overflow: hidden; }
    .trans-change { display: grid; grid-template-columns: minmax(0, 15rem) minmax(0, 1fr) auto; gap: .5rem 1.5rem; padding: 1rem 1.15rem; border-bottom: 1px solid var(--trans-line); }
    .trans-change:last-child { border-bottom: 0; }
    .trans-diff { display: grid; gap: .3rem; font-size: .9rem; }
    .trans-diff div { display: grid; grid-template-columns: 4.5rem minmax(0, 1fr); gap: .5rem; align-items: baseline; }
    .trans-diff dt, .trans-diff .trans-diff-label { font-size: .75rem; color: var(--trans-text-3); }
    .trans-diff del, .trans-diff ins { text-decoration: none; padding: .3rem .55rem; border-radius: 6px; white-space: pre-wrap; overflow-wrap: anywhere; }
    .trans-diff del { background: var(--trans-danger-soft); color: var(--trans-ink); }
    .trans-diff ins { background: var(--trans-success-soft); color: var(--trans-ink); }
    .trans-diff .is-none { background: var(--trans-subtle); color: var(--trans-text-3); font-style: italic; }
    .trans-change-meta { grid-column: 2; display: flex; flex-wrap: wrap; gap: .25rem .75rem; font-size: .8rem; color: var(--trans-text-3); }
    .trans-change-meta .is-warning { color: var(--trans-warning); }
    .trans-change-tools { display: flex; gap: .5rem; align-items: start; }
    .trans-change-tools form { margin: 0; }
    .trans-commands { margin: .75rem 0 0; padding: .8rem 1rem; border-radius: var(--trans-radius); background: var(--trans-bg); font: .85rem/1.7 var(--trans-mono); color: var(--trans-ink); overflow-x: auto; white-space: pre; }
    .trans-commands span { color: var(--trans-text-3); }
    .trans-avatar { display: inline-grid; place-items: center; width: 1.35rem; height: 1.35rem; border-radius: 50%; background: var(--trans-accent-soft); color: var(--trans-accent-ink); font-size: .6rem; font-weight: 600; margin-right: .3rem; vertical-align: middle; }

    @media (max-width: 47.99rem) {
        .trans-bar { padding-inline: 16px; }
        .trans-page { padding: 1.5rem 16px 2.5rem; }
        .trans-foot { padding-inline: 16px; }
        .trans h1 { font-size: 1.6rem; }
        .trans-table, .trans-table tbody, .trans-table tr, .trans-table td { display: block; width: 100%; }
        .trans-table thead, .trans-table colgroup { display: none; }
        .trans-table tr { padding: .9rem 1rem; border-bottom: 1px solid var(--trans-line); }
        .trans-table tr:last-child { border-bottom: 0; }
        .trans-table td { padding: 0; border: 0; }
        .trans-table td + td { margin-top: .5rem; }
        .trans-table tr.is-focus td { background: none; }
        .trans-change { grid-template-columns: minmax(0, 1fr); }
        .trans-change-meta { grid-column: 1; }
        .trans-progress .trans-meter { width: 6rem; }
    }
</style>
