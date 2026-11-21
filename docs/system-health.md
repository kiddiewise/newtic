# NEWTIC Sistem Sağlığı (hosting independent)

The admin-only /admin/sistem-sagligi page reads *local Laravel/database signals*, not a Hostinger/hPanel API. It works on shared PHP hosting, managed hosting, VPS, and cloud platforms with the same Laravel migrations.

## Truthful monitor semantics
- **Doğrulanmadı:** No signal has been recorded. Never imply a backup or webhook was successful.
- **Devre dışı:** This installation's feature flag/credentials/gating do not permit the integration.
- **İnceleme gerekli:** A recent recorded failure, an overdue scheduler heartbeat, a shipment exception, or high disk usage.
- **Çalışıyor:** A particular local check was observed; it is not a guarantee an external provider is reachable.
- Log scanning is bounded and shows only timestamps and severity. No raw stack traces, IPs, customer/order data or secrets are rendered.
- Disk usage is for the server's local storage; S3/other object storage usage must be checked at the provider.

## Configure a scheduler on ANY hosting provider
Run `php /absolute/path/to/project/artisan schedule:run` **once a minute** using that provider's cron/scheduler. This triggers `health:heartbeat` and the regular NEWTIC tasks. A request to the admin health page NEVER triggers scheduled work. If the host does not allow one-minute cron, the status will correctly show overdue.

## Record an actual backup (trusted CLI only)
NEWTIC does not create a backup, assume one exists, inspect or expose private archives, or claim success merely because a script started.

After a separate backup job **successfully finishes and verifies its output**, run:
`php artisan health:backup-report --result=success --provider=external`

If the actual job fails, call:
`php artisan health:backup-report --result=failed --provider=external`

Allowed providers: external, local, remote, managed. This is only a reporting hook; ensure the backup provider protects retention, encryption and off-site copies. For shared hosting, it can be appended to the provider's cron backup script. For managed backup services, call from their verified completion hook. Neither paths nor archive contents are stored.

## Integration observability
- PAYTR: callback processing timestamps in paytr_payment_attempts plus sanitized incoming webhook outcomes; no fake HTTP request to PAYTR or secret disclosure.
- Shipping: provider readiness, shipment sync timestamps, verified webhook events, technical exception counts.
- Cron: per-command success/failure hooks and a main one-minute heartbeat persisted in system_health_monitors.
- No tables in the existing store are deleted or reset by the migration. Existing store data and credentials remain untouched.
