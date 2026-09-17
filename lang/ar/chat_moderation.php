<?php

declare(strict_types=1);

return array_replace_recursive(
    require base_path('app/Modules/Communication/Resources/lang/en/chat_moderation.php'),
    require base_path('app/Modules/Communication/Resources/lang/ar/chat_moderation.php'),
    [
        'nav' => [
            'restricted_chat' => 'المحادثات المقيدة',
            'moderation_flags' => 'بلاغات الإشراف',
            'message_log' => 'سجل الرسائل',
        ],
        'columns' => [
            'thread' => 'المحادثة', 'sender' => 'المرسل', 'flag_reason' => 'سبب البلاغ',
            'created_at' => 'تاريخ الإنشاء', 'flagged' => 'مبلّغ عنها', 'redacted' => 'محجوبة',
            'flag_type' => 'نوع البلاغ', 'matched_pattern' => 'النمط المطابق',
            'action_taken' => 'الإجراء المتخذ', 'reviewed_at' => 'تاريخ المراجعة', 'reviewer' => 'المراجع',
            'waiting_time' => 'مدة الانتظار',
        ],
        'fields' => ['flag_type' => 'نوع البلاغ'],
        'notifications' => [
            'marked_off_platform' => 'تم تعليم الرسالة كتواصل خارج المنصة.',
            'flag_resolved' => 'تم حسم البلاغ.', 'flag_escalated' => 'تم تصعيد البلاغ.',
        ],
        'sections' => ['message_details' => 'تفاصيل الرسالة', 'flag_details' => 'تفاصيل البلاغ'],
        'filters' => ['reviewed' => 'تمت مراجعته', 'created_from' => 'أُنشئ من', 'created_to' => 'أُنشئ حتى'],
        'unresolved' => 'غير محسوم',
    ],
);
