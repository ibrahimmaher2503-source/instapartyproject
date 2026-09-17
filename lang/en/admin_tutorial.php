<?php

declare(strict_types=1);

return [
    'launcher' => [
        'label' => 'Tutorial',
        'aria_label' => 'Open the admin tutorial',
        'tooltip' => 'Step-by-step walkthrough of the admin flows',
        'unseen_badge' => 'New',
    ],
    'overlay' => [
        'heading' => 'Admin tutorial',
        'current_flow' => 'Current workflow',
        'flow_index' => 'Tutorial index',
        'flow_progress' => 'Flow :current of :total',
        'step_progress' => 'Step :current of :total',
        'goto_screen' => 'Open this screen',
        'next' => 'Next',
        'previous' => 'Previous',
        'finish' => 'Finish',
        'close' => 'Close',
        'skip' => 'Skip tutorial',
        'start_over' => 'Start over',
        'resumed' => 'Resumed where you left off.',
        'completed' => 'Tutorial complete. You can reopen it any time from the top bar.',
        'no_flows' => 'There are no tutorial flows available for your role.',
        'not_built' => 'The tutorial has not been generated yet. An administrator needs to run: php artisan admin:build-tutorial-manifest',
        'text_only_step' => 'This step is completed outside the admin panel.',
        'live_screen' => 'Live screen guide',
        'screenshot_pending' => 'Screenshots have not been captured for this flow yet. Use “Open this screen” to follow the real workflow in the admin panel.',
        'screenshot_alt' => 'Screenshot for :flow, step :step',
        'generic_step_body' => 'Open the linked screen and complete “:title”. Review the visible status, result, and audit information before moving to the next step. If the screen shows an exception or missing permission, resolve it before continuing.',
    ],
    'page' => [
        'navigation' => 'Tutorials',
        'title' => 'Admin tutorials',
        'eyebrow' => 'Operations handbook',
        'heading' => 'Admin tutorials',
        'description' => 'Browse every admin workflow in order. Each step includes the expected action, the reason it matters, and a direct link to the relevant screen.',
        'index' => 'Tutorial index',
        'steps_count' => ':count steps',
    ],
    'vendor-approval' => [
        'title' => 'Approve a new vendor',
        'step_1' => [
            'title' => 'Open the approval queue',
            'body' => "Start here when a new vendor registration needs a decision. Open the queue and work from the oldest pending application first.\n\nCheck that the list is filtered to pending vendors, then confirm the business type, email, document count, and submission date before opening a record.",
        ],
        'step_2' => [
            'title' => 'Open the vendor for review',
            'body' => "Select Review, or click the row, to inspect the application. Read the identity and business profile sections together.\n\nPay special attention to both English and Arabic business names. Missing Arabic content is a real approval risk because the vendor-facing experience is bilingual.",
        ],
        'step_3' => [
            'title' => 'Review the uploaded documents',
            'body' => "Open Documents and filter by the vendor under review. Open every required document and verify that it is present, readable, authentic, and not expired.\n\nIf a document link expires, reopen it from the resource. Do not approve an application with an expired commercial register, tax card, or national ID.",
        ],
        'step_4' => [
            'title' => 'Approve the vendor',
            'body' => "Return to the approval queue and choose Approve only after the profile and documents pass review. Confirm the action in the dialog.\n\nThe vendor leaves this queue after approval. That is expected. Approval enables the account, but it does not grant access to every product type.",
        ],
        'step_5' => [
            'title' => 'Reject instead, when the data is insufficient',
            'body' => "If the evidence is insufficient, choose Reject and write a clear reason that the vendor can act on. Explain what is missing or invalid, and provide the reason in English and Arabic whenever possible.\n\nA rejection reason is customer-facing operational content, not an internal note.",
        ],
        'step_6' => [
            'title' => 'Grant the product types the vendor may sell',
            'body' => "After approval, open All Vendors and find the vendor. Grant each approved product type separately: Rental, Sale, or Digital.\n\nThese permissions are independent. Approving rental services does not authorize sale or digital services. Grant only the types supported by the reviewed documents and business activity.",
        ],
        'step_7' => [
            'title' => 'Verify the Arabic experience',
            'body' => "Switch the admin panel to Arabic and repeat the key checks. Confirm that the layout is RTL, the Operations and Vendor Management groups are readable, and business type and approval status labels are translated.\n\nThis is a release check: Arabic is a primary operating language, not a translated afterthought.",
        ],
        'step_8' => [
            'title' => 'Confirm the audit entry',
            'body' => "Open Activity Log and confirm that the approval or rejection is recorded with the vendor, the acting admin, the status change, and the timestamp.\n\nIf the audit entry is missing, stop the workflow and report it before making another decision. The audit trail is the evidence that makes vendor approval accountable.",
        ],
    ],
    'service-moderation' => [
        'title' => 'Moderate a service or a service edit',
        'step_1' => [
            'title' => 'Open the pending queue for one product type',
            'body' => 'Follow step 1: Open the pending queue for one product type.',
        ],
        'step_2' => [
            'title' => 'Review the service content',
            'body' => 'Follow step 2: Review the service content.',
        ],
        'step_3' => [
            'title' => 'Approve the service',
            'body' => 'Follow step 3: Approve the service.',
        ],
        'step_4' => [
            'title' => 'Reject, or request edits instead',
            'body' => 'Follow step 4: Reject, or request edits instead.',
        ],
        'step_5' => [
            'title' => 'Open the pending service-edit queue',
            'body' => 'Follow step 5: Open the pending service-edit queue.',
        ],
        'step_6' => [
            'title' => 'Decide on a change request',
            'body' => 'Follow step 6: Decide on a change request.',
        ],
        'step_7' => [
            'title' => 'Verify in Arabic',
            'body' => 'Follow step 7: Verify in Arabic.',
        ],
        'step_8' => [
            'title' => 'Confirm the audit timeline',
            'body' => 'Follow step 8: Confirm the audit timeline.',
        ],
    ],
    'booking-intervention' => [
        'title' => 'Intervene in a stalled booking',
        'step_1' => [
            'title' => 'Open the negotiation monitor',
            'body' => 'Follow step 1: Open the negotiation monitor.',
        ],
        'step_2' => [
            'title' => 'Open the intervention queue',
            'body' => 'Follow step 2: Open the intervention queue.',
        ],
        'step_3' => [
            'title' => 'Send a vendor reminder (lightest touch)',
            'body' => 'Follow step 3: Send a vendor reminder (lightest touch).',
        ],
        'step_4' => [
            'title' => 'Extend the vendor\'s deadline',
            'body' => 'Follow step 4: Extend the vendor\'s deadline.',
        ],
        'step_5' => [
            'title' => 'Escalate a timed-out vendor',
            'body' => 'Follow step 5: Escalate a timed-out vendor.',
        ],
        'step_6' => [
            'title' => 'Suggest alternative vendors (never assign them)',
            'body' => 'Follow step 6: Suggest alternative vendors (never assign them).',
        ],
        'step_7' => [
            'title' => 'Freeze the chat when conduct is the problem',
            'body' => 'Follow step 7: Freeze the chat when conduct is the problem.',
        ],
        'step_8' => [
            'title' => 'Record a note and return the booking to review',
            'body' => 'Follow step 8: Record a note and return the booking to review.',
        ],
        'step_9' => [
            'title' => 'Verify in Arabic',
            'body' => 'Follow step 9: Verify in Arabic.',
        ],
    ],
    'withdrawal-approval' => [
        'title' => 'Approve and pay a vendor withdrawal',
        'step_1' => [
            'title' => 'Open the withdrawal queue',
            'body' => 'Follow step 1: Open the withdrawal queue.',
        ],
        'step_2' => [
            'title' => 'Review the request and the bank details',
            'body' => 'Follow step 2: Review the request and the bank details.',
        ],
        'step_3' => [
            'title' => 'Approve the request (step 1 of 2)',
            'body' => 'Follow step 3: Approve the request (step 1 of 2).',
        ],
        'step_4' => [
            'title' => 'Execute the bank transfer (outside the panel)',
            'body' => 'Follow step 4: Execute the bank transfer (outside the panel).',
        ],
        'step_5' => [
            'title' => 'Mark the withdrawal paid (step 2 of 2)',
            'body' => 'Follow step 5: Mark the withdrawal paid (step 2 of 2).',
        ],
        'step_6' => [
            'title' => 'Reject a request instead',
            'body' => 'Follow step 6: Reject a request instead.',
        ],
        'step_7' => [
            'title' => 'Verify against the ledger',
            'body' => 'Follow step 7: Verify against the ledger.',
        ],
        'step_8' => [
            'title' => 'Verify in Arabic',
            'body' => 'Follow step 8: Verify in Arabic.',
        ],
    ],
    'user-management' => [
        'title' => 'Provision an admin user and grant panel access',
        'step_1' => [
            'title' => 'Provision the user record (CLI)',
            'body' => 'Follow step 1: Provision the user record (CLI).',
        ],
        'step_2' => [
            'title' => 'Attach the role (CLI)',
            'body' => 'Follow step 2: Attach the role (CLI).',
        ],
        'step_3' => [
            'title' => 'Find the user in the panel',
            'body' => 'Follow step 3: Find the user in the panel.',
        ],
        'step_4' => [
            'title' => 'Verify the account and its roles',
            'body' => 'Follow step 4: Verify the account and its roles.',
        ],
        'step_5' => [
            'title' => 'Confirm the effective permissions',
            'body' => 'Follow step 5: Confirm the effective permissions.',
        ],
        'step_6' => [
            'title' => 'Verify in Arabic',
            'body' => 'Follow step 6: Verify in Arabic.',
        ],
        'step_7' => [
            'title' => 'Confirm the audit trail',
            'body' => 'Follow step 7: Confirm the audit trail.',
        ],
    ],
    'role-assignment' => [
        'title' => 'Manage roles and permissions',
        'step_1' => [
            'title' => 'Open the Roles resource',
            'body' => 'Follow step 1: Open the Roles resource.',
        ],
        'step_2' => [
            'title' => 'Open a role for editing',
            'body' => 'Follow step 2: Open a role for editing.',
        ],
        'step_3' => [
            'title' => 'Set the per-product-type service permissions',
            'body' => 'Follow step 3: Set the per-product-type service permissions.',
        ],
        'step_4' => [
            'title' => 'Set the finance permissions deliberately',
            'body' => 'Follow step 4: Set the finance permissions deliberately.',
        ],
        'step_5' => [
            'title' => 'Save and confirm the count',
            'body' => 'Follow step 5: Save and confirm the count.',
        ],
        'step_6' => [
            'title' => 'Understand `super_admin`',
            'body' => 'Follow step 6: Understand `super_admin`.',
        ],
        'step_7' => [
            'title' => 'Verify the effect on a real account',
            'body' => 'Follow step 7: Verify the effect on a real account.',
        ],
        'step_8' => [
            'title' => 'Verify in Arabic',
            'body' => 'Follow step 8: Verify in Arabic.',
        ],
    ],
    'audit-logs' => [
        'title' => 'Read the audit trail',
        'step_1' => [
            'title' => 'Open the activity log',
            'body' => 'Follow step 1: Open the activity log.',
        ],
        'step_2' => [
            'title' => 'Filter by event type',
            'body' => 'Follow step 2: Filter by event type.',
        ],
        'step_3' => [
            'title' => 'Filter by log name',
            'body' => 'Follow step 3: Filter by log name.',
        ],
        'step_4' => [
            'title' => 'Open a single entry',
            'body' => 'Follow step 4: Open a single entry.',
        ],
        'step_5' => [
            'title' => 'Read a record\'s own timeline instead',
            'body' => 'Follow step 5: Read a record\'s own timeline instead.',
        ],
        'step_6' => [
            'title' => 'Understand audience filtering',
            'body' => 'Follow step 6: Understand audience filtering.',
        ],
        'step_7' => [
            'title' => 'Verify in Arabic',
            'body' => 'Follow step 7: Verify in Arabic.',
        ],
    ],
    'system-configuration' => [
        'title' => 'Configure the platform',
        'step_1' => [
            'title' => 'Open the settings page',
            'body' => 'Follow step 1: Open the settings page.',
        ],
        'step_2' => [
            'title' => 'Edit an application setting',
            'body' => 'Follow step 2: Edit an application setting.',
        ],
        'step_3' => [
            'title' => 'Review the feature flags',
            'body' => 'Follow step 3: Review the feature flags.',
        ],
        'step_4' => [
            'title' => 'Toggle a feature flag',
            'body' => 'Follow step 4: Toggle a feature flag.',
        ],
        'step_5' => [
            'title' => 'Use the dedicated resources for bulk work',
            'body' => 'Follow step 5: Use the dedicated resources for bulk work.',
        ],
        'step_6' => [
            'title' => 'Review geography configuration',
            'body' => 'Follow step 6: Review geography configuration.',
        ],
        'step_7' => [
            'title' => 'Review taxonomy configuration',
            'body' => 'Follow step 7: Review taxonomy configuration.',
        ],
        'step_8' => [
            'title' => 'Verify in Arabic',
            'body' => 'Follow step 8: Verify in Arabic.',
        ],
    ],
    'reconciliation' => [
        'title' => 'Reconcile the financial ledger',
        'step_1' => [
            'title' => 'Open the reconciliation dashboard',
            'body' => 'Follow step 1: Open the reconciliation dashboard.',
        ],
        'step_2' => [
            'title' => 'Review recent runs',
            'body' => 'Follow step 2: Review recent runs.',
        ],
        'step_3' => [
            'title' => 'Open the findings',
            'body' => 'Follow step 3: Open the findings.',
        ],
        'step_4' => [
            'title' => 'Trace a finding to the ledger',
            'body' => 'Follow step 4: Trace a finding to the ledger.',
        ],
        'step_5' => [
            'title' => 'Resolve a known-issue finding',
            'body' => 'Follow step 5: Resolve a known-issue finding.',
        ],
        'step_6' => [
            'title' => 'Check the transaction groups',
            'body' => 'Follow step 6: Check the transaction groups.',
        ],
        'step_7' => [
            'title' => 'Review the daily snapshots',
            'body' => 'Follow step 7: Review the daily snapshots.',
        ],
        'step_8' => [
            'title' => 'Verify in Arabic',
            'body' => 'Follow step 8: Verify in Arabic.',
        ],
    ],
    'chat-moderation' => [
        'title' => 'Moderate customer–vendor chat',
        'step_1' => [
            'title' => 'Open the moderation flag queue',
            'body' => 'Follow step 1: Open the moderation flag queue.',
        ],
        'step_2' => [
            'title' => 'Open the flagged thread',
            'body' => 'Follow step 2: Open the flagged thread.',
        ],
        'step_3' => [
            'title' => 'Read the message log',
            'body' => 'Follow step 3: Read the message log.',
        ],
        'step_4' => [
            'title' => 'Resolve the flag',
            'body' => 'Follow step 4: Resolve the flag.',
        ],
        'step_5' => [
            'title' => 'Freeze the thread when conduct warrants it',
            'body' => 'Follow step 5: Freeze the thread when conduct warrants it.',
        ],
        'step_6' => [
            'title' => 'Check provider health if the log looks empty',
            'body' => 'Follow step 6: Check provider health if the log looks empty.',
        ],
        'step_7' => [
            'title' => 'Verify in Arabic',
            'body' => 'Follow step 7: Verify in Arabic.',
        ],
        'step_8' => [
            'title' => 'Confirm the audit trail',
            'body' => 'Follow step 8: Confirm the audit trail.',
        ],
    ],
    'excel-imports' => [
        'title' => 'Import vendor services from Excel',
        'step_1' => [
            'title' => 'Open the rental import page',
            'body' => 'Follow step 1: Open the rental import page.',
        ],
        'step_2' => [
            'title' => 'Download and inspect the rental template',
            'body' => 'Follow step 2: Download and inspect the rental template.',
        ],
        'step_3' => [
            'title' => 'Upload and run the import',
            'body' => 'Follow step 3: Upload and run the import.',
        ],
        'step_4' => [
            'title' => 'Review the import result',
            'body' => 'Follow step 4: Review the import result.',
        ],
        'step_5' => [
            'title' => 'Work through the errors',
            'body' => 'Follow step 5: Work through the errors.',
        ],
        'step_6' => [
            'title' => 'Repeat for sale',
            'body' => 'Follow step 6: Repeat for sale.',
        ],
        'step_7' => [
            'title' => 'Repeat for digital',
            'body' => 'Follow step 7: Repeat for digital.',
        ],
        'step_8' => [
            'title' => 'Moderate what was imported',
            'body' => 'Follow step 8: Moderate what was imported.',
        ],
        'step_9' => [
            'title' => 'Verify in Arabic',
            'body' => 'Follow step 9: Verify in Arabic.',
        ],
    ],
    'review-moderation' => [
        'title' => 'Moderate ratings and reviews',
        'step_1' => [
            'title' => 'Open the moderation page',
            'body' => 'Follow step 1: Open the moderation page.',
        ],
        'step_2' => [
            'title' => 'Review a service review',
            'body' => 'Follow step 2: Review a service review.',
        ],
        'step_3' => [
            'title' => 'Review a vendor review',
            'body' => 'Follow step 3: Review a vendor review.',
        ],
        'step_4' => [
            'title' => 'Decide on a review',
            'body' => 'Follow step 4: Decide on a review.',
        ],
        'step_5' => [
            'title' => 'Check the vendor\'s right of reply',
            'body' => 'Follow step 5: Check the vendor\'s right of reply.',
        ],
        'step_6' => [
            'title' => 'Read the moderation log',
            'body' => 'Follow step 6: Read the moderation log.',
        ],
        'step_7' => [
            'title' => 'Verify in Arabic',
            'body' => 'Follow step 7: Verify in Arabic.',
        ],
    ],
    'commission-rates' => [
        'title' => 'Set commission rates',
        'step_1' => [
            'title' => 'Open the commission rules',
            'body' => 'Follow step 1: Open the commission rules.',
        ],
        'step_2' => [
            'title' => 'Confirm a global default exists',
            'body' => 'Follow step 2: Confirm a global default exists.',
        ],
        'step_3' => [
            'title' => 'Create a per-type rate',
            'body' => 'Follow step 3: Create a per-type rate.',
        ],
        'step_4' => [
            'title' => 'Create a category × type rate',
            'body' => 'Follow step 4: Create a category × type rate.',
        ],
        'step_5' => [
            'title' => 'Verify resolution against a real booking',
            'body' => 'Follow step 5: Verify resolution against a real booking.',
        ],
        'step_6' => [
            'title' => 'Understand the snapshot boundary',
            'body' => 'Follow step 6: Understand the snapshot boundary.',
        ],
        'step_7' => [
            'title' => 'Verify in Arabic',
            'body' => 'Follow step 7: Verify in Arabic.',
        ],
    ],
    'support-tickets' => [
        'title' => 'Handle a support ticket',
        'step_1' => [
            'title' => 'Open the ticket queue',
            'body' => 'Follow step 1: Open the ticket queue.',
        ],
        'step_2' => [
            'title' => 'Open a ticket and read its context',
            'body' => 'Follow step 2: Open a ticket and read its context.',
        ],
        'step_3' => [
            'title' => 'Respond and set status',
            'body' => 'Follow step 3: Respond and set status.',
        ],
        'step_4' => [
            'title' => 'Check the routing rules when tickets land wrongly',
            'body' => 'Follow step 4: Check the routing rules when tickets land wrongly.',
        ],
        'step_5' => [
            'title' => 'Review the FAQ categories',
            'body' => 'Follow step 5: Review the FAQ categories.',
        ],
        'step_6' => [
            'title' => 'Turn a recurring ticket into an FAQ entry',
            'body' => 'Follow step 6: Turn a recurring ticket into an FAQ entry.',
        ],
        'step_7' => [
            'title' => 'Verify in Arabic',
            'body' => 'Follow step 7: Verify in Arabic.',
        ],
    ],
    'trust-safety' => [
        'title' => 'Act on a trust & safety report',
        'step_1' => [
            'title' => 'Open the report queue',
            'body' => 'Follow step 1: Open the report queue.',
        ],
        'step_2' => [
            'title' => 'Open a report and identify the subject',
            'body' => 'Follow step 2: Open a report and identify the subject.',
        ],
        'step_3' => [
            'title' => 'Investigate before deciding',
            'body' => 'Follow step 3: Investigate before deciding.',
        ],
        'step_4' => [
            'title' => 'Resolve the report',
            'body' => 'Follow step 4: Resolve the report.',
        ],
        'step_5' => [
            'title' => 'Suspend a vendor when warranted',
            'body' => 'Follow step 5: Suspend a vendor when warranted.',
        ],
        'step_6' => [
            'title' => 'Manage trust badges',
            'body' => 'Follow step 6: Manage trust badges.',
        ],
        'step_7' => [
            'title' => 'Verify in Arabic',
            'body' => 'Follow step 7: Verify in Arabic.',
        ],
        'step_8' => [
            'title' => 'Confirm the audit trail',
            'body' => 'Follow step 8: Confirm the audit trail.',
        ],
    ],
    'campaigns-notifications' => [
        'title' => 'Run a campaign and manage notification templates',
        'step_1' => [
            'title' => 'Review the notification templates',
            'body' => 'Follow step 1: Review the notification templates.',
        ],
        'step_2' => [
            'title' => 'Edit a template in both locales',
            'body' => 'Follow step 2: Edit a template in both locales.',
        ],
        'step_3' => [
            'title' => 'Check delivery of transactional notifications',
            'body' => 'Follow step 3: Check delivery of transactional notifications.',
        ],
        'step_4' => [
            'title' => 'Review notification preferences',
            'body' => 'Follow step 4: Review notification preferences.',
        ],
        'step_5' => [
            'title' => 'Create a campaign',
            'body' => 'Follow step 5: Create a campaign.',
        ],
        'step_6' => [
            'title' => 'Run it and watch the run',
            'body' => 'Follow step 6: Run it and watch the run.',
        ],
        'step_7' => [
            'title' => 'Check provider health when delivery looks wrong',
            'body' => 'Follow step 7: Check provider health when delivery looks wrong.',
        ],
        'step_8' => [
            'title' => 'Verify in Arabic',
            'body' => 'Follow step 8: Verify in Arabic.',
        ],
    ],
    'branding-and-social' => [
        'title' => 'Manage site branding and social links',
        'step_1' => ['title' => 'Open Branding from the sidebar', 'body' => 'Open Appearance, then Branding. This is the home for the public site identity, contacts, social links, and uploaded brand assets.'],
        'step_2' => ['title' => 'Update the public identity', 'body' => 'Enter the site name, tagline, and address in both English and Arabic. Save the form and confirm the success notification.'],
        'step_3' => ['title' => 'Update social links and contacts', 'body' => 'Update Instagram, Facebook, TikTok, X, YouTube, email, phone, or WhatsApp. The saved social values are normalized to absolute URLs.'],
        'step_4' => ['title' => 'Replace brand assets', 'body' => 'Upload the light and dark logos, favicon, Open Graph image, and app store badges. Check the previews before saving.'],
        'step_5' => ['title' => 'Manage colors and typography', 'body' => 'Use Manage colors and typography to edit design tokens, then activate the intended token set. Check colors, fonts, radius, and shadows.'],
        'step_6' => ['title' => 'Verify the public site in both locales', 'body' => 'Open the public site in English and Arabic. Check the header, footer, metadata, social icons, logos, favicon, and responsive layout.'],
    ],
];
