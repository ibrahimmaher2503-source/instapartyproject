<?php

declare(strict_types=1);

return [
    'ticket_status' => [
        'open' => 'Open', 'in_progress' => 'In progress',
        'resolved' => 'Resolved', 'closed' => 'Closed',
    ],
    'resource' => [
        'singular' => 'Support ticket',
        'plural' => 'Support tickets',
        'details' => 'Request details',
    ],
    'columns' => [
        'reference' => 'Reference', 'requester' => 'Requester', 'subject' => 'Subject',
        'status' => 'Status', 'assignee' => 'Assignee', 'email' => 'Email',
        'message' => 'Message', 'created_at' => 'Created at', 'updated_at' => 'Last updated',
    ],
    'actions' => ['mark_in_progress' => 'Start work', 'resolve' => 'Resolve'],
    'notifications' => ['in_progress' => 'Ticket moved to in progress.', 'resolved' => 'Ticket resolved.'],
    'empty' => [
        'heading' => 'No support tickets',
        'description' => 'New support requests will appear here.',
    ],
    'guest' => 'Guest',
    'unassigned' => 'Unassigned',
];
