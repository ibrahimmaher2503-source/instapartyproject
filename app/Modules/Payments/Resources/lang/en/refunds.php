<?php

declare(strict_types=1);

return [
    'actions' => [
        'initiate' => 'Refund',
        'confirm' => 'Confirm a partial refund or refund the remaining captured amount. Service refund policies still apply.',
        'amount_minor' => 'Refund amount (minor units)',
        'amount_help' => '100 minor units = EGP 1. The default is the remaining amount after refund reservations.',
        'note_en' => 'Reason note (English)',
        'note_ar' => 'Reason note (Arabic)',
        'recorded' => 'Refund request recorded',
    ],
    'nav' => [
        'refunds' => 'Refunds',
    ],

    'models' => [
        'refund' => [
            'singular' => 'Refund',
            'plural' => 'Refunds',
        ],
    ],

    'columns' => [
        'public_id' => 'Public ID',
        'booking' => 'Booking',
        'payment' => 'Payment',
        'amount' => 'Amount',
        'reason_code' => 'Refund Reason',
        'status' => 'Status',
        'created_at' => 'Created At',
    ],

    'reason_code' => [
        'customer_request' => 'Customer Request',
        'vendor_cancellation' => 'Vendor Cancellation',
        'service_unavailable' => 'Service Unavailable',
        'duplicate_charge' => 'Duplicate Charge',
        'admin_discretion' => 'Administrative Decision',
    ],

    'status' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ],

    'policy' => [
        'allowed' => 'Allowed',
        'rental_window_closed' => 'The rental refund window has closed.',
        'rental_in_setup' => 'Rental setup has already started.',
        'sale_in_preparation' => 'The sale item is already being prepared.',
        'digital_post_delivery' => 'Digital items cannot be refunded after delivery.',
    ],

    'errors' => [
        'amount_positive' => 'Enter a refund amount greater than zero.',
        'partial_refund_unsupported' => 'Partial refunds are not supported in phase 1.',
        'over_refund_attempted' => 'The requested refund exceeds the amount remaining for this payment.',
    ],
];
