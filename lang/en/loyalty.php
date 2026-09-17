<?php

declare(strict_types=1);

return array_replace_recursive(
    require base_path('app/Modules/Loyalty/Resources/lang/en/loyalty.php'),
    [
        'nav' => [
            'programs' => 'Programs',
            'rules' => 'Rules',
            'ledger' => 'Ledger',
            'redemptions' => 'Redemptions',
        ],
        'models' => [
            'program' => [
                'singular' => 'Program',
                'plural' => 'Programs',
            ],
            'rule' => [
                'singular' => 'Rule',
                'plural' => 'Rules',
            ],
            'ledger_entry' => [
                'singular' => 'Ledger Entry',
                'plural' => 'Ledger',
            ],
            'redemption' => [
                'singular' => 'Redemption',
                'plural' => 'Redemptions',
            ],
        ],
    ],
);
