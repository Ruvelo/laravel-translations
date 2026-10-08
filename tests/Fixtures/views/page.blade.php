<!doctype html>
<html>
<head><title>{{ __('billing.invoice.title', ['number' => 42]) }}</title></head>
<body>
    <h1>{{ __('Welcome back, :name!', ['name' => 'Maya']) }}</h1>
    <p>{{ trans('billing.invoice.due', ['date' => 'May 1']) }}</p>
    <p>{{ trans_choice('billing.plans', 3) }}</p>
    <button title="{{ __('Pay now') }}">@lang('Pay now')</button>
    <p>{{ __('billing.missing_line') }}</p>
</body>
</html>
