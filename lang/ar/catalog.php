<?php

declare(strict_types=1);

return array_replace_recursive(
    require base_path('app/Modules/Catalog/Resources/lang/en/catalog.php'),
    require base_path('app/Modules/Catalog/Resources/lang/ar/catalog.php'),
    [
        'nav' => [
            'categories' => 'التصنيفات',
            'pending_service_edits' => 'تعديلات الخدمات المعلقة',
        ],
        'models' => [
            'category' => [
                'singular' => 'تصنيف',
                'plural' => 'التصنيفات',
            ],
        ],
        'service_change_request_items_count' => 'الحقول المعدلة',
        'clarification_round' => 'جولة الاستيضاح',
        'submitted_at' => 'تاريخ الإرسال',
        'service_change_request_status_pending' => 'قيد الانتظار',
        'service_change_request_status_awaiting_clarification' => 'بانتظار الاستيضاح',
        'service_change_request_approve' => 'اعتماد التعديلات',
        'admin_note_en' => 'ملاحظة الإدارة بالإنجليزية',
        'admin_note_ar' => 'ملاحظة الإدارة بالعربية',
        'service_change_request_approved_successfully' => 'تم اعتماد تعديلات الخدمة.',
        'service_change_request_version_conflict' => 'تغيرت الخدمة أثناء المراجعة.',
        'service_change_request_version_conflict_body' => 'أعد تحميل أحدث نسخة قبل اتخاذ القرار.',
        'service_change_request_reject' => 'رفض التعديلات',
        'service_change_request_rejected_successfully' => 'تم رفض تعديلات الخدمة.',
        'service_change_request_request_clarification' => 'طلب استيضاح',
        'service_change_request_clarification_sent' => 'تم إرسال طلب الاستيضاح.',
        'service_name' => 'اسم الخدمة',
    ],
);
