<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Brand driver
    |--------------------------------------------------------------------------
    |
    | Where brands come from. "config" reads the "brands" array below and is
    | read-only. "database" stores them in a table with a management API.
    | Register your own driver on Byrcsc\Whitelabel\BrandRepositoryManager and
    | name it here.
    |
    */

    'driver' => 'config',

    /*
    |--------------------------------------------------------------------------
    | Database driver
    |--------------------------------------------------------------------------
    |
    | Where the "database" driver keeps its brands. A null connection means the
    | application default. Change the table name before you publish the
    | migration; the model reads it from here.
    |
    */

    'database' => [
        'connection' => null,
        'table' => 'brands',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default brand
    |--------------------------------------------------------------------------
    |
    | The identifier of the brand used when nothing else resolves one, and the
    | brand every other brand falls back to, key by key.
    |
    */

    'default' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Brands
    |--------------------------------------------------------------------------
    |
    | Brand definitions for the config driver, keyed by identifier. Every key
    | below is optional; a brand only defines what it overrides.
    |
    |     'acme' => [
    |         // Display name. The default brand falls back to the application
    |         // name when it leaves this out.
    |         'name' => 'Acme',
    |
    |         // The host this brand answers on, bare: no scheme, no path. This
    |         // is the one key that never falls back to the default brand.
    |         'domain' => 'app.acme.com',
    |
    |         // Assets are a disk and path pair resolved through Storage, or a
    |         // plain string holding a path or an absolute URL. The package
    |         // never checks that the file exists or that the disk is public.
    |         'logo' => ['disk' => 'public', 'path' => 'brands/acme/logo.svg'],
    |         'favicon' => ['disk' => 'public', 'path' => 'brands/acme/favicon.ico'],
    |
    |         // Emitted as CSS custom properties by <x-whitelabel::styles />.
    |         'colors' => ['primary' => '#7c3aed', 'secondary' => '#0ea5e9'],
    |
    |         // Used only when whitelabel.mail.override_from is enabled.
    |         'mail' => ['from_name' => 'Acme', 'from_address' => 'hello@acme.com'],
    |
    |         // An open bag for anything the fixed core does not name. Read it
    |         // with brand('settings.support_url').
    |         'settings' => ['support_url' => 'https://support.acme.com'],
    |     ],
    |
    | Fallback works key by key. A brand that names one colour keeps the rest
    | of the default brand's set. A brand that sets a value to an empty string,
    | or to an empty list, has cleared it, and it does not fall back. An empty
    | map such as 'colors' => [] says nothing at all, so clear colours one at a
    | time. Setting a key to null is an error: remove the key instead.
    |
    | The brand named by 'default' above must exist here.
    |
    */

    'brands' => [

        'default' => [
            //
        ],

    ],

];
