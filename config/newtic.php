<?php

return [
    // Optional, explicitly pinned preview release for the internal Sinay pilot only.
    // Empty on every normal customer installation. Never shown as a public setting.
    'update_preview_tag' => env('NEWTIC_UPDATE_PREVIEW_TAG', ''),

    // Protected server-side first-install wizard state. Never put this inside public_html.
    'install_private_directory' => storage_path('app/private/newtic-setup'),
    // Explicit opt-in for initial installation on a temporary Hostinger preview
    // hostname. Never grants production payments, shipments or bulk sending.
    'install_allow_hostinger_temp_domain' => (bool) env('NEWTIC_INSTALL_ALLOW_HOSTINGER_TEMP_DOMAIN', false),
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

    // Legacy test flags only; merchant-managed activation is stored in the database.
    'paytr_card_payments_enabled' => (bool) env('NEWTIC_PAYTR_CARD_PAYMENTS_ENABLED', false),
    'paytr_webhooks_enabled' => (bool) env('NEWTIC_PAYTR_WEBHOOKS_ENABLED', false),

    // Live bulk campaigns require a deliberate server-level release decision
    // AND admin marketing/IYS settings. Preview domains always redirect mail
    // to one safe test inbox; never use this flag to bypass consent records.
    'marketing_live_enabled' => (bool) env('NEWTIC_MARKETING_SEND_ENABLED', false),
    'marketing_daily_limit' => max(1, min(2000, (int) env('NEWTIC_MARKETING_DAILY_LIMIT', 100))),

    // Dummy Kargo can be explicitly opted into by an admin only on staging.
    // Every real DHL/other-carrier shipment remains hard-disabled here.
    'shipping_dummy_enabled' => (bool) env('NEWTIC_SHIPPING_DUMMY_ENABLED', parse_url((string) env('APP_URL'), PHP_URL_HOST) === 'powderblue-dinosaur-538060.hostingersite.com'),
];
