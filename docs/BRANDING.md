# NEWTIC default WebP brand identity

- Horizontal wordmark: `public/assets/branding/newtic-logo.webp` (800 × 200 px, transparent WebP).
- Compact mark: `public/assets/branding/newtic-mark.webp` (320 × 320 px, transparent WebP).
- Desktop header, mobile mega menu, store footer, admin sidebar/login and install wizard use the horizontal wordmark.
- Mobile header, favicon and compact admin icons use the square mark.
- A merchant's uploaded desktop/mobile logos always take precedence. Uploads are isolated in the merchant database/storage and never overwritten by a deploy.
- When only one custom logo is uploaded it is used for the other form factor too. Removing uploads reveals the original default files without changing them.
- GitHub Actions > Tests and assets offers a `newtic-complete-install` artifact (30 days) containing all release source code, two bundled brand images, matching precompiled CSS/JS and `.newtic-release-revision`. No `.env`, vendor, customer uploads or database records are packaged.
- Extract `NEWTIC/`, configure an independent `.env` and run `bash scripts/install-production.sh --check` followed by `--install`; the release revision is verified without needing to clone Git.
- Git-based installations can still use the existing `newtic-install-assets` artifact.
