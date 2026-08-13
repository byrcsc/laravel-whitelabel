# The Whitelabel workbench

A small Laravel application that runs the package for real, so every seam can
be driven by hand rather than only asserted in a test.

It is not shipped: `workbench/` is `export-ignore`d, so it never reaches anyone
who installs the package.

## Boot it

```bash
composer install
composer build
php vendor/bin/testbench serve
```

`composer build` creates the SQLite database, runs the migrations, and seeds
the demo data. It is safe to run again at any point; it starts from scratch
each time.

The demo copies its logos into the served `public/brands` the first time it
boots, so there is no storage symlink to set up. Delete that directory if you
edit one of the SVGs.

The demo resolves brands by host, so add these to `/etc/hosts` before you start
clicking:

```
127.0.0.1 acme.localhost globex.localhost
```

Then open <http://localhost:8000>.

## The demo loop

| Do this | Expect |
|---|---|
| Open <http://localhost:8000> | The **default** brand: dark grey, "Whitelabel Demo", its own logo and favicon. |
| Open <http://acme.localhost:8000> | **Acme**, purple, its own logo. The host resolved it, with no middleware. |
| Open <http://globex.localhost:8000> | **Globex**, blue. It defines one colour and no logo, so the secondary colour, the logo, the favicon, the sender, and the support URL all arrive from the default brand. The secondary colour row says so outright; the rest you can check against the default at <http://localhost:8000>. |
| Open <http://acme.localhost:8000/as/globex> | **Globex** on Acme's host. An explicit `Whitelabel::activate()` beats the domain, because `OverrideResolver` is first in the chain. |
| Open `/tenant/initech` | **Initech**, red. Making the Spatie tenant current ran `SwitchTenantBrandTask`, which activated the brand its JSON column carries. |
| Open `/eager` | The same page, resolved by `EagerResolveBrand` before the route ran and shared with the view as `$brand`. The sentence under the table only appears here. |
| Open `/brands` | Every brand the configured driver knows. |
| Open `/notify`, then run `php vendor/bin/testbench queue:work --once` | A queued notification, sent by the worker with the brand that was active when you clicked. Check `vendor/orchestra/testbench-core/laravel/storage/logs/laravel.log`: the brand's logo is in the header and its colour is on the button. |

Two more worth trying, because they are the things most likely to be wrong:

- **Take `BrandAware` off `WelcomeNotification`**, queue another one, and run the
  worker. The mail arrives with the default brand instead — the worker has no
  request, so nothing else could have told it.
- **Add `'whitelabel.mail.override_from' => true`** to the `config([...])` call
  in `WorkbenchServiceProvider::boot()`, and queue one more. The sender changes
  from the application's address to the brand's. Read the caveat in the main
  README before you do this in anything real.
- **Change `'whitelabel.driver'` to `'database'`** in the same place and reload
  `/brands`. The two brands the seeder created through the management API show
  up instead of the ones in the provider.

The demo has no published `config/whitelabel.php`: every setting lives in that
one `config([...])` call, which is the only place to change any of this.

## What is where

| Path | Seam |
|---|---|
| `app/Providers/WorkbenchServiceProvider.php` | The config-driver brands, and the Spatie task registration. |
| `app/Models/Tenant.php` | A tenant implementing `ProvidesBrand` from a JSON column. |
| `app/Notifications/WelcomeNotification.php` | A queued, `BrandAware` notification. |
| `database/seeders/DatabaseSeeder.php` | The tenant, plus two brands created through the database driver's management API. |
| `resources/views/layouts/demo.blade.php` | All three Blade components in a real layout. |
| `resources/assets/brands/` | The logos and favicon the demo serves. |
| `routes/web.php` | One route per resolution path. |

The testing helpers are exercised from the package's own suite, in
`tests/Feature/WorkbenchTest.php`, which drives these same routes.
