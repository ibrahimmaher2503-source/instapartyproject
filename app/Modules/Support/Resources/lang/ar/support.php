<?php

declare(strict_types=1);

return [
    'ticket_status' => [
        'open' => 'مفتوحة', 'in_progress' => 'قيد المعالجة',
        'resolved' => 'تم الحل', 'closed' => 'مغلقة',
    ],
    'resource' => [
        'singular' => 'تذكرة دعم',
        'plural' => 'تذاكر الدعم',
        'details' => 'تفاصيل الطلب',
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
