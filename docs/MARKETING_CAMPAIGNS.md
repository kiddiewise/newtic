# NEWTIC Campaign Mail – Release and consent policy

## Purpose
Admin /kampanya-eposta provides draft / preview / test / send / pause / resume / cancel / report workflows using independent encrypted SMTP settings per store.

Campaigns support subject/title, safely escaped multiline body, uploaded banner (1200x600 recommended), button label + HTTPS URL, responsive HTML/text email, per-recipient delivery report and free unsubscribe.

On staging, every outgoing message routes to the configured safe test inbox. Preview launches are limited to three eligible recipient records, and none of their email addresses are used as SMTP destinations.

## Consent and IYS
Transactional order/delivery/password emails do not grant marketing consent.
Registration, checkout and account marketing checkboxes are unchecked by default. A customer's choice creates a PENDING record, not an immediately sendable address.

An administrator may add or approve a recipient only with clear evidence of prior consent (source and time), IYS recipient reference, and affirmative operator verification that IYS permits mail. Existing customers and buyers are never auto imported.

Eligibility requires consent proof, time, IYS confirmation, approved status and absence of any unsubscribe. A suppression/unsubscribe takes effect before the next queued send. Administrators cannot re-enable an unsubscribed address by importing it again. Recipients can opt in again from their own account, but a fresh IYS verification is required.

Email HTML includes company identification, contact information and a visible free unsub link, plus List-Unsubscribe header. This setting has no effect on mandatory transactional notices.

IMPORTANT: This NEWTIC module does NOT call the IYS API and cannot assert that merchant-entered references are valid. Merchant operators must personally verify the records and keep IYS withdrawals synchronized. Live campaigns are blocked by default through the server-level NEWTIC_MARKETING_SEND_ENABLED switch and must be released only after compliance and deliverability review.

Official reference:
https://ticaret.gov.tr/ic-ticaret/ticari-elektronik-iletiler/genel-bilgiler
https://iys.org.tr/iys/yonetmelik
https://iys.org.tr/iys/sss

## Setup
1. Configure the per-store SMTP connection, verify test delivery and enable operational mail.
2. Save the business name, business identifier (e.g. MERSIS), address, reply contact and IYS operator registration.
3. Enter consent-backed and IYS-confirmed recipient records one by one.
4. Create a draft, preview HTML and send a test to the admin (safe inbox on staging).
5. Preview a launch on staging: at most three selected eligible records, always redirected to safe test inbox.
6. Inspect report, pause/resume/cancel and unsubscribe behavior.
7. For live domains, independently verify IYS/API/legal obligations, SPF/DKIM/DMARC and vendor terms, then explicitly enable NEWTIC_MARKETING_SEND_ENABLED=true on the server.

The Laravel scheduler runs marketing:process --limit=10 each minute. The default marketing daily cap is 100 accepted campaign emails; NEWTIC_MARKETING_DAILY_LIMIT may be increased after provider review.

## Known limits
SMTP acceptance is not inbox delivery. No opening/click tracking or bounce webhooks are implemented yet. Provider-reported errors are recorded only by exception category, so secrets never appear in logs. In rare crash-after-SMTP-acceptance cases a retry may duplicate an email, even though each campaign/contact has a unique outbox record. Vendor-specific bounce feedback and direct IYS API integration are future production enhancements.
