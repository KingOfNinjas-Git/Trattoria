<?php

return [
    // "reverb" = live updates over websockets. "polling" = no websocket server (shared hosting):
    // screens ask the server for changes every few seconds instead.
    'realtime' => env('REALTIME', 'reverb'),

    'poll' => [
        'kitchen' => (int) env('POLL_KITCHEN', 20),
        'guest' => (int) env('POLL_GUEST', 20),
        'bill' => (int) env('POLL_BILL', 30),
    ],

    // Paying from the guest's phone. "demo" = a test-card gateway that never charges anything (the public demo turns it on
    // by itself); "off" = payment is made at the table, as before. A real restaurant would connect a payment provider
    // (see App\Support\Payments).
    'payments' => env('PAYMENTS', env('DEMO_MODE', false) ? 'demo' : 'off'),

    // Public portfolio demo: a notice on every page, a one-click staff view (no password) and
    // automatic clean-up of old orders. Off by default; a real restaurant leaves it off.
    'demo' => (bool) env('DEMO_MODE', false),
    'demo_email' => env('DEMO_EMAIL', 'demo@trattoria.demo'),
    'demo_keep_hours' => (int) env('DEMO_KEEP_HOURS', 6),
    'portfolio_url' => env('PORTFOLIO_URL', 'https://franciscowebdev.com'),
];
