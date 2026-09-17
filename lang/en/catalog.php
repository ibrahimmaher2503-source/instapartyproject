<?php

declare(strict_types=1);

return array_replace_recursive(
    require base_path('app/Modules/Catalog/Resources/lang/en/catalog.php'),
    [
        'service_change_request_items_count' => 'Changed fields',
        'clarification_round' => 'Clarification round',
        'submitted_at' => 'Submitted at',
        'service_change_request_status_pending' => 'Pending',
        'service_change_request_status_awaiting_clarification' => 'Awaiting clarification',
        'service_change_request_approve' => 'Approve changes',
        'admin_note_en' => 'Admin note (English)',
        'admin_note_ar' => 'Admin note (Arabic)',
        'service_change_request_approved_successfully' => 'Service changes approved.',
        'service_change_request_version_conflict' => 'The service changed during review.',
        'service_change_request_version_conflict_body' => 'Reload the latest version before deciding.',
        'service_change_request_reject' => 'Reject changes',
        'service_change_request_rejected_successfully' => 'Service changes rejected.',
        'service_change_request_request_clarification' => 'Request clarification',
        'service_change_request_clarification_sent' => 'Clarification request sent.',
        'service_name' => 'Service name',
    ],
);
