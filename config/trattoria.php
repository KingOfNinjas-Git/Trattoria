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

    // Public portfolio demo: a notice on every page, a one-click staff view (no password) and
    // automatic clean-up of old orders. Off by default; a real restaurant leaves it off.
    'demo' => (bool) env('DEMO_MODE', false),
    'demo_email' => env('DEMO_EMAIL', 'demo@trattoria.demo'),
    'demo_keep_hours' => (int) env('DEMO_KEEP_HOURS', 6),
    'portfolio_url' => env('PORTFOLIO_URL', 'https://franciscowebdev.com'),
];
