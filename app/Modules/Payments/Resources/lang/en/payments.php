<?php

declare(strict_types=1);

return [
    'navigation' => [
        'payments' => 'Payments',
        'refunds' => 'Refunds',
        'attempts' => 'Payment Attempts',
        'webhook_logs' => 'Webhook Logs',
        'idempotency_keys' => 'Idempotency Keys',
    ],

    'models' => [
        'payment' => [
            'singular' => 'Payment',
            'plural' => 'Payments',
        ],
        'payment_attempt' => [
            'singular' => 'Payment Attempt',
            'plural' => 'Payment Attempts',
        ],
        'webhook_log' => [
            'singular' => 'Webhook Log',
            'plural' => 'Webhook Logs',
        ],
        'idempotency_key' => [
            'singular' => 'Idempotency Key',
            'plural' => 'Idempotency Keys',
        ],
        'refund' => [
            'singular' => 'Refund',
            'plural' => 'Refunds',
        ],
    ],

    'columns' => [
        'public_id' => 'Public ID',
        'booking' => 'Booking',
        'gateway' => 'Payment Gateway',
        'amount' => 'Amount',
        'method' => 'Payment Method',
        'status' => 'Status',
        'captured_at' => 'Captured At',
        'payment' => 'Payment',
        'attempt_no' => 'Attempt Number',
        'http_status' => 'HTTP Status',
        'event_type' => 'Event Type',
        'signature_valid' => 'Valid Signature',
        'processing_status' => 'Processing Status',
        'processed_at' => 'Processed At',
        'key' => 'Key',
        'route' => 'Route',
        'response_status' => 'Response Status',
        'expires_at' => 'Expires At',
    ],

    'event_types' => [
        'unknown' => 'Unknown event',
        'payment_captured' => 'Payment Captured',
        'payment_failed' => 'Payment Failed',
        'payment_authorized' => 'Payment Authorized',
        'refund_completed' => 'Refund Completed',
        'refund_failed' => 'Refund Failed',
    ],

    'signature_status' => [
        'valid' => 'Valid',
        'invalid' => 'Invalid',
        'not_checked' => 'Not checked',
    ],

    'processing_status' => [
        'pending' => 'Pending',
        'processed' => 'Processed',
        'duplicate' => 'Duplicate (idempotent)',
        'failed' => 'Failed',
        'rejected' => 'Rejected',
    ],

    'status' => [
        'pending' => 'Pending',
        'authorized' => 'Authorized',
        'captured' => 'Captured',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
        'partially_refunded' => 'Partially Refunded',
        'voided' => 'Voided',
        'abandoned' => 'Abandoned',
    ],

    'method' => [
        'card' => 'Card',
        'wallet' => 'Wallet',
        'installment' => 'Installment',
        'cash_on_delivery' => 'Cash on delivery',
        'transfer' => 'Bank transfer',
    ],

    'ops' => [
        'navigation_label' => 'Ops Console',
        'page_title' => 'Payments Operations Console',

        'tabs' => [
            'failed_payments' => 'Failed Payments',
            'stuck_auths' => 'Stuck Authorizations',
            'webhook_replay' => 'Webhook Replay',
            'chargebacks' => 'Chargebacks',
            'gateway_health' => 'Gateway Health',
            'reconciliation' => 'Reconciliation Diff',
        ],

        'columns' => [
            'id' => 'ID',
            'gateway_ref' => 'Gateway Ref',
            'amount' => 'Amount',
            'failed_at' => 'Failed At',
            'authorized_at' => 'Authorized At',
            'processed_at' => 'Processed At',
            'received_at' => 'Received At',
            'checked_at' => 'Checked At',
            'opened_at' => 'Opened At',
            'resolved_at' => 'Resolved At',
            'case_id' => 'Case ID',
            'latency_ms' => 'Latency (ms)',
            'error' => 'Error',
        ],

        'placeholders' => [
            'not_processed' => 'Not processed',
            'pending' => 'Pending',
            'none' => '—',
        ],

        'gateway_status' => [
            'ok' => 'OK',
            'fail' => 'FAIL',
        ],

        'actions' => [
            'retry' => 'Retry',
            'abandon' => 'Mark Abandoned',
            'capture' => 'Manual Capture',
            'void' => 'Void Authorization',
            'replay' => 'Replay',
            'open_chargeback' => 'Open Chargeback',
            'resolve' => 'Resolve',
        ],

        'forms' => [
            'reason_manual_capture' => 'Reason for manual capture',
            'reason_void' => 'Reason for void',
            'payment_id' => 'Payment ID',
            'reason_en' => 'Reason (EN)',
            'reason_ar' => 'Reason (AR)',
            'amount_minor' => 'Amount (minor units)',
            'gateway_case_id' => 'Gateway Case ID',
            'resolution' => 'Resolution',
            'admin_notes_en' => 'Admin Notes (EN)',
            'admin_notes_ar' => 'Admin Notes (AR)',
            'resolution_won' => 'Won (restore vendor credit)',
            'resolution_lost' => 'Lost (finalize debit)',
        ],

        'modal' => [
            'replay_description' => 'Re-dispatch this webhook through the processor. Idempotency is guaranteed — no double-charge is possible.',
        ],

        'reconciliation_heading' => 'Reconciliation — Platform: :platform | Gateway: :gateway | Diff: :diff | Source: :source',
        'reconciliation_diff_none' => 'None ✓',
        'reconciliation_na' => 'N/A',

        'retry_queued' => 'Payment queued for retry.',
        'abandoned' => 'Payment marked as abandoned.',
        'captured' => 'Payment captured manually.',
        'voided' => 'Authorization voided.',
        'webhook_replayed' => 'Webhook replayed.',
    ],

    'refund' => [
        'columns' => [
            'public_id' => 'Public ID',
            'booking_reference' => 'Booking Reference',
            'payment_reference' => 'Payment Reference',
            'booking_id' => 'Booking',
            'amount' => 'Amount',
            'reason_code' => 'Reason Code',
            'status' => 'Status',
            'created_at' => 'Created At',
        ],
    ],
    'identity' => [
        'legacy_unknown' => 'Legacy / Unknown',
    ],
];
