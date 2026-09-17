<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('vendor-portal.errors.not_found_title') }} — InstaParty</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: #fafaf8;
            color: #2e2c28;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .container {
            text-align: center;
            max-width: 480px;
            width: 100%;
        }
        .code {
            display: block;
            font-size: 6rem;
            font-weight: 800;
            line-height: 1;
            color: #f96b0e;
            letter-spacing: -0.05em;
            margin-bottom: 1rem;
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: #2e2c28;
        }
        p {
            font-size: 1rem;
            color: #6b6860;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #f96b0e;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9375rem;
            padding: 0.625rem 1.5rem;
            border-radius: 0.5rem;
            transition: background 0.15s;
        }
        a:hover { background: #e05c00; }
    </style>
</head>
<body>
    <div class="container">
        <span class="code">404</span>
        <h1>{{ __('vendor-portal.errors.not_found_title') }}</h1>
        <p>{{ __('vendor-portal.errors.not_found_message') }}</p>
        <a href="{{ request()->is('admin*') ? '/admin' : (request()->is('vendor-portal*') ? '/vendor-portal' : '/') }}">{{ __('vendor-portal.errors.back_to_dashboard') }}</a>
    </div>
</body>
</html>
