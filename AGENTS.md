# NEWTIC coding and release guidance

NEWTIC is a Laravel 12 / Blade storefront with a separate staging domain.

- Preserve every gram/weight as a distinct Product/SKU and product-detail URL; the same-category multi-product picker must support independent quantities.
- Keep server-side price, inventory, order and PAYTR callback validation authoritative. Do not expose credentials or enable live payments.
- Never modify or deploy to the existing WordPress production site `polariskursun.com`; use the Hostinger staging domain only after all tests pass.
- For HTML/CSS/JavaScript changes, **follow [docs/MODERN_WEB_GUIDANCE.md](docs/MODERN_WEB_GUIDANCE.md)**. Run the telemetry-disabled Modern Web Guidance audit and review its candidates contextually, verify support across browsers and accessibility/reduced-motion behavior.
- Modern Web Guidance is guidance, not an automatic rewrite. The independent audit workflow writes only a report and must never deploy.
- Run Laravel feature tests, Blade compilation and Vite compilation before publishing. Avoid speculative styling regressions, especially on small-screen checkout, cart and product-detail pages.
