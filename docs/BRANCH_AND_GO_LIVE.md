# NEWTIC deployment and branch policy

## One canonical source
- main is the GitHub default branch and canonical source.
- Temporary feat/* branches exist only for reviews and CI, not production publishing.
- Main triggers tested staging deployments. The historical phase-01 branch remains as a rollback archive; never force-push.
- main includes the previous staging history AND its pre-existing default-branch manual deployment workflow.

## Domain migration
- Every customer install gets its own domain, .env, storage, and database. Set APP_URL to the new HTTPS domain.
- The staging script remains staging-only. Live PAYTR and real shipping require deliberate separate go-live verification.
- SEO / 301 Yönlendirmeler maps old path to new path and supports CSV from any platform.
- The old domain must route to this app or redirect at its own server; NEWTIC cannot intercept traffic on unrelated hosting.

## Scheduler
- Preferred: hPanel cron executes php artisan schedule:run every minute from the app root.
- When shared hosting offers no crontab, the GitHub Action runs staging maintenance every 30 minutes. GitHub schedules may be delayed or disabled due to inactivity; monitor the workflow.
- The admin Orders screen can run bank-payment expiry manually.
- Unpaid bank orders expire after 48 hours by default, configurable via NEWTIC_BANK_TRANSFER_EXPIRY_HOURS (1-720).
- Orders with unreviewed or verified bank notifications are excluded from automatic cancellation.
