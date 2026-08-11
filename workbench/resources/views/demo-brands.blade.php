@extends('layouts.demo')

@section('content')
    <h1>Brands the configured driver knows</h1>

    <table>
        <tr><th>Identifier</th><th>Name</th><th>Domain</th><th>Primary</th></tr>
        @foreach ($brands as $id => $listed)
            <tr>
                <td><code>{{ $id }}</code></td>
                <td>{{ $listed->name() }}</td>
                <td><code>{{ $listed->domain() ?? '—' }}</code></td>
                <td>
                    <span class="swatch" style="background: {{ $listed->color('primary') }}"></span>
                    <code>{{ $listed->color('primary') ?? '—' }}</code>
                </td>
            </tr>
        @endforeach
    </table>

    <p>
        The demo runs on the config driver, so these are the brands from
        <code>WorkbenchServiceProvider</code>. The seeder also creates
        <code>umbrella</code> and <code>soylent</code> through the database
        driver's management API. Change <code>whitelabel.driver</code> to
        <code>database</code> in <code>WorkbenchServiceProvider</code> to see
        those here instead.
    </p>
@endsection
