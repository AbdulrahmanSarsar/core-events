# phpqrcode (bundled)

Self-contained QR code generator used by Core Events Pro to render
attendee tickets locally, without depending on any external service.

- **Source:** https://github.com/t0k4rt/phpqrcode
- **Upstream author:** Dominik Dzienia
- **License:** LGPL 3.0 (compatible with the plugin's GPLv2+ license)
- **Why bundled:** WordPress plugins distributed on shared hosting cannot
  rely on `composer install`. Vendoring keeps the plugin self-contained.

Do not modify `phpqrcode.php` directly. If you need to upgrade, replace
the file with a newer release from the upstream repository.

The plugin only uses the public `\QRcode::png()` and `\QRcode::svg()`
entry points. All access goes through `\CoreEventsPro\Helpers\QrGenerator`.
