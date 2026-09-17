<?php

declare(strict_types=1);

return array_replace_recursive(
    require base_path('app/Modules/Tax/Resources/lang/en/tax.php'),
    require base_path('app/Modules/Tax/Resources/lang/ar/tax.php'),
    [
        'report_page' => 'تقرير الضريبة',
        'rate' => 'معدل الضريبة',
        'rates' => 'معدلات الضريبة',
    ],
);
