<?php

declare(strict_types=1);

return [
    'password_reset' => [
        'invalid_identifier' => 'Enter a valid email or E.164 phone number (e.g. +201012345678).',
        'neutral_success' => 'If an account exists for that identifier, we have sent a reset code or link. Check your inbox or messages.',
        'invalid_or_expired' => 'This reset link or code is invalid or has expired. Please request a new one.',
        'updated' => 'Your password has been updated. You can now sign in.',
        'email_subject' => 'Reset your InstaParty password',
        'email_body' => "We received a request to reset your InstaParty password.\n\nUse the link below to set a new password. It expires in 60 minutes.\n\n:url\n\nIf you did not make this request, you can safely ignore this email.",
    ],
];
