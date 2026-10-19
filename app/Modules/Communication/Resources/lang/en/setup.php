<?php
return [
    'title' => 'Communication and payment setup', 'email' => 'Verification, reset and notification email',
    'sms' => 'SMS Misr phone verification', 'firebase' => 'Firebase messaging', 'paymob' => 'Paymob checkout and webhook',
    'whatsapp' => 'WhatsApp (delivery adapter unavailable)', 'laravel_mail' => 'Laravel mail transport',
    'settings_present' => 'Settings present; delivery unverified', 'settings_missing' => 'Setup incomplete',
    'readiness_hint' => 'This checklist reads configuration only. It never sends a message or starts a payment. Secrets are never displayed.',
    'queue_hint' => 'Verify that the queue worker is running and monitor failed jobs. Email verification is queued; settings alone cannot prove worker health.',
    'acceptance_hint' => 'Use the permitted test actions with recipients you control, then check inbox and spam. Confirm Paymob with an approved test transaction and signed callback. SMS/Firebase require an actual device. Local adapters are simulations.',
];
