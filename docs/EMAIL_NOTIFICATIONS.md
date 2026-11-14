# NEWTIC – Transactional e-mail center (2026-11-15)

## Scope and safety

One isolated installation = one independent SMTP credential set, sender domain, admin
recipient list, event preference list, encrypted database and delivery outbox.
No Polaris-specific SMTP credentials or addresses are built into code.

Mail sending is **OFF by default**. An authenticated admin must save SMTP credentials,
send a successful SMTP test, then explicitly enable automation. The SMTP password uses
the Laravel encrypted database cast and is never shown in admin pages or logs.

If the installation is on a Hostinger preview domain, in local/testing, **or**
NEWTIC_CHECKOUT_TEST_MODE=true, outbound messages are forcibly redirected to the
single configured test recipient. The original customer/admin recipient remains on
the private outbox record for diagnosis, not used as the actual SMTP destination.
This prevents fake orders and dummy shipping events from spamming real customers.

No promotional/marketing campaigns are part of this phase. Merchant consent and
IYS obligations must be assessed separately. A one-time post-purchase review request
is designed without promotional messages. Before enabling review invitations on a
live domain, the merchant should check its current legal compliance policy and
purchase communication preferences (IYS guidance on one-time customer satisfaction
surveys is narrower than blanket permission for marketing).

## Admin setup

1. Open **Admin > E-posta ve Bildirimler** at /admin/eposta-bildirimler.
2. Enter host, TCP port, username, secret SMTP password, from name/address,
   reply-to, operator recipients and test recipient. Use a verified sender domain.
3. Save with automatic sending **disabled**.
4. Click **SMTP test e-postası gönder**. An accepted SMTP request records
   verified_at; confirm the message actually arrived (inbox/spam).
5. Enable selected events and automatic sending. For staging, only the test inbox
   receives email.
6. Configure SPF, DKIM and DMARC according to the selected transactional provider
   in DNS; verify sender reputation, bounce policy, and recipient handling.
7. Run a signed dummy shipping delivery and inspect notification log. Do not
   turn off checkout test mode merely to test email on the preview domain.

## Customer events

- customer_welcome: newly created non-admin account, once
- customer_email_verification: registration, or explicit repeat request; signed 60-min link
- customer_password_reset: customer-requested reset, 60-min broker token, encrypted at rest
- customer_order_received: one per new order; includes order lines and, for bank transfer, active bank information
- customer_payment_notice: bank transfer notification received, review not yet approved
- customer_payment_verified: verified payment changes order to preparing
- customer_payment_rejected: bank transfer notice rejected
- customer_order_shipped: shipped status
- customer_out_for_delivery: courier dispatch status
- customer_delivered: completed/delivered status
- customer_cancelled: cancellation confirmed (not a refund claim)
- customer_review_invite: 3 days after the carrier confirms delivered, **only** a
  customer account tied to the completed order can review, one message per
  order, no reviewed products repeated; canceled, returned, corrected and
  incomplete shipments are rejected during processing
- customer_review_approved: moderation accepted

## Operator alerts

- admin_new_order: incoming order
- admin_payment_notice: unverified bank notice awaiting manual check
- admin_shipping_exception: return, failed, canceled shipment
- admin_review_pending: a customer submits/edits a review for moderation

No per-scan "at_delivery_center" emails, no marketing reminders, no abandoned-cart
emails, and no false promises of verified payment after PAYTR failure.

## Reliable delivery behavior

Outbox rows are committed atomically with source business updates. A unique
dedupe key suppresses repeated webhooks, retries and duplicate status changes.
The scheduled command runs once per minute, with a bounded batch size:
\`php artisan email:process --limit=25\`. It uses an in-DB processing lease,
retry/backoff for temporary SMTP failure, and per-event stale-state guards.
Failures are logged as exception class **only**, not sensitive messages. After
a reset link is sent or abandoned, its encrypted token is cleared. An operator
can view statuses and manually retry permanently failed messages.

As with most SMTP implementations, a crash after the provider accepts an email
but before the outbox updates can create a duplicate retry; delivery is
best-effort at-least-once, not mathematically exactly-once. Outbox dedupe keeps
event scheduling idempotent.

The existing Hostinger staging deployment provisions the once-per-minute
Laravel scheduler when crontab is available. GitHub Actions maintenance checks
every 30 minutes as a backup. On a new customer domain, set up **that
domain's own cron**, as detailed in INSTALL_NEW_STORE.md.

## Before live use

- [ ] Do not expose .env, SMTP secret, reset token or subscriber exports
- [ ] Verify sender DNS/authentication with the actual provider
- [ ] Confirm new store's SMTP and admin recipients are isolated
- [ ] Test bank notice acceptance/rejection, PAYTR signed callback and canceled order
- [ ] Test signed dummy carrier updates and delayed review invite
- [ ] Verify no mail goes to a real customer on staging/test checkout
- [ ] Confirm new store scheduler runs, retry log and operator alerts work
- [ ] Test new-account verification and password reset without leaking whether the address exists
- [ ] Check commercial communication policy (KVKK / ETK / IYS) before any review campaign
