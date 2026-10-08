<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@hasSection('title')@yield('title') · @endif{{ config('translations.name') }}</title>
    @include('translations::partials.styles')
    <style>
        body.trans { margin: 0; min-height: 100vh; }
        body.trans::before { content: ""; position: fixed; inset: 0 0 auto; height: 3px; z-index: 40; background: linear-gradient(90deg, var(--trans-accent), var(--trans-accent-2)); }
    </style>
    @stack('translations-head')
</head>
<body class="trans">
    @include('translations::partials.page')
</body>
</html>
