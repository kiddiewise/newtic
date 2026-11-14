# Modern Web Guidance — NEWTIC development policy

Modern Web Guidance 1.0 is an **advisory, read-only** part of the normal NEWTIC frontend workflow.

## Required on frontend changes

1. Review the official guide for the relevant HTML/CSS/JS feature and its current cross-browser Baseline status.
2. Use native web APIs when they genuinely simplify the implementation **without** breaking customer interactions, accessibility, or compatibility.
3. Run the frontend build, relevant Laravel feature tests and browser-level checks, including keyboard support and `prefers-reduced-motion`.
4. Run the telemetry-free scanner after frontend changes. Read the finding's `keepWhen` and `dropWhen` context before changing code. Record deliberate fallbacks or false positives.
5. Let the existing GitHub Actions test and deployment workflow publish only a verified staging build. Production `polariskursun.com` remains untouched.

### Local audit commands

```bash
DISABLE_TELEMETRY=1 npx --yes modern-web-guidance@1.0.0 legacy-scan resources --json
DISABLE_TELEMETRY=1 npx --yes modern-web-guidance@1.0.0 retrieve progress
```

GitHub Actions: `.github/workflows/modern-web-guidance.yml` runs independently on feature-branch pushes, pull requests and manually. It publishes a report artifact and readable job summary. Its advisory results do not change source files, block payments, configure PAYTR, or deploy. The CLI version is pinned; review new versions before upgrading.

### Initial 2026-11-12 findings and decisions

- `animate-to-intrinsic-sizes` — **intentionally not changed**. The product-detail ribbon has a known fixed open height of 40px and closed height of 0; it is not trying to animate from `auto`. Retaining fixed-size transitions gives predictable cross-browser behavior.
- `customize-scrollbar-color-and-thickness` — **modernized**: use standard `scrollbar-width` and `scrollbar-color`, keeping vendor fallback only where the standard property is unsupported.
- `progress` — **modernized**: use a semantic, native `<progress>` for the free-shipping meter, preserving the truck overlay, the original navy palette, visual progress and reduced-motion handling.

The scanner has limited deterministic rule coverage (~20% of the published guides). A smaller findings count is not proof of full browser compatibility. Do not automatically apply all future findings.
