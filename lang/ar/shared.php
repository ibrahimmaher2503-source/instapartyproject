<?php

declare(strict_types=1);

return array_replace_recursive(
    require base_path('app/Modules/Shared/Resources/lang/en/shared.php'),
    require base_path('app/Modules/Shared/Resources/lang/ar/shared.php'),
    [
        'branding' => [
            'nav_label' => 'الهوية البصرية',
        ],
        'design_token' => [
            'singular' => 'رمز تصميم',
            'plural' => 'رموز التصميم',
        ],
        'nav_menu' => [
            'singular' => 'قائمة تنقل',
            'plural' => 'قوائم التنقل',
        ],
        'home_block' => [
            'singular' => 'كتلة الصفحة الرئيسية',
            'plural' => 'كتل الصفحة الرئيسية',
        ],
        'settings' => [
            'nav_label' => 'إعدادات التطبيق',
            'app_setting_singular' => 'إعداد تطبيق',
            'app_setting_plural' => 'إعدادات التطبيق',
            'feature_flag_singular' => 'علامة ميزة',
            'feature_flag_plural' => 'علامات الميزات',
        ],
    ],
);
