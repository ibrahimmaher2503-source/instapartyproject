<?php

declare(strict_types=1);

return array_replace_recursive(
    require base_path('app/Modules/Communication/Resources/lang/en/chat_moderation.php'),
    [
        'nav' => [
            'restricted_chat' => 'Restricted Chat',
            'moderation_flags' => 'Moderation Flags',
            'message_log' => 'Message Log',
        ],
        'columns' => [
            'thread' => 'Thread', 'sender' => 'Sender', 'flag_reason' => 'Flag reason',
            'created_at' => 'Created at', 'flagged' => 'Flagged', 'redacted' => 'Redacted',
            'flag_type' => 'Flag type', 'matched_pattern' => 'Matched pattern',
            'action_taken' => 'Action taken', 'reviewed_at' => 'Reviewed at', 'reviewer' => 'Reviewer',
            'waiting_time' => 'Waiting Time',
        ],
        'fields' => ['flag_type' => 'Flag type'],
        'notifications' => [
            'marked_off_platform' => 'Message marked as off-platform.',
            'flag_resolved' => 'Flag resolved.', 'flag_escalated' => 'Flag escalated.',
        ],
        'sections' => ['message_details' => 'Message details', 'flag_details' => 'Flag details'],
        'filters' => ['reviewed' => 'Reviewed', 'created_from' => 'Created from', 'created_to' => 'Created to'],
        'unresolved' => 'Unresolved',
    ],
);
