<?php

declare(strict_types=1);

return [
    'navigation' => [
        'payments' => 'المدفوعات',
        'refunds' => 'طلبات الاسترداد',
        'attempts' => 'محاولات الدفع',
        'webhook_logs' => 'سجلات Webhook',
        'idempotency_keys' => 'مفاتيح منع التكرار',
    ],

    'models' => [
        'payment' => [
            'singular' => 'عملية دفع',
            'plural' => 'المدفوعات',
        ],
        'payment_attempt' => [
            'singular' => 'محاولة دفع',
            'plural' => 'محاولات الدفع',
        ],
        'webhook_log' => [
            'singular' => 'سجل Webhook',
            'plural' => 'سجلات Webhook',
        ],
        'idempotency_key' => [
            'singular' => 'مفتاح منع التكرار',
            'plural' => 'مفاتيح منع التكرار',
        ],
        'refund' => [
            'singular' => 'طلب استرداد',
            'plural' => 'طلبات الاسترداد',
        ],
    ],

    'columns' => [
        'public_id' => 'المعرّف العام',
        'booking' => 'الحجز',
        'gateway' => 'بوابة الدفع',
        'amount' => 'المبلغ',
        'method' => 'طريقة الدفع',
        'status' => 'الحالة',
        'captured_at' => 'تاريخ التحصيل',
        'payment' => 'عملية الدفع',
        'attempt_no' => 'رقم المحاولة',
        'http_status' => 'حالة HTTP',
        'event_type' => 'نوع الحدث',
        'signature_valid' => 'التوقيع صحيح',
        'processing_status' => 'حالة المعالجة',
        'processed_at' => 'تاريخ المعالجة',
        'key' => 'المفتاح',
        'route' => 'المسار',
        'response_status' => 'حالة الاستجابة',
        'expires_at' => 'ينتهي في',
    ],

    'event_types' => [
        'unknown' => 'حدث غير معروف',
        'payment_captured' => 'تم تحصيل الدفع',
        'payment_failed' => 'فشل الدفع',
        'payment_authorized' => 'تم تفويض الدفع',
        'refund_completed' => 'اكتمل رد المبلغ',
        'refund_failed' => 'فشل رد المبلغ',
    ],

    'signature_status' => [
        'valid' => 'صحيح',
        'invalid' => 'غير صحيح',
        'not_checked' => 'لم يتم التحقق',
    ],

    'processing_status' => [
        'pending' => 'قيد الانتظار',
        'processed' => 'تمت المعالجة',
        'duplicate' => 'مكرر (تم التعامل معه بأمان)',
        'failed' => 'فشلت المعالجة',
        'rejected' => 'مرفوض',
    ],

    'status' => [
        'pending' => 'قيد الانتظار',
        'authorized' => 'تم التفويض',
        'captured' => 'تم التحصيل',
        'failed' => 'فشل',
        'refunded' => 'تم رد المبلغ',
        'partially_refunded' => 'رد جزئي',
        'voided' => 'ملغى',
        'abandoned' => 'متروك',
    ],

    'method' => [
        'card' => 'بطاقة',
        'wallet' => 'محفظة إلكترونية',
        'installment' => 'تقسيط',
        'cash_on_delivery' => 'الدفع عند الاستلام',
        'transfer' => 'تحويل بنكي',
    ],

    'ops' => [
        'navigation_label' => 'لوحة العمليات',
        'page_title' => 'لوحة عمليات المدفوعات',

        'tabs' => [
            'failed_payments' => 'المدفوعات الفاشلة',
            'stuck_auths' => 'تفويضات متوقفة',
            'webhook_replay' => 'إعادة تشغيل Webhook',
            'chargebacks' => 'استردادات النزاع',
            'gateway_health' => 'صحة البوابة',
            'reconciliation' => 'فروق التسوية',
        ],

        'columns' => [
            'id' => 'المعرّف',
            'gateway_ref' => 'مرجع البوابة',
            'amount' => 'المبلغ',
            'failed_at' => 'تاريخ الفشل',
            'authorized_at' => 'تاريخ التفويض',
            'processed_at' => 'تاريخ المعالجة',
            'received_at' => 'تاريخ الاستلام',
            'checked_at' => 'تاريخ الفحص',
            'opened_at' => 'تاريخ الفتح',
            'resolved_at' => 'تاريخ الحل',
            'case_id' => 'رقم القضية',
            'latency_ms' => 'زمن الاستجابة (مللي ثانية)',
            'error' => 'خطأ',
        ],

        'placeholders' => [
            'not_processed' => 'لم تتم المعالجة',
            'pending' => 'قيد الانتظار',
            'none' => '—',
        ],

        'gateway_status' => [
            'ok' => 'يعمل',
            'fail' => 'فشل',
        ],

        'actions' => [
            'retry' => 'إعادة المحاولة',
            'abandon' => 'وضع علامة متروك',
            'capture' => 'تحصيل يدوي',
            'void' => 'إلغاء التفويض',
            'replay' => 'إعادة التشغيل',
            'open_chargeback' => 'فتح نزاع استرداد',
            'resolve' => 'حل النزاع',
        ],

        'forms' => [
            'reason_manual_capture' => 'سبب التحصيل اليدوي',
            'reason_void' => 'سبب الإلغاء',
            'payment_id' => 'معرّف عملية الدفع',
            'reason_en' => 'السبب (بالإنجليزية)',
            'reason_ar' => 'السبب (بالعربية)',
            'amount_minor' => 'المبلغ (الوحدات الصغيرة)',
            'gateway_case_id' => 'رقم قضية البوابة',
            'resolution' => 'القرار',
            'admin_notes_en' => 'ملاحظات الإدارة (EN)',
            'admin_notes_ar' => 'ملاحظات الإدارة (AR)',
            'resolution_won' => 'كسب (استرداد رصيد المورد)',
            'resolution_lost' => 'خسارة (إتمام الخصم)',
        ],

        'modal' => [
            'replay_description' => 'إعادة إرسال هذا الـ Webhook عبر المعالج. الإجراء مضمون عدم التكرار — لا إمكانية لخصم مزدوج.',
        ],

        'reconciliation_heading' => 'التسوية — المنصة: :platform | البوابة: :gateway | الفرق: :diff | المصدر: :source',
        'reconciliation_diff_none' => 'لا فرق ✓',
        'reconciliation_na' => 'غير متاح',

        'retry_queued' => 'تمت إضافة عملية الدفع لقائمة انتظار إعادة المحاولة.',
        'abandoned' => 'تم تحديد عملية الدفع كمتروكة.',
        'captured' => 'تم تحصيل عملية الدفع يدوياً.',
        'voided' => 'تم إلغاء التفويض.',
        'webhook_replayed' => 'تمت إعادة تشغيل Webhook.',
    ],

    'refund' => [
        'columns' => [
            'public_id' => 'المعرّف العام',
            'booking_reference' => 'مرجع الحجز',
            'payment_reference' => 'مرجع الدفع',
            'booking_id' => 'الحجز',
            'amount' => 'المبلغ',
            'reason_code' => 'رمز السبب',
            'status' => 'الحالة',
            'created_at' => 'تاريخ الإنشاء',
        ],
    ],
    'identity' => [
        'legacy_unknown' => 'قديم / غير معروف',
    ],
];
