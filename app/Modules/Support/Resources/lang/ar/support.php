<?php

declare(strict_types=1);

return [
    'help' => [
        'contact_support' => 'تواصل مع الدعم',
        'ticket_title' => 'إرسال طلب دعم',
        'ticket_intro' => 'إذا كنت لا تزال بحاجة إلى مساعدة، أرسل التفاصيل واحتفظ بالمرجع الظاهر بعد الإرسال.',
        'ticket_subject' => 'الموضوع',
        'ticket_email' => 'البريد الإلكتروني للرد',
        'ticket_body' => 'كيف يمكننا مساعدتك؟',
        'ticket_submit' => 'إرسال الطلب',
        'ticket_received' => 'تم استلام طلب الدعم. المرجع: :reference',
    ],
    'ticket_status' => [
        'open' => 'مفتوحة', 'in_progress' => 'قيد المعالجة',
        'resolved' => 'تم الحل', 'closed' => 'مغلقة',
    ],
    'resource' => [
        'singular' => 'تذكرة دعم',
        'plural' => 'تذاكر الدعم',
        'details' => 'تفاصيل الطلب',
    ],
    'faq_categories' => [
        'singular' => 'تصنيف الأسئلة الشائعة',
        'plural' => 'تصنيفات الأسئلة الشائعة',
        'section' => 'تفاصيل التصنيف',
        'name' => 'الاسم',
        'slug' => 'الرابط المختصر',
        'sort_order' => 'ترتيب العرض',
        'is_active' => 'مفعّل',
    ],
    'columns' => [
        'reference' => 'المرجع', 'requester' => 'صاحب الطلب', 'subject' => 'الموضوع',
        'status' => 'الحالة', 'assignee' => 'المسؤول', 'email' => 'البريد الإلكتروني',
        'message' => 'الرسالة', 'created_at' => 'تاريخ الإنشاء', 'updated_at' => 'آخر تحديث',
    ],
    'actions' => ['mark_in_progress' => 'بدء المعالجة', 'resolve' => 'حل التذكرة'],
    'notifications' => ['in_progress' => 'نُقلت التذكرة إلى قيد المعالجة.', 'resolved' => 'تم حل التذكرة.'],
    'empty' => [
        'heading' => 'لا توجد تذاكر دعم',
        'description' => 'ستظهر طلبات الدعم الجديدة هنا.',
    ],
    'guest' => 'زائر',
    'unassigned' => 'غير معيّن',
];
