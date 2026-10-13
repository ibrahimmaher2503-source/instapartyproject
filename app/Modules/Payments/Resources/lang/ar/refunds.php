<?php

declare(strict_types=1);

return [
    'actions' => [
        'initiate' => 'استرداد',
        'confirm' => 'أكد الاسترداد الجزئي أو استرد المبلغ المحصل المتبقي. تظل سياسات استرداد الخدمات سارية.',
        'amount' => 'مبلغ الاسترداد',
        'amount_help' => 'القيمة الافتراضية هي المبلغ المتبقي بعد طلبات الاسترداد الحالية.',
        'note_en' => 'سبب الاسترداد (بالإنجليزية)',
        'note_ar' => 'سبب الاسترداد (بالعربية)',
        'recorded' => 'تم تسجيل طلب الاسترداد',
    ],
    'nav' => [
        'refunds' => 'المبالغ المستردة',
    ],
    'models' => [
        'refund' => [
            'singular' => 'مبلغ مسترد',
            'plural' => 'المبالغ المستردة',
        ],
    ],
    'columns' => [
        'public_id' => 'المعرّف العام',
        'booking' => 'الحجز',
        'payment' => 'الدفعة',
        'amount' => 'المبلغ',
        'reason_code' => 'سبب الاسترداد',
        'status' => 'الحالة',
        'created_at' => 'تاريخ الإنشاء',
    ],
    'reason_code' => [
        'customer_request' => 'طلب العميل',
        'vendor_cancellation' => 'إلغاء مقدم الخدمة',
        'service_unavailable' => 'الخدمة غير متاحة',
        'duplicate_charge' => 'رسوم مكررة',
        'admin_discretion' => 'قرار إداري',
    ],
    'status' => [
        'pending' => 'قيد الانتظار',
        'processing' => 'قيد المعالجة',
        'completed' => 'مكتمل',
        'failed' => 'فشل',
    ],
    'policy' => [
        'allowed' => 'مسموح',
        'rental_window_closed' => 'انتهت مهلة استرداد الإيجار.',
        'rental_in_setup' => 'بدأ تجهيز الإيجار بالفعل.',
        'sale_in_preparation' => 'عنصر البيع قيد التحضير بالفعل.',
        'digital_post_delivery' => 'لا يمكن استرداد العناصر الرقمية بعد التسليم.',
    ],
    'errors' => [
        'refund_in_progress' => 'يوجد استرداد لهذه الدفعة بانتظار التأكيد. انتظر نتيجته قبل بدء استرداد آخر.',
        'amount_positive' => 'أدخل مبلغ استرداد أكبر من صفر.',
        'partial_refund_unsupported' => 'الاسترداد الجزئي غير مدعوم في المرحلة الأولى.',
        'over_refund_attempted' => 'يتجاوز مبلغ الاسترداد المطلوب المبلغ المتبقي لهذه الدفعة.',
    ],
];
