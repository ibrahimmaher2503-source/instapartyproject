<?php

return [
    'create_account' => 'Invite account owner', 'created' => 'Account created', 'account_type' => 'Account type', 'staff' => 'Staff',
    'create_hint' => 'Only a super administrator can invite account owners. The owner sets a password through email. Contact verification stays pending; vendors are not automatically approved and customers must provide their own consent.',
    'invitation_requested' => 'A password setup email was requested. Confirm receipt with the account owner.',
    'invitation_failed' => 'The account was created, but the setup email could not be sent. Fix mail delivery, then use Send password reset on this account.',
    'resend_verification' => 'Resend vendor verification',
    'verification_hint' => 'Send the existing account a signed verification link. This does not mark its email verified or bypass account ownership.',
    'verification_requested' => 'The verification email was submitted to the mail transport. Confirm receipt with the vendor.',
    'delivery_failed' => 'The email could not be sent or requests are temporarily limited. Check provider setup and retry.',
    'reset_request_neutral' => 'Password recovery request received',
    'reset_recovery_hint' => 'If an eligible account exists, a reset link is requested for its email. Check inbox and spam; if nothing arrives, retry later or ask support to check email delivery. This response does not confirm account existence or inbox receipt.',
    'suspend_summary' => 'Suspend :name (:reference). Vendor operations will be blocked until an administrator restores access. Record the reason below.',
];
