<?php

declare(strict_types=1);

return [
    'help' => [
        'contact_support' => 'Contact support',
        'ticket_title' => 'Send a support request',
        'ticket_intro' => 'If you still need help, send us the details and keep the reference shown after submission.',
        'ticket_subject' => 'Subject',
        'ticket_email' => 'Email for a reply',
        'ticket_body' => 'How can we help?',
        'ticket_submit' => 'Send request',
        'ticket_received' => 'Your support request was received. Reference: :reference',
    ],
    'ticket_status' => [
        'open' => 'Open', 'in_progress' => 'In progress',
        'resolved' => 'Resolved', 'closed' => 'Closed',
    ],
    'resource' => [
        'singular' => 'Support ticket',
        'plural' => 'Support tickets',
        'details' => 'Request details',
    ],
    'faq_categories' => [
        'singular' => 'FAQ category',
        'plural' => 'FAQ categories',
        'section' => 'Category details',
        'name' => 'Name',
        'slug' => 'Short link',
        'sort_order' => 'Display order',
        'is_active' => 'Active',
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
