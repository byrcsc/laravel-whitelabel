# Security Policy

## Supported versions

Security fixes go into the newest release only. To get a fix, upgrade to it.

| Version | Supported |
|---------|-----------|
| 1.x     | Yes       |
| < 1.0   | No        |

## Reporting a vulnerability

Please do not open a public issue, discussion, or pull request for a security
problem.

Report it privately through
[GitHub security advisories](https://github.com/byrcsc/laravel-whitelabel/security/advisories/new),
or by email to developer@code-vertical.com.

Include what you can: affected version, a description of the issue, and the
steps to reproduce it. A proof of concept helps but is not required.

You should get an acknowledgement within a week. This package is maintained by
one person, so a full assessment can take longer.

## Trust boundary

The package treats the following as trusted input, supplied by the application
developer and never by an end user:

- Brand definitions, whether they come from `config/whitelabel.php`, the
  database driver, or `Whitelabel::define()`.
- Asset `disk` and `path` values, and any absolute URL used in their place.
- Colour values rendered by `<x-whitelabel::styles />`.
- Resolver class-strings listed in `whitelabel.resolvers`.

Brand values reach `<style>` blocks, `img` tags, and mail headers. If your
application lets untrusted users write brand records, validate those values
before they are stored. The package will not do it for you.

The package treats the following as untrusted:

- The request host used by `DomainResolver`, which is matched against known
  brand domains and never used to build a brand.

### The request host decides which brand a visitor sees

`DomainResolver` reads the host through `Request::getHost()`, which is the
correct Laravel API and which therefore honours your `TrustProxies`
configuration. That is worth saying out loud: if your application trusts all
proxies, `X-Forwarded-Host` is attacker-controlled, and an attacker can pick
which brand they are served by setting a header.

This is not specific to this package — the same header decides your session
domain and your generated URLs — but brand selection makes it visible. If you
resolve brands by domain, configure `TrustProxies` with the proxies you
actually run behind, and set `trustedHosts` to the domains you actually serve.

Getting a brand you are not "entitled" to is not itself a privilege
escalation: the package decides how the application looks, never who the user
is or what they may do. Report it if you find a path where brand selection
reaches further than appearance.

Anything that lets untrusted input escape one of the trusted paths above, or
that lets one brand's data leak into another brand's request, job, or mail, is
a vulnerability. Report it.
