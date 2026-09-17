<?php

declare(strict_types=1);

return [
    'section_title' => 'سجل الأحداث',

    'action_keys' => [
        // البائع
        'vendor.state_changed' => 'تغيّرت حالة موافقة البائع',
        'vendor.approved_for_rental' => 'تمت الموافقة على البائع للخدمات الإيجارية',
        'vendor.approved_for_sale' => 'تمت الموافقة على البائع لخدمات البيع',
        'vendor.approved_for_digital' => 'تمت الموافقة على البائع للخدمات الرقمية',
        'vendor.suspended' => 'تم تعليق البائع',
        'vendor.profile_updated' => 'تم تحديث الملف الشخصي للبائع',
        'vendor.document_uploaded' => 'تم رفع المستند',
        'vendor.document_rejected' => 'تم رفض المستند',
        'vendor.document_approved' => 'تمت الموافقة على المستند',

        // الخدمة
        'service.state_changed' => 'تغيّرت حالة الخدمة',
        'service.published' => 'تم نشر الخدمة',
        'service.unpublished' => 'تم إلغاء نشر الخدمة',
        'service.archived' => 'تم أرشفة الخدمة',
        'service.profile_updated' => 'تم تحديث بيانات الخدمة',
        'service.change_request.submitted' => 'تم تقديم طلب التعديل',
        'service.change_request.approved' => 'تمت الموافقة على طلب التعديل',
        'service.change_request.rejected' => 'تم رفض طلب التعديل',
        'service.change_request.clarification_requested' => 'تم طلب توضيح',
        'service.change_request.cancelled' => 'تم إلغاء طلب التعديل',
        'service.change_request.decided' => 'تم البتّ في طلب التعديل',

        // تحوّلات حالة دورة حياة الحجز
        'booking.state_changed' => 'تغيّرت حالة الحجز',
        'state_transition.booking.draft' => 'تم إنشاء الحجز كمسودة',
        'state_transition.booking.submitted' => 'تم تقديم الحجز من قِبل العميل',
        'state_transition.booking.vendor_review' => 'أُرسل إلى البائع للمراجعة',
        'state_transition.booking.customer_review' => 'في انتظار مراجعة العميل',
        'state_transition.booking.confirmed' => 'تم تأكيد الحجز من قِبل البائع',
        'state_transition.booking.active' => 'بدأت الفعالية',
        'state_transition.booking.completed' => 'اكتمل الحجز',
        'state_transition.booking.cancelled' => 'تم إلغاء الحجز',

        // تحوّلات حالة بائع الحجز
        'state_transition.bookingvendor.pending' => 'تعيين البائع قيد الانتظار',
        'state_transition.bookingvendor.sent' => 'تم إرسال الحجز إلى البائع',
        'state_transition.bookingvendor.accepted' => 'قبل البائع الحجز',
        'state_transition.bookingvendor.rejected' => 'رفض البائع الحجز',
        'state_transition.bookingvendor.modified' => 'تم تعديل الحجز من قِبل البائع',
        'state_transition.bookingvendor.cancelled' => 'تم إلغاء تعيين البائع',
        'state_transition.bookingvendor.modification_proposed' => 'اقترح البائع تعديلاً',
        'state_transition.bookingvendor.fulfilled' => 'البائع علّم الخدمة كمنجزة',

        // تحوّلات حالة بنود الحجز
        'state_transition.bookingitem.pending' => 'العنصر قيد الانتظار',
        'state_transition.bookingitem.confirmed' => 'تم تأكيد العنصر',
        'state_transition.bookingitem.in_preparation' => 'العنصر قيد التحضير',
        'state_transition.bookingitem.ready' => 'العنصر جاهز',
        'state_transition.bookingitem.delivered' => 'تم تسليم العنصر',
        'state_transition.bookingitem.setup' => 'جارٍ تركيب العنصر',
        'state_transition.bookingitem.active' => 'العنصر نشط في الفعالية',
        'state_transition.bookingitem.completed' => 'اكتمل العنصر',
        'state_transition.bookingitem.cancelled' => 'تم إلغاء العنصر',

        // تعديلات الحجز
        'booking.modification.proposed' => 'اقتُرح تعديل',
        'booking.modification.accepted' => 'تم قبول التعديل',
        'booking.modification.rejected' => 'تم رفض التعديل',
        'booking.modification.cancelled' => 'تم إلغاء التعديل',
        'booking_modification.draft' => 'تم إنشاء مسودة التعديل',
        'booking_modification.pending' => 'التعديل قيد مراجعة العميل',
        'booking_modification.customer_accepted' => 'قبل العميل التعديل',
        'booking_modification.customer_rejected' => 'رفض العميل التعديل',
        'booking_modification.withdrawn' => 'سحب البائع عرض التعديل',
        'booking_modification.expired' => 'انتهت صلاحية عرض التعديل',

        // المدفوعات
        'payment.pending' => 'الدفع قيد الانتظار',
        'payment.authorized' => 'تم تفويض الدفع',
        'payment.captured' => 'تم استلام الدفع',
        'payment.failed' => 'فشل الدفع',
        'payment.refunded' => 'تم استرداد الدفع',
        'payment.partially_refunded' => 'تم استرداد الدفع جزئياً',
        'payment.voided' => 'تم إلغاء الدفع',
        'payment.abandoned' => 'تم التخلي عن الدفع',
        'payment.attempt.succeeded' => 'نجحت محاولة الدفع',
        'payment.attempt.failed' => 'فشلت محاولة الدفع',
        'payment.refund.initiated' => 'بدأ الاسترداد',
        'payment.refund.processing' => 'جارٍ معالجة الاسترداد',
        'payment.refund.completed' => 'اكتمل الاسترداد',
        'payment.refund.failed' => 'فشل الاسترداد',

        // المبالغ المستردة
        'refund.initiated' => 'بدأ الاسترداد',
        'refund.processing' => 'جارٍ معالجة الاسترداد',
        'refund.completed' => 'اكتمل الاسترداد',
        'refund.failed' => 'فشل الاسترداد',
        'refund.processed' => 'تمت معالجة الاسترداد',
        'refund.rejected' => 'تم رفض الاسترداد',

        // قيود دفتر الأستاذ
        'wallet_ledger.commission_credit' => 'إضافة عمولة',
        'wallet_ledger.refund_debit' => 'خصم استرداد',
        'wallet_ledger.withdrawal_debit' => 'خصم سحب',
        'wallet_ledger.manual_adjustment' => 'تعديل يدوي على الدفتر',
        'wallet_ledger.payment_capture' => 'إضافة دفعة مُستلمة للمحفظة',
        'wallet_ledger.refund_credit_customer' => 'إضافة استرداد للعميل',
        'wallet_ledger.refund_debit_platform' => 'خصم استرداد من المنصة',
        'wallet_ledger.commission_accrual' => 'استحقاق العمولة',
        'wallet_ledger.commission_reversal' => 'عكس العمولة',
        'wallet_ledger.withdrawal_reserve' => 'حجز مبلغ السحب',
        'wallet_ledger.withdrawal_settle' => 'تسوية السحب',
        'wallet_ledger.withdrawal_reject_release' => 'الإفراج عن المبلغ المحجوز للسحب المرفوض',
        'wallet_ledger.manual_adjustment_debit' => 'تعديل خصم يدوي',
        'wallet_ledger.manual_adjustment_credit' => 'تعديل إضافة يدوي',
        'wallet_ledger.suspense_movement' => 'حركة حساب التعليق',
        'wallet_ledger.legacy_backfill' => 'ترحيل بيانات الدفتر القديم',
        'wallet_ledger.vendor_credit' => 'إضافة رصيد للبائع',

        // دفتر الأستاذ (نطاق السحب)
        'ledger.withdrawal_reserve' => 'حجز مبلغ السحب في الدفتر',
        'ledger.withdrawal_settle' => 'تسوية السحب في الدفتر',
        'ledger.withdrawal_reject_release' => 'الإفراج عن السحب المرفوض في الدفتر',

        // السحب
        'withdrawal.requested' => 'تم طلب السحب',
        'withdrawal.approved' => 'تمت الموافقة على السحب',
        'withdrawal.paid' => 'تم تحديد السحب كمدفوع',
        'withdrawal.rejected' => 'تم رفض السحب',

        // المحادثة
        'chat.frozen' => 'تم تجميد المحادثة',
        'chat.unfrozen' => 'تم رفع تجميد المحادثة',
        'chat.flag_raised' => 'تم رفع تقرير للمحادثة',
        'chat.flag_resolved' => 'تم حل التقرير',
        'chat.system_message' => 'تم نشر رسالة نظام',
        'chat.off_platform_marked' => 'تم تحديد تواصل خارج المنصة',
        'chat.escalated_to_admin' => 'تم التصعيد إلى صندوق وارد الإدارة',

        // التقييمات
        'review.submitted' => 'تم تقديم التقييم',
        'review.approved' => 'تمت الموافقة على التقييم',
        'review.rejected' => 'تم رفض التقييم',
        'review.hidden' => 'تم إخفاء التقييم',
        'review.moderated' => 'تمت مراجعة التقييم',

        // الإشعارات
        'notification.dispatched' => 'تم إرسال الإشعار',
        'notification.retry_queued' => 'تمت إعادة إضافة الإشعار للطابور',
        'notification.push.queued' => 'إشعار فوري في الطابور',
        'notification.push.sent' => 'تم إرسال الإشعار الفوري',
        'notification.push.delivered' => 'تم تسليم الإشعار الفوري',
        'notification.push.failed' => 'فشل إرسال الإشعار الفوري',
        'notification.push.bounced' => 'ارتدّ الإشعار الفوري',
        'notification.sms.queued' => 'رسالة SMS في الطابور',
        'notification.sms.sent' => 'تم إرسال رسالة SMS',
        'notification.sms.delivered' => 'تم تسليم رسالة SMS',
        'notification.sms.failed' => 'فشل إرسال رسالة SMS',
        'notification.sms.bounced' => 'ارتدّت رسالة SMS',
        'notification.whatsapp.queued' => 'رسالة واتساب في الطابور',
        'notification.whatsapp.sent' => 'تم إرسال رسالة واتساب',
        'notification.whatsapp.delivered' => 'تم تسليم رسالة واتساب',
        'notification.whatsapp.failed' => 'فشل إرسال رسالة واتساب',
        'notification.whatsapp.bounced' => 'ارتدّت رسالة واتساب',
        'notification.email.queued' => 'بريد إلكتروني في الطابور',
        'notification.email.sent' => 'تم إرسال البريد الإلكتروني',
        'notification.email.delivered' => 'تم تسليم البريد الإلكتروني',
        'notification.email.failed' => 'فشل إرسال البريد الإلكتروني',
        'notification.email.bounced' => 'ارتدّ البريد الإلكتروني',
        'notification.in_app.queued' => 'إشعار داخل التطبيق في الطابور',
        'notification.in_app.sent' => 'تم إرسال الإشعار داخل التطبيق',
        'notification.in_app.delivered' => 'تم تسليم الإشعار داخل التطبيق',
        'notification.in_app.failed' => 'فشل إرسال الإشعار داخل التطبيق',
        'notification.in_app.bounced' => 'ارتدّ الإشعار داخل التطبيق',

        // سجل التدقيق (احتياطي)
        'audit.entry' => 'تم تسجيل حدث تدقيق',
        'audit.unknown' => 'تم تسجيل حدث تدقيق',
    ],

    'event_kind' => [
        'state_change' => 'تغيير الحالة',
        'financial' => 'مالي',
        'moderation' => 'إشراف',
        'note' => 'ملاحظة',
        'document' => 'مستند',
        'system' => 'نظام',
    ],

    'actor_role' => [
        'admin' => 'مسؤول',
        'vendor' => 'بائع',
        'customer' => 'عميل',
        'system' => 'النظام',
        'webhook' => 'ويب هوك',
    ],

    'badges' => [
        'legacy_ledger_omitted' => 'تم حذف :count صف/صفوف من السجل القديم — يلزم متابعة المشغل',
        'anomalous_timestamp' => 'طابع زمني غير طبيعي',
        'untranslated_state' => 'حالة غير مترجمة — يرجى إضافة الترجمة',
    ],

    'empty' => [
        'no_events' => 'لا توجد أحداث بعد',
    ],

    'fields' => [
        'actor' => 'المنفذ',
        'occurred_at' => 'الوقت',
        'note' => 'ملاحظة',
    ],

    'filters' => [
        'all_event_kinds' => 'جميع أنواع الأحداث',
        'all_actor_roles' => 'جميع المنفذين',
    ],

    'pagination' => [
        'load_more' => 'تحميل المزيد من الأحداث',
    ],
];
