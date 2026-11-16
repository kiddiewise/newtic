<?php

return [
    // Protected server-side first-install wizard state. Never put this inside public_html.
    'install_private_directory' => storage_path('app/private/newtic-setup'),
    // Deliberately server-private: admin recovery never lives under public_html.
    'admin_recovery_private_directory' => storage_path('app/private/newtic-admin-recovery'),
    /*
     * Until real payment credentials and fulfillment workflows are verified,
     * treat all orders as test records regardless of the configured hostname.
     * Live checkout must be deliberately enabled during the later go-live phase.
     */
    'checkout_test_mode' => (bool) env('NEWTIC_CHECKOUT_TEST_MODE', true),

    // Pending, unpaid bank transfer orders reserve stock for 48 hours by default.
    // Pending payment claims are protected until an administrator reviews them.
    'bank_transfer_expiry_hours' => max(1, min(720, (int) env('NEWTIC_BANK_TRANSFER_EXPIRY_HOURS', 48))),

    // Hard-disabled defaults: saving PAYTR credentials never activates payments.
    'paytr_card_payments_enabled' => (bool) env('NEWTIC_PAYTR_CARD_PAYMENTS_ENABLED', parse_url((string) env('APP_URL'), PHP_URL_HOST) === 'powderblue-dinosaur-538060.hostingersite.com'),
    'paytr_webhooks_enabled' => (bool) env('NEWTIC_PAYTR_WEBHOOKS_ENABLED', parse_url((string) env('APP_URL'), PHP_URL_HOST) === 'powderblue-dinosaur-538060.hostingersite.com'),

    // Live bulk campaigns require a deliberate server-level release decision
    // AND admin marketing/IYS settings. Preview domains always redirect mail
    // to one safe test inbox; never use this flag to bypass consent records.
    'marketing_live_enabled' => (bool) env('NEWTIC_MARKETING_SEND_ENABLED', false),
    'marketing_daily_limit' => max(1, min(2000, (int) env('NEWTIC_MARKETING_DAILY_LIMIT', 100))),

    // Dummy Kargo can be explicitly opted into by an admin only on staging.
    // Every real DHL/other-carrier shipment remains hard-disabled here.
    'shipping_dummy_enabled' => (bool) env('NEWTIC_SHIPPING_DUMMY_ENABLED', parse_url((string) env('APP_URL'), PHP_URL_HOST) === 'powderblue-dinosaur-538060.hostingersite.com'),
];
