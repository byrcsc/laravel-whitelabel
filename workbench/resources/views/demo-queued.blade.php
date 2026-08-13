@extends('layouts.demo')

@section('content')
    <h1>Queued</h1>

    <p>
        A welcome notification was queued while <strong>{{ $brand?->name() }}</strong>
        was the active brand. Run the worker to send it:
    </p>

    <pre><code>php vendor/bin/testbench queue:work --once</code></pre>

    <p>
        The mailer is the log driver, so it lands in
        <code>vendor/orchestra/testbench-core/laravel/storage/logs/laravel.log</code>. Look for the brand's logo in the
        header, the brand's colour on the button, and the sender — which stays
        the application's until you switch
        <code>whitelabel.mail.override_from</code> on in
        <code>WorkbenchServiceProvider</code>.
    </p>
@endsection
