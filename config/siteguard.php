<?php

return [
    'contact_email' => env('SITEGUARD_CONTACT_EMAIL'),
    'challenge_bits' => (int) env('SITEGUARD_CHALLENGE_BITS', 16),
];
