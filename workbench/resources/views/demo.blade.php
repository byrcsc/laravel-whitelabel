@extends('layouts.demo')

@section('content')
    <h1>{{ brand('name') }}</h1>

    <table>
        <tr><th>Brand</th><td><code>{{ brand('id') }}</code></td></tr>
        <tr><th>Host</th><td><code>{{ request()->getHost() }}</code></td></tr>
        <tr><th>Domain</th><td><code>{{ brand()?->domain() ?? '—' }}</code></td></tr>
        <tr>
            <th>Primary</th>
            <td>
                <span class="swatch" style="background: {{ brand('colors.primary') }}"></span>
                <code>{{ brand('colors.primary') ?? '—' }}</code>
            </td>
        </tr>
        <tr>
            <th>Secondary</th>
            <td>
                <span class="swatch" style="background: {{ brand('colors.secondary') }}"></span>
                <code>{{ brand('colors.secondary') ?? '—' }}</code>
                @if (brand()?->definition()['colors']['secondary'] ?? null)
                    (its own)
                @else
                    (inherited from the default brand)
                @endif
            </td>
        </tr>
        <tr><th>Logo</th><td><code>{{ brand()?->logoUrl() ?? '—' }}</code></td></tr>
        <tr><th>Support</th><td><code>{{ brand('settings.support_url') ?? '—' }}</code></td></tr>
        <tr><th>Sender</th><td><code>{{ brand()?->mailFromAddress() ?? '—' }}</code></td></tr>
    </table>

    @isset($brand)
        <p>The middleware shared this brand with the view as <code>$brand</code>: {{ $brand?->name() }}.</p>
    @endisset
@endsection
