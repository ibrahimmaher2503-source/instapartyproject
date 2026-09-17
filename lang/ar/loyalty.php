<?php

declare(strict_types=1);

return array_replace_recursive(
    require base_path('app/Modules/Loyalty/Resources/lang/en/loyalty.php'),
    require base_path('app/Modules/Loyalty/Resources/lang/ar/loyalty.php'),
    [
        'nav' => [
            'programs' => 'البرامج',
            'rules' => 'القواعد',
            'ledger' => 'السجل',
            'redemptions' => 'عمليات الاستبدال',
        ],
        'models' => [
            'program' => [
                'singular' => 'برنامج',
                'plural' => 'البرامج',
            ],
            'rule' => [
                'singular' => 'قاعدة',
                'plural' => 'القواعد',
            ],
            'ledger_entry' => [
                'singular' => 'قيد سجل',
                'plural' => 'السجل',
            ],
            'redemption' => [
                'singular' => 'عملية استبدال',
                'plural' => 'عمليات الاستبدال',
            ],
        ],
    ],
);
