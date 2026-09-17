<?php

declare(strict_types=1);

return [
    'section_title' => 'Audit Timeline',

    'action_keys' => [
        // Vendor
        'vendor.state_changed' => 'Vendor approval status changed',
        'vendor.approved_for_rental' => 'Vendor approved for rental services',
        'vendor.approved_for_sale' => 'Vendor approved for sale services',
        'vendor.approved_for_digital' => 'Vendor approved for digital services',
        'vendor.suspended' => 'Vendor suspended',
        'vendor.profile_updated' => 'Vendor profile updated',
        'vendor.document_uploaded' => 'Document uploaded',
        'vendor.document_rejected' => 'Document rejected',
        'vendor.document_approved' => 'Document approved',

        // Service
        'service.state_changed' => 'Service status changed',
        'service.published' => 'Service published',
        'service.unpublished' => 'Service unpublished',
        'service.archived' => 'Service archived',
        'service.profile_updated' => 'Service profile updated',
        'service.change_request.submitted' => 'Change request submitted',
        'service.change_request.approved' => 'Change request approved',
        'service.change_request.rejected' => 'Change request rejected',
        'service.change_request.clarification_requested' => 'Clarification requested',
        'service.change_request.cancelled' => 'Change request cancelled',
        'service.change_request.decided' => 'Change request decided',

        // Booking lifecycle state transitions
        'booking.state_changed' => 'Booking status changed',
        'state_transition.booking.draft' => 'Booking created as draft',
        'state_transition.booking.submitted' => 'Booking submitted by customer',
        'state_transition.booking.vendor_review' => 'Sent to vendor for review',
        'state_transition.booking.customer_review' => 'Awaiting customer review',
        'state_transition.booking.confirmed' => 'Booking confirmed by vendor',
        'state_transition.booking.active' => 'Event started',
        'state_transition.booking.completed' => 'Booking completed',
        'state_transition.booking.cancelled' => 'Booking cancelled',

        // Booking vendor state transitions
        'state_transition.bookingvendor.pending' => 'Vendor assignment pending',
        'state_transition.bookingvendor.sent' => 'Booking sent to vendor',
        'state_transition.bookingvendor.accepted' => 'Vendor accepted booking',
        'state_transition.bookingvendor.rejected' => 'Vendor rejected booking',
        'state_transition.bookingvendor.modified' => 'Booking modified by vendor',
        'state_transition.bookingvendor.cancelled' => 'Vendor assignment cancelled',
        'state_transition.bookingvendor.modification_proposed' => 'Vendor proposed modification',
        'state_transition.bookingvendor.fulfilled' => 'Vendor marked as fulfilled',

        // Booking item state transitions
        'state_transition.bookingitem.pending' => 'Item pending',
        'state_transition.bookingitem.confirmed' => 'Item confirmed',
        'state_transition.bookingitem.in_preparation' => 'Item in preparation',
        'state_transition.bookingitem.ready' => 'Item ready',
        'state_transition.bookingitem.delivered' => 'Item delivered',
        'state_transition.bookingitem.setup' => 'Item being set up',
        'state_transition.bookingitem.active' => 'Item active at event',
        'state_transition.bookingitem.completed' => 'Item completed',
        'state_transition.bookingitem.cancelled' => 'Item cancelled',

        // Booking modification
        'booking.modification.proposed' => 'Modification proposed',
        'booking.modification.accepted' => 'Modification accepted',
        'booking.modification.rejected' => 'Modification rejected',
        'booking.modification.cancelled' => 'Modification cancelled',
        'booking_modification.draft' => 'Modification drafted',
        'booking_modification.pending' => 'Modification pending customer review',
        'booking_modification.customer_accepted' => 'Customer accepted modification',
        'booking_modification.customer_rejected' => 'Customer rejected modification',
        'booking_modification.withdrawn' => 'Modification withdrawn by vendor',
        'booking_modification.expired' => 'Modification offer expired',

        // Payments
        'payment.pending' => 'Payment pending',
        'payment.authorized' => 'Payment authorised',
        'payment.captured' => 'Payment captured',
        'payment.failed' => 'Payment failed',
        'payment.refunded' => 'Payment refunded',
        'payment.partially_refunded' => 'Payment partially refunded',
        'payment.voided' => 'Payment voided',
        'payment.abandoned' => 'Payment abandoned',
        'payment.attempt.succeeded' => 'Payment attempt succeeded',
        'payment.attempt.failed' => 'Payment attempt failed',
        'payment.refund.initiated' => 'Refund initiated',
        'payment.refund.processing' => 'Refund processing',
        'payment.refund.completed' => 'Refund completed',
        'payment.refund.failed' => 'Refund failed',

        // Refunds (standalone refund subject)
        'refund.initiated' => 'Refund initiated',
        'refund.processing' => 'Refund processing',
        'refund.completed' => 'Refund completed',
        'refund.failed' => 'Refund failed',
        'refund.processed' => 'Refund processed',
        'refund.rejected' => 'Refund rejected',

        // Wallet ledger entry types
        'wallet_ledger.commission_credit' => 'Commission credit',
        'wallet_ledger.refund_debit' => 'Refund debit',
        'wallet_ledger.withdrawal_debit' => 'Withdrawal debit',
        'wallet_ledger.manual_adjustment' => 'Manual ledger adjustment',
        'wallet_ledger.payment_capture' => 'Payment captured to wallet',
        'wallet_ledger.refund_credit_customer' => 'Refund credited to customer',
        'wallet_ledger.refund_debit_platform' => 'Refund debited from platform',
        'wallet_ledger.commission_accrual' => 'Commission accrual',
        'wallet_ledger.commission_reversal' => 'Commission reversal',
        'wallet_ledger.withdrawal_reserve' => 'Withdrawal reserved',
        'wallet_ledger.withdrawal_settle' => 'Withdrawal settled',
        'wallet_ledger.withdrawal_reject_release' => 'Withdrawal rejection released',
        'wallet_ledger.manual_adjustment_debit' => 'Manual debit adjustment',
        'wallet_ledger.manual_adjustment_credit' => 'Manual credit adjustment',
        'wallet_ledger.suspense_movement' => 'Suspense account movement',
        'wallet_ledger.legacy_backfill' => 'Legacy ledger backfill',
        'wallet_ledger.vendor_credit' => 'Vendor credit',

        // Ledger (withdrawal-scoped)
        'ledger.withdrawal_reserve' => 'Withdrawal reserved in ledger',
        'ledger.withdrawal_settle' => 'Withdrawal settled in ledger',
        'ledger.withdrawal_reject_release' => 'Withdrawal rejection released in ledger',

        // Withdrawals
        'withdrawal.requested' => 'Withdrawal requested',
        'withdrawal.approved' => 'Withdrawal approved',
        'withdrawal.paid' => 'Withdrawal marked as paid',
        'withdrawal.rejected' => 'Withdrawal rejected',

        // Chat
        'chat.frozen' => 'Chat frozen',
        'chat.unfrozen' => 'Chat unfrozen',
        'chat.flag_raised' => 'Chat flag raised',
        'chat.flag_resolved' => 'Chat flag resolved',
        'chat.system_message' => 'Chat system message posted',
        'chat.off_platform_marked' => 'Off-platform contact marked',
        'chat.escalated_to_admin' => 'Escalated to admin inbox',

        // Reviews
        'review.submitted' => 'Review submitted',
        'review.approved' => 'Review approved',
        'review.rejected' => 'Review rejected',
        'review.hidden' => 'Review hidden',
        'review.moderated' => 'Review moderated',

        // Notifications
        'notification.dispatched' => 'Notification dispatched',
        'notification.retry_queued' => 'Notification retry queued',
        'notification.push.queued' => 'Push notification queued',
        'notification.push.sent' => 'Push notification sent',
        'notification.push.delivered' => 'Push notification delivered',
        'notification.push.failed' => 'Push notification failed',
        'notification.push.bounced' => 'Push notification bounced',
        'notification.sms.queued' => 'SMS notification queued',
        'notification.sms.sent' => 'SMS notification sent',
        'notification.sms.delivered' => 'SMS notification delivered',
        'notification.sms.failed' => 'SMS notification failed',
        'notification.sms.bounced' => 'SMS notification bounced',
        'notification.whatsapp.queued' => 'WhatsApp notification queued',
        'notification.whatsapp.sent' => 'WhatsApp notification sent',
        'notification.whatsapp.delivered' => 'WhatsApp notification delivered',
        'notification.whatsapp.failed' => 'WhatsApp notification failed',
        'notification.whatsapp.bounced' => 'WhatsApp notification bounced',
        'notification.email.queued' => 'Email notification queued',
        'notification.email.sent' => 'Email notification sent',
        'notification.email.delivered' => 'Email notification delivered',
        'notification.email.failed' => 'Email notification failed',
        'notification.email.bounced' => 'Email notification bounced',
        'notification.in_app.queued' => 'In-app notification queued',
        'notification.in_app.sent' => 'In-app notification sent',
        'notification.in_app.delivered' => 'In-app notification delivered',
        'notification.in_app.failed' => 'In-app notification failed',
        'notification.in_app.bounced' => 'In-app notification bounced',

        // Audit log fallbacks
        'audit.entry' => 'Audit entry recorded',
        'audit.unknown' => 'Audit event recorded',
    ],

    'event_kind' => [
        'state_change' => 'State Change',
        'financial' => 'Financial',
        'moderation' => 'Moderation',
        'note' => 'Note',
        'document' => 'Document',
        'system' => 'System',
    ],

    'actor_role' => [
        'admin' => 'Admin',
        'vendor' => 'Vendor',
        'customer' => 'Customer',
        'system' => 'System',
        'webhook' => 'Webhook',
    ],

    'badges' => [
        'legacy_ledger_omitted' => ':count legacy ledger row(s) omitted — operator follow-up required',
        'anomalous_timestamp' => 'Anomalous timestamp',
        'untranslated_state' => 'Untranslated state — please add translation',
    ],

    'empty' => [
        'no_events' => 'No events yet',
    ],

    'fields' => [
        'actor' => 'Actor',
        'occurred_at' => 'When',
        'note' => 'Note',
    ],

    'filters' => [
        'all_event_kinds' => 'All event types',
        'all_actor_roles' => 'All actors',
    ],

    'pagination' => [
        'load_more' => 'Load more events',
    ],
];
