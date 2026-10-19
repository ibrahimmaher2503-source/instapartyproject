<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('panel.failure_title') }} — InstaParty</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f8fafc; color: #0f172a; font: 1rem/1.7 system-ui, sans-serif; }
        main { max-width: 36rem; padding: 2rem; }
        h1 { font-size: 1.75rem; line-height: 1.4; }
        p { color: #475569; }
        code { display: block; overflow-wrap: anywhere; direction: ltr; unicode-bidi: isolate; }
        a { display: inline-block; padding: .75rem 1rem; background: #1c2b54; border-radius: .5rem; color: white; text-decoration: none; }
        a:focus-visible { outline: 3px solid #0f766e; outline-offset: 3px; }
        @media (prefers-color-scheme: dark) { body { background: #111827; color: #f8fafc; } p { color: #cbd5e1; } }
    </style>
</head>
<body>
    <main>
        <h1>{{ __('panel.failure_title') }}</h1>
        <p>{{ __('panel.failure_help') }}</p>
        <p>{{ __('panel.reference') }} <code>{{ $referenceId ?? \Illuminate\Support\Facades\Context::get('trace_id') }}</code></p>
        <a href="{{ request()->is('admin*') ? '/admin' : (request()->is('vendor-portal*') ? '/vendor-portal' : '/') }}">{{ __('panel.back') }}</a>
    </main>
</body>
</html>
