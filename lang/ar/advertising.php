<?php

declare(strict_types=1);

return array_replace_recursive(
    require base_path('app/Modules/Advertising/Resources/lang/en/advertising.php'),
    require base_path('app/Modules/Advertising/Resources/lang/ar/advertising.php'),
    [
        'analytics' => 'تحليلات الإعلانات',
        'package' => 'باقة إعلانية',
        'packages' => 'باقات الإعلانات',
        'subscription' => 'اشتراك إعلاني',
        'subscriptions' => 'اشتراكات الإعلانات',
    ],
);
