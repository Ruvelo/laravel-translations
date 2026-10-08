{{-- A pretend page from the Halyard app, translated with __(), with <x-translations::toolbar /> at the end. Rendered by demo/build.php. --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Plan & billing') }} · Halyard</title>
    <style>
        :root { --app-bg: #f6f7f9; --app-card: #ffffff; --app-line: #e3e6eb; --app-ink: #1b2330; --app-text-2: #556070; --app-brand: #0f766e; --app-side: #13202e; --app-warn: #9a5b00; --app-warn-bg: #fff6e5; color-scheme: light; }
        @media (prefers-color-scheme: dark) { :root { --app-bg: #0e141b; --app-card: #151d26; --app-line: #26313d; --app-ink: #e8edf3; --app-text-2: #9aa7b6; --app-brand: #2dd4bf; --app-side: #0a1016; --app-warn: #ffc266; --app-warn-bg: #2a2111; color-scheme: dark; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--app-bg); color: var(--app-ink); font: 14px/1.55 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .app { display: grid; grid-template-columns: 15rem minmax(0, 1fr); min-height: 100vh; }
        .side { background: var(--app-side); color: #cbd5e1; padding: 1.25rem 1rem; }
        .logo { display: flex; align-items: center; gap: .6rem; color: #fff; font-weight: 700; font-size: 1.05rem; margin: 0 .5rem 1.75rem; }
        .logo span { display: grid; place-items: center; width: 1.9rem; height: 1.9rem; border-radius: 8px; background: #14b8a6; color: #04201d; font-size: .9rem; }
        .side a { display: block; color: inherit; text-decoration: none; padding: .5rem .75rem; border-radius: 7px; margin-bottom: .15rem; }
        .side a.on { background: rgb(255 255 255 / .08); color: #fff; font-weight: 500; }
        .side .out { margin-top: 1.5rem; color: #8b9bb0; }
        .main { padding: 2rem 2.5rem 3rem; max-width: 64rem; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
        .top h1 { margin: 0; font-size: 1.5rem; letter-spacing: -.02em; }
        .top p { margin: .2rem 0 0; color: var(--app-text-2); }
        .who { display: flex; align-items: center; gap: .75rem; color: var(--app-text-2); }
        .bell { display: grid; place-items: center; width: 2.1rem; height: 2.1rem; border-radius: 50%; border: 1px solid var(--app-line); background: var(--app-card); }
        .avatar { display: grid; place-items: center; width: 2.1rem; height: 2.1rem; border-radius: 50%; background: var(--app-brand); color: #fff; font-weight: 600; font-size: .8rem; }
        .notice { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; background: var(--app-warn-bg); color: var(--app-warn); border-radius: 10px; padding: .8rem 1.1rem; margin-bottom: 1.25rem; font-weight: 500; }
        .card { background: var(--app-card); border: 1px solid var(--app-line); border-radius: 12px; padding: 1.25rem 1.4rem; margin-bottom: 1.25rem; }
        .card h2 { font-size: .95rem; margin: 0 0 .9rem; }
        .plan { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .plan strong { font-size: 1.35rem; }
        .muted { color: var(--app-text-2); }
        .btn { display: inline-block; border: 1px solid var(--app-line); background: var(--app-card); color: var(--app-ink); border-radius: 8px; padding: .5rem .85rem; font: inherit; font-weight: 500; text-decoration: none; }
        .btn.primary { background: var(--app-brand); border-color: var(--app-brand); color: #fff; }
        .actions { display: flex; gap: .5rem; flex-wrap: wrap; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-weight: 500; color: var(--app-text-2); font-size: .8rem; }
        th, td { padding: .6rem 0; border-bottom: 1px solid var(--app-line); }
        tr:last-child td { border-bottom: 0; }
        .num { text-align: right; }
        .pill { display: inline-block; padding: .1rem .55rem; border-radius: 999px; font-size: .75rem; background: color-mix(in srgb, var(--app-brand) 14%, transparent); color: var(--app-brand); font-weight: 500; }
        .pill.late { background: color-mix(in srgb, #dc2626 14%, transparent); color: #dc2626; }
        .danger { color: #dc2626; background: none; border: 0; font: inherit; font-weight: 500; padding: 0; cursor: pointer; }
        @media (max-width: 48rem) { .app { grid-template-columns: minmax(0, 1fr); } .side { display: none; } .main { padding: 1.25rem 16px 2rem; } }
    </style>
</head>
<body>
    <div class="app">
        <nav class="side" aria-label="Halyard">
            <div class="logo"><span>H</span>Halyard</div>
            <a href="#">{{ __('Dashboard') }}</a>
            <a href="#">{{ __('Invoices') }}</a>
            <a href="#">{{ __('Customers') }}</a>
            <a href="#">{{ __('Payments') }}</a>
            <a href="#">{{ __('Reports') }}</a>
            <a class="on" href="#">{{ __('Settings') }}</a>
            <a href="#">{{ __('Help center') }}</a>
            <a class="out" href="#">{{ __('Sign out') }}</a>
        </nav>
        <main class="main">
            <div class="top">
                <div>
                    <h1>{{ __('Plan & billing') }}</h1>
                    <p>{{ __('Settings for :company', ['company' => 'Northwind Studio']) }}</p>
                </div>
                <div class="who">
                    <span class="bell" title="{{ __('inbox::inbox.title') }}" aria-label="{{ __('inbox::inbox.title') }}"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg></span>
                    <span class="avatar" aria-hidden="true">MO</span>
                </div>
            </div>

            <div class="notice">
                <span>{{ __('Your next invoice of :amount is due on :date.', ['amount' => '€58.80', 'date' => '1/11/2026']) }}</span>
                <a class="btn primary" href="#">{{ __('Pay now') }}</a>
            </div>

            <section class="card">
                <h2>{{ __('billing.plan.title') }}</h2>
                <div class="plan">
                    <div>
                        <strong>{{ __('billing.plan.name', ['plan' => 'Growth']) }}</strong> <span class="muted">· {{ __('billing.plan.price', ['price' => '€49', 'interval' => __('monthly')]) }}</span><br>
                        <span class="muted">{{ __('billing.plan.usage', ['used' => 812, 'limit' => '1 000']) }} · {{ trans_choice('billing.seats', 5) }} · {{ __('billing.plan.renews', ['date' => '1/11/2026']) }}</span>
                    </div>
                    <a class="btn primary" href="#">{{ __('Change plan') }}</a>
                </div>
            </section>

            <section class="card">
                <h2>{{ __('billing.payment_method.title') }}</h2>
                <div class="plan">
                    <span>{{ __('billing.payment_method.card', ['brand' => 'Visa', 'last4' => '4242', 'expiry' => '08/28']) }}</span>
                    <a class="btn" href="#">{{ __('Update card') }}</a>
                </div>
            </section>

            <section class="card">
                <h2>{{ __('billing.history.title') }}</h2>
                <table>
                    <thead><tr><th>{{ __('billing.history.invoice') }}</th><th>{{ __('billing.history.date') }}</th><th>{{ __('billing.history.status') }}</th><th class="num">{{ __('billing.history.amount') }}</th></tr></thead>
                    <tbody>
                        <tr><td>HAL-2026-0352</td><td>15/10/2026</td><td><span class="pill late">{{ __('billing.status.overdue') }}</span></td><td class="num">€58.80</td></tr>
                        <tr><td>HAL-2026-0310</td><td>01/10/2026</td><td><span class="pill">{{ __('billing.status.paid') }}</span></td><td class="num">€58.80</td></tr>
                        <tr><td>HAL-2026-0274</td><td>01/09/2026</td><td><span class="pill">{{ __('billing.status.paid') }}</span></td><td class="num">€58.80</td></tr>
                    </tbody>
                </table>
                <div class="actions" style="margin-top: 1rem">
                    <a class="btn" href="#">{{ __('Download PDF') }}</a>
                    <a class="btn" href="#">{{ __('Send reminder') }}</a>
                    <a class="btn" href="#">{{ __('Export CSV') }}</a>
                </div>
            </section>

            <button class="danger" type="button" title="{{ __('billing.cancel_confirm', ['date' => '1/11/2026']) }}">{{ __('billing.cancel') }}</button>
        </main>
    </div>

    <x-translations::toolbar />
</body>
</html>
