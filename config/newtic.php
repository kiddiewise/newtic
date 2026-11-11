<?php

return [
    /*
     * Until real payment credentials and fulfillment workflows are verified,
     * treat all orders as test records regardless of the configured hostname.
     * Live checkout must be deliberately enabled during the later go-live phase.
     */
    'checkout_test_mode' => (bool) env('NEWTIC_CHECKOUT_TEST_MODE', true),

    // Hard-disabled defaults: saving PAYTR credentials never activates payments.
    'paytr_card_payments_enabled' => (bool) env('NEWTIC_PAYTR_CARD_PAYMENTS_ENABLED', false),
    'paytr_webhooks_enabled' => (bool) env('NEWTIC_PAYTR_WEBHOOKS_ENABLED', false),
];
