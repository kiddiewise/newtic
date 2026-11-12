<?php

return [
    /*
     * Until real payment credentials and fulfillment workflows are verified,
     * treat all orders as test records regardless of the configured hostname.
     * Live checkout must be deliberately enabled during the later go-live phase.
     */
    'checkout_test_mode' => (bool) env('NEWTIC_CHECKOUT_TEST_MODE', true),

    // Hard-disabled defaults: saving PAYTR credentials never activates payments.
    'paytr_card_payments_enabled' => (bool) env('NEWTIC_PAYTR_CARD_PAYMENTS_ENABLED', parse_url((string) env('APP_URL'), PHP_URL_HOST) === 'powderblue-dinosaur-538060.hostingersite.com'),
    'paytr_webhooks_enabled' => (bool) env('NEWTIC_PAYTR_WEBHOOKS_ENABLED', parse_url((string) env('APP_URL'), PHP_URL_HOST) === 'powderblue-dinosaur-538060.hostingersite.com'),

    // Dummy Kargo can be explicitly opted into by an admin only on staging.
    // Every real DHL/other-carrier shipment remains hard-disabled here.
    'shipping_dummy_enabled' => (bool) env('NEWTIC_SHIPPING_DUMMY_ENABLED', parse_url((string) env('APP_URL'), PHP_URL_HOST) === 'powderblue-dinosaur-538060.hostingersite.com'),
];
