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
    | Mail
    |--------------------------------------------------------------------------
    |
    | "markdown" puts the brand's logo in the header of Laravel's markdown mail
    | and paints the primary button in the brand's colour. Turn it off to leave
    | markdown mail exactly as Laravel renders it. Views you have published to
    | resources/views/vendor/mail always win either way.
    |
    | "override_from" sends mail from the active brand's own name and address
    | instead of the application's. It is off by default, and you should leave
    | it off until every brand's domain is verified with your mail provider:
    | sending as a domain whose SPF, DKIM, and DMARC records do not authorise
    | you is the fastest way to have your mail silently dropped, and it damages
    | the reputation of the sending domain along the way. With it on, a brand
    | that names no from address is left alone, and so is mail sent with no
    | brand active.
    |
    */

    'mail' => [
        'markdown' => true,
        'override_from' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Brand assets
    |--------------------------------------------------------------------------
    |
    | The Storage disk a brand's logo, favicon, and other assets live on when
    | their definition does not name one. Assets are expected to be publicly
    | readable: the package builds URLs and never checks that a file exists or
    | that a disk is public.
    |
    */

    'assets' => [
        'disk' => 'public',
    ],

    /*
    |--------------------------------------------------------------------------
    | CSS custom properties
    |--------------------------------------------------------------------------
    |
    | The prefix <x-whitelabel::styles /> puts on the brand's colours, so a
    | colour named "primary" is emitted as --brand-primary. Override it per
    | usage with <x-whitelabel::styles prefix="theme" />.
    |
    */

    'css' => [
        'prefix' => 'brand',
    ],

    /*
    |--------------------------------------------------------------------------
    | Brand cache
    |--------------------------------------------------------------------------
    |
    | Brands are cached per identifier, forever, and busted by the write that
    | changed them. A null store means the application default. The config
    | driver is never cached: Laravel's config cache already covers it.
    |
    | If you use spatie/laravel-multitenancy with its PrefixCacheTask, that
    | task changes the cache prefix as tenants switch, which keeps one tenant's
    | brands out of another's cache automatically. Naming a store here that the
    | task does not prefix would undo that, so leave it null unless you know
    | the store is safe to share.
    |
    | Clear it by hand with `php artisan whitelabel:clear`.
    |
    */

    'cache' => [
        'enabled' => true,
        'store' => null,
        'prefix' => 'whitelabel',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resolver chain
    |--------------------------------------------------------------------------
    |
    | How the active brand is decided, in order. The first resolver to answer
    | wins. Reorder them, drop the ones you do not need, or add your own class
    | implementing Byrcsc\Whitelabel\Contracts\BrandResolver.
    |
    | The chain runs lazily, the first time the brand is read, so no middleware
    | is required. Add Byrcsc\Whitelabel\Http\Middleware\EagerResolveBrand to a
    | route group if you would rather resolve at the start of the request.
    |
    */

    'resolvers' => [
        Byrcsc\Whitelabel\Resolvers\OverrideResolver::class,
        Byrcsc\Whitelabel\Resolvers\TenantResolver::class,
        Byrcsc\Whitelabel\Resolvers\DomainResolver::class,
        Byrcsc\Whitelabel\Resolvers\DefaultResolver::class,
    ],

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
