<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ brand('name') }}</title>

    <x-whitelabel::favicon />
    <x-whitelabel::styles />

    <style>
        body { font: 16px/1.6 system-ui, sans-serif; margin: 0; color: #111; }
        header { border-bottom: 4px solid var(--brand-primary, #000); padding: 1.5rem 2rem; }
        main { padding: 1.5rem 2rem; max-width: 48rem; }
        a { color: var(--brand-primary, #000); }
        code { background: #f3f4f6; padding: 0.1rem 0.3rem; border-radius: 3px; }
        .swatch { display: inline-block; width: 1rem; height: 1rem; vertical-align: middle;
                  border: 1px solid #d1d5db; }
        table { border-collapse: collapse; width: 100%; }
        th, td { text-align: left; padding: 0.4rem 0.6rem; border-bottom: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <header>
        <x-whitelabel::logo style="height: 2rem" />
        <strong>{{ brand('name') }}</strong>
    </header>

    <main>
        @yield('content')

        <hr>

        <p>
            <a href="{{ route('home') }}">home</a> ·
            <a href="{{ route('eager') }}">eager</a> ·
            <a href="{{ route('brands') }}">brands</a> ·
            <a href="{{ route('as', 'acme') }}">as acme</a> ·
            <a href="{{ route('tenant', 'initech') }}">tenant initech</a> ·
            <a href="{{ route('notify') }}">queue a notification</a>
        </p>
    </main>
</body>
</html>
