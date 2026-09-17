<?php

declare(strict_types=1);

return [
    // General
    'vendor_profile' => 'ملف مقدم الخدمة',
    'vendor_profiles' => 'ملفات مقدمي الخدمات',
    'vendor_document' => 'مستند مقدم الخدمة',
    'vendor_documents' => 'مستندات مقدمي الخدمات',
    'customer' => 'العميل',
    'vendor' => 'مقدم الخدمة',
    'admin' => 'المسؤول',

    // Navigation labels
    'nav' => [
        'users' => 'المستخدمون',
        'customer_profiles' => 'ملفات العملاء',
        'customer_addresses' => 'عناوين العملاء',
        'devices' => 'أجهزة المستخدمين',
        'coverage_areas' => 'مناطق التغطية',
        'business_hours' => 'ساعات العمل',
        'approved_product_types' => 'أنواع المنتجات المعتمدة',
        'queue' => 'قائمة الانتظار',
        'all_vendors' => 'جميع مقدمي الخدمات',
        'vendor_management' => 'إدارة مقدمي الخدمات',
        'documents' => 'المستندات',
        'customers' => 'العملاء',
    ],

    'models' => [
        'user' => [
            'singular' => 'مستخدم',
            'plural' => 'المستخدمون',
        ],
        'customer_profile' => [
            'singular' => 'ملف عميل',
            'plural' => 'ملفات العملاء',
        ],
        'customer_address' => [
            'singular' => 'عنوان عميل',
            'plural' => 'عناوين العملاء',
        ],
        'user_device' => [
            'singular' => 'جهاز مستخدم',
            'plural' => 'أجهزة المستخدمين',
        ],
        'vendor_profile' => [
            'singular' => 'ملف مقدم الخدمة',
            'plural' => 'ملفات مقدمي الخدمات',
        ],
        'vendor_document' => [
            'singular' => 'مستند مقدم الخدمة',
            'plural' => 'مستندات مقدمي الخدمات',
        ],
        'coverage_area' => [
            'singular' => 'منطقة تغطية',
            'plural' => 'مناطق التغطية',
        ],
        'business_hour' => [
            'singular' => 'ساعات عمل',
            'plural' => 'ساعات العمل',
        ],
        'approved_product_type' => [
            'singular' => 'نوع منتج معتمد',
            'plural' => 'أنواع المنتجات المعتمدة',
        ],
    ],

    // Infolist / form section titles
    'sections' => [
        'identity' => 'الهوية',
        'business_profile' => 'بيانات النشاط التجاري',
        'banking' => 'البيانات البنكية',
        'bank_information' => 'بيانات البنك',
        'uploaded_documents' => 'المستندات المرفوعة',
        'approved_product_types' => 'أنواع المنتجات المعتمدة',
        'business_hours' => 'ساعات العمل',
        'coverage_areas' => 'مناطق التغطية',
        'services' => 'الخدمات',
        'bookings' => 'الحجوزات',
        'wallet' => 'المحفظة',
        'withdrawals' => 'السحوبات',
        'reviews' => 'التقييمات',
        'activity' => 'سجل النشاط',
        'documents' => 'المستندات',
        'location' => 'الموقع',
    ],

    // Compliance / document expiry section
    'compliance' => [
        'document_context' => 'بيانات المستند والمراجعة',
        'delete_document_warning' => 'هل تريد حذف هذا المستند نهائيًا؟ لا يمكن التراجع عن هذا الإجراء.',
        'expiry_and_criticality' => 'تاريخ الانتهاء والأهمية',
        'document_expired' => 'انتهت صلاحية المستند بتاريخ :date.',
        'auto_suspended' => 'تم الإيقاف تلقائيًا لانتهاء مستند إلزامي وحساس بتاريخ :date.',
        'expiry_reminder' => 'تذكير بانتهاء المستند: متبقي :days يوم.',
    ],

    // Activity log column labels
    'activity' => [
        'description' => 'الوصف',
        'caused_by' => 'بواسطة',
        'properties' => 'التفاصيل',
    ],

    // Filters
    'filters' => [
        'approved_product_type' => 'نوع المنتج المعتمد',
        'status_active' => 'نشط',
        'status_suspended' => 'موقوف',
    ],

    // Empty state messages
    'empty_states' => [
        'no_bookings' => 'لا توجد حجوزات بعد.',
        'no_reviews' => 'لا توجد تقييمات بعد.',
        'no_loyalty' => 'لا توجد أرصدة ولاء بعد.',
        'no_addresses' => 'لا توجد عناوين محفوظة بعد.',
        'no_activity' => 'لا يوجد نشاط مسجل بعد.',
        'no_documents' => 'لم يتم رفع أي مستندات.',
    ],

    // Modal descriptions
    'modals' => [
        'suspend_customer' => 'هل أنت متأكد من تعليق حساب :name؟ سيتم إنهاء جلسته فوراً.',
        'force_logout_customer' => 'سيتم إلغاء جميع الجلسات النشطة لـ :name.',
        'approve_vendor_heading' => 'اعتماد هذا المورد؟',
        'approve_vendor_description' => 'سيمنح هذا الإجراء المورد حالة الاعتماد. يمكنك منح صلاحيات لأنواع المنتجات بعد ذلك.',
    ],

    // Page titles
    'pages' => [
        'review_vendor_application' => 'مراجعة طلب المورد',
    ],

    // Misc display labels
    'misc' => [
        'piastres' => 'قرش',
        'address_default_marker' => '[افتراضي]',
        'booking_id' => 'رقم الحجز',
        'id_short' => 'المعرّف',
        'translations_tab' => 'الترجمات',
        'tab_english' => 'الإنجليزية',
        'tab_arabic' => 'العربية',
        'bookings_count' => '{0} لا يوجد حجوزات|{1} حجز واحد|[2,*] :count حجوزات',
        'reviews_count' => '{0} لا يوجد تقييمات|{1} تقييم واحد|[2,*] :count تقييمات',
        'yes' => 'نعم',
        'no' => 'لا',
    ],

    // Table column labels
    'columns' => [
        'public_id' => 'المعرّف العام',
        'vendor_reference' => 'مرجع مقدم الخدمة',
        'document_reference' => 'مرجع المستند',
        'reviewed_by' => 'راجعه',
        'reviewed_at' => 'تاريخ المراجعة',
        'review_notes' => 'ملاحظات المراجعة',
        'id' => 'المعرّف',
        'name' => 'الاسم',
        'user_id' => 'المستخدم',
        'business_name' => 'اسم النشاط التجاري',
        'business_type' => 'نوع النشاط',
        'email' => 'البريد الإلكتروني',
        'phone' => 'رقم الهاتف',
        'phone_verified' => 'تم التحقق من الهاتف',
        'docs' => 'المستندات',
        'vendor' => 'مقدم الخدمة',
        'doc_type' => 'نوع المستند',
        'file_name' => 'اسم الملف',
        'status' => 'الحالة',
        'approval_status' => 'حالة الموافقة',
        'created_at' => 'تاريخ الإنشاء',
        'download' => 'تنزيل',
        'slug' => 'المعرّف النصي',
        'governorate' => 'المحافظة',
        'city' => 'المدينة',
        'city_id' => 'المدينة',
        'label' => 'التسمية',
        'recipient_name' => 'اسم المستلم',
        'recipient_phone' => 'هاتف المستلم',
        'is_default' => 'العنوان الافتراضي',
        'bank_name' => 'اسم البنك',
        'bank_account_holder' => 'صاحب الحساب',
        'bank_iban' => 'رقم IBAN',
        'bank_swift_bic' => 'رمز SWIFT / BIC',
        'bank_branch' => 'فرع البنك',
        'active_type_approvals' => 'الموافقات الفعّالة على أنواع المنتجات',
        'date_of_birth' => 'تاريخ الميلاد',
        'gender' => 'النوع',
        'accepts_marketing' => 'يقبل الرسائل التسويقية',
        'platform' => 'المنصة',
        'device_id' => 'معرّف الجهاز',
        'last_seen_at' => 'آخر ظهور',
        'delivery_fee' => 'رسوم التوصيل',
        'min_order' => 'الحد الأدنى للطلب',
        'day_of_week' => 'يوم الأسبوع',
        'opens_at' => 'وقت الفتح',
        'closes_at' => 'وقت الإغلاق',
        'product_type' => 'نوع المنتج',
        'approved_at' => 'تاريخ الموافقة',
        'revoked_at' => 'تاريخ السحب',
        'role' => 'الدور',
        'reference' => 'المرجع',
        'lifecycle_status' => 'مرحلة الحجز',
        'payment_status' => 'الدفع',
        'fulfillment_status' => 'التنفيذ',
        'total' => 'الإجمالي',
        'kind' => 'النوع',
        'target' => 'الهدف',
        'rating' => 'التقييم',
        'body' => 'التعليق',
        'program' => 'البرنامج',
        'points' => 'النقاط',
        'address_line' => 'العنوان',
        'description' => 'الوصف',
        'actor' => 'المُنفِّذ',
    ],

    'gender' => [
        'male' => 'ذكر',
        'female' => 'أنثى',
        'prefer_not_to_say' => 'أفضل عدم الإفصاح',
    ],

    // Filament action labels
    'actions' => [
        'open_document' => 'فتح المستند',
        'review' => 'مراجعة',
        'approve' => 'موافقة',
        'reject' => 'رفض',
        'suspend' => 'إيقاف',
        'unsuspend' => 'إلغاء الإيقاف',
        'approve_for_type' => 'اعتماد نوع منتج',
        'revoke_type' => 'سحب اعتماد النوع',
        'download' => 'عرض',
        'suspend_customer' => 'تعليق العميل',
        'unsuspend_customer' => 'إلغاء تعليق العميل',
        'force_logout' => 'إنهاء جميع الجلسات',
        'edit_profile' => 'تعديل الملف الشخصي',
        'impersonate' => 'انتحال صفة المورد (رمز API)',
        'login_as_vendor' => 'الدخول كمورد (ويب)',
        'replace_coverage' => 'استبدال مناطق التغطية',
        're_upload_document' => 'إعادة رفع المستند',
        'request_changes' => 'طلب تعديلات',
        'approve_for_rental' => 'اعتماد للإيجار',
        'approve_for_sale' => 'اعتماد للبيع',
        'approve_for_digital' => 'اعتماد للرقمي',
        'revoke_type_short' => 'سحب الاعتماد',
        'suspend_vendor' => 'إيقاف المورد',
        'edit' => 'تعديل',
        'remove' => 'حذف',
        'delete' => 'حذف',
        'upload' => 'رفع',
        'approve_profile' => 'اعتماد الملف',
    ],

    // Form field labels
    'forms' => [
        'rejection_reason' => 'سبب الرفض',
        'rejection_reason_en' => 'سبب الرفض بالإنجليزية',
        'rejection_reason_ar' => 'سبب الرفض بالعربية',
        'revoke_reason_en' => 'سبب السحب بالإنجليزية',
        'revoke_reason_ar' => 'سبب السحب بالعربية',
        'revoke_reason_en_short' => 'سبب السحب (إنجليزي)',
        'business_name_en' => 'اسم النشاط التجاري بالإنجليزية',
        'business_name_ar' => 'اسم النشاط التجاري بالعربية',
        'bio_en' => 'النبذة بالإنجليزية',
        'bio_ar' => 'النبذة بالعربية',
    ],

    // Notification titles
    'notifications' => [
        'customer_suspended' => 'تم تعليق العميل',
        'customer_unsuspended' => 'تم إلغاء تعليق العميل',
        'customer_logged_out' => 'تم إنهاء جلسات العميل',
        'profile_updated_by_admin' => 'تم تحديث ملف العميل',
        'vendor_approved' => 'تمت الموافقة على الملف التجاري',
        'vendor_rejected' => 'تم رفض الملف التجاري',
        'vendor_suspended' => 'تم إيقاف المورد',
        'vendor_unsuspended' => 'تم إعادة تفعيل المورد',
        'type_approved' => 'تم اعتماد المورد لنوع المنتج: :type',
        'type_approved_for' => 'تم اعتماد المورد لـ: :type',
        'type_revoked' => 'تم سحب اعتماد نوع المنتج',
        'signed_url_generated' => 'تم إنشاء رابط موقّع',
        'impersonation_token' => 'رمز انتحال الصفة (ينتهي خلال 30 دقيقة)',
        'coverage_updated' => 'تم تحديث مناطق التغطية بنجاح',
        'document_uploaded' => 'تم استبدال المستند بنجاح',
        'document_deleted' => 'تم حذف المستند',
        'document_upload_success' => 'تم رفع المستند بنجاح',
        'vendor_profile_updated' => 'تم تحديث ملف مقدم الخدمة',
        'change_request_created' => 'تم إرسال طلب التعديل',
        'expiry_set_successfully' => 'تم تحديث تاريخ انتهاء المستند',
        'approval_request_submitted' => 'تم إرسال طلب الاعتماد. سيقوم أحد المشرفين بمراجعته قريباً.',
        'coverage_removed' => 'تم حذف منطقة التغطية',
        'coverage_saved' => 'تم حفظ منطقة التغطية',
        'document_approved' => 'تمت الموافقة على المستند',
        'document_rejected' => 'تم رفض المستند',
    ],

    // Confirmation dialog content
    'confirmations' => [
        'impersonate_warning' => 'أنت على وشك انتحال صفة هذا المورد. سيتم تسجيل هذا الإجراء في سجل التدقيق. ينتهي الرمز خلال 30 دقيقة.',
    ],

    // Placeholder strings
    'placeholders' => [
        'not_verified' => '— غير مُتحقّق منه —',
        'none' => '— لا يوجد —',
        'dash' => '—',
        'no_documents' => 'لم يتم رفع أي مستندات',
        's3_not_configured' => '— لم يتم إعداد S3 —',
        'open_document' => 'فتح المستند',
    ],

    // Field labels
    'fields' => [
        'logo' => 'الشعار',
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'phone' => 'رقم الهاتف',
        'password' => 'كلمة المرور',
        'password_confirmation' => 'تأكيد كلمة المرور',
        'business_name' => 'اسم النشاط التجاري',
        'business_type' => 'نوع النشاط',
        'bio' => 'نبذة تعريفية',
        'slug' => 'المعرّف النصي',
        'doc_type' => 'نوع المستند',
        'file' => 'الملف',
        'document_file' => 'ملف المستند',
        'city_id' => 'المدينة',
        'governorate_id' => 'المحافظة',
        'delivery_fee' => 'رسوم التوصيل',
        'min_order' => 'الحد الأدنى للطلب',
        'product_type' => 'نوع المنتج',
        'bank_iban' => 'رقم IBAN',
        'bank_name' => 'اسم البنك',
        'bank_account_holder' => 'صاحب الحساب',
        'bank_swift_bic' => 'رمز SWIFT / BIC',
        'bank_branch' => 'فرع البنك',
        'opens_at' => 'وقت الفتح',
        'closes_at' => 'وقت الإغلاق',
        'day_of_week' => 'يوم الأسبوع',
        'label' => 'التسمية',
        'address_line' => 'العنوان',
        'recipient_name' => 'اسم المستلم',
        'recipient_phone' => 'هاتف المستلم',
        'is_default' => 'تعيين كافتراضي',
        'field_path' => 'الحقل',
        'requested_change_en' => 'التعديل المطلوب (إنجليزي)',
        'requested_change_ar' => 'التعديل المطلوب (عربي)',
        'expires_at' => 'تاريخ الانتهاء',
        'is_critical' => 'مستند حرج',
        'commercial_register_no' => 'رقم السجل التجاري',
        'tax_id' => 'الرقم الضريبي',
        'national_id' => 'رقم الهوية الوطنية',
        'locale' => 'اللغة المفضلة',
        'last_login_at' => 'آخر تسجيل دخول',
        'joined_at' => 'تاريخ الانضمام',
        'uploaded_at' => 'تاريخ الرفع',
    ],

    // Role labels (Spatie roles)
    'roles' => [
        'customer' => 'عميل',
        'vendor' => 'مزود خدمة',
        'admin' => 'مشرف',
        'super_admin' => 'مشرف عام',
        'booking_manager' => 'مدير الحجوزات',
    ],

    // Approval status labels
    'status' => [
        'pending' => 'قيد المراجعة',
        'approved' => 'معتمد',
        'rejected' => 'مرفوض',
        'suspended' => 'موقوف',
        'changes_requested' => 'تعديلات مطلوبة',
    ],

    // Document status labels
    'document_status' => [
        'pending' => 'قيد المراجعة',
        'approved' => 'موافق عليه',
        'rejected' => 'مرفوض',
    ],

    // Document type labels
    'document_type' => [
        'cr' => 'السجل التجاري',
        'tax_card' => 'البطاقة الضريبية',
        'national_id' => 'بطاقة الهوية الوطنية',
        'iban_proof' => 'إثبات رقم IBAN',
        'other' => 'أخرى',
    ],

    // Business type labels
    'business_type' => [
        'individual' => 'فرد',
        'company' => 'شركة',
        'establishment' => 'منشأة',
    ],

    // Product type labels
    'product_type' => [
        'rental' => 'تأجير',
        'sale' => 'بيع',
        'digital' => 'رقمي',
    ],

    // Day of week labels
    'day_of_week' => [
        0 => 'الأحد',
        1 => 'الاثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ],

    'invalid_current_password' => 'كلمة المرور الحالية غير صحيحة.',

    // Self-service account deletion guards (live audit 2026-06-06 §12.2)
    'account_deletion' => [
        'open_bookings' => 'لا يمكنك حذف حسابك أثناء وجود طلبات مفتوحة. أكمِلها أو ألغِها أولاً.',
        'wallet_balance' => 'لا يمكنك حذف حسابك بينما توجد أموال في محفظتك. اسحب رصيدك أولاً.',
        'open_withdrawals' => 'لا يمكنك حذف حسابك أثناء وجود طلب سحب قيد المعالجة.',
    ],

    // Customer management keys
    'customer_already_suspended' => 'هذا الحساب معلّق بالفعل.',
    'customer_not_suspended' => 'هذا الحساب غير معلّق حالياً.',
    'admin_cannot_self_suspend' => 'لا يمكنك تعليق حسابك الخاص.',

    // Customer tabs
    'tabs' => [
        'overview' => 'نظرة عامة',
        'bookings' => 'الحجوزات',
        'reviews' => 'التقييمات',
        'wallet' => 'المحفظة',
        'addresses' => 'العناوين',
        'activity' => 'سجل النشاط',
    ],

    // Generic error keys (used in Actions and API responses)
    'invalid_credentials' => 'بيانات الاعتماد المدخلة غير صحيحة.',
    'user_not_found' => 'لم يتم العثور على حساب بهذا الرقم.',
    'invalid_otp' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.',

    'registration' => [
        'phone_hint' => 'أدخل كود الدولة، مثال: +201001234567.',
    ],

    'approval_eligibility' => [
        'checklist' => 'متطلبات الاعتماد',
        'checklist_description' => 'يجب استكمال جميع المتطلبات قبل اعتماد المورد.',
        'complete' => 'مكتمل',
        'incomplete' => 'غير مكتمل',
        'complete_requirements' => 'استكمل جميع متطلبات الاعتماد أولاً.',
        'pending_only' => 'لا يمكن اعتماد إلا المورد قيد المراجعة.',
        'identity' => 'بيانات هوية المورد غير مكتملة.',
        'contact' => 'يجب إدخال البريد الإلكتروني ورقم الهاتف والتحقق منهما.',
        'profile' => 'بيانات النشاط والهوية المطلوبة باللغتين غير مكتملة.',
        'geography' => 'المدينة المحددة لا تتبع المحافظة المحددة.',
        'banking' => 'البيانات البنكية المطلوبة غير مكتملة.',
        'hours' => 'يجب إضافة موعد عمل واحد على الأقل.',
        'coverage' => 'يجب إضافة منطقة تغطية واحدة على الأقل.',
        'documents' => 'يجب أن تكون أحدث المستندات المطلوبة معتمدة وسارية.',
    ],

    // Validation messages
    'validation' => [
        'platform_required' => 'نظام تشغيل الجهاز مطلوب.',
        'platform_invalid' => 'يجب أن يكون نظام تشغيل الجهاز ios أو android أو web.',
        'fcm_token_required' => 'رمز الجهاز (FCM) مطلوب.',
        'phone_e164' => 'يجب أن يكون رقم الهاتف بصيغة E.164، مثال: +201001234567.',
        'otp_invalid' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.',
        'otp_throttled' => 'تم تجاوز عدد محاولات التحقق المسموح. يرجى المحاولة لاحقاً.',
        'duplicate_phone' => 'رقم الهاتف هذا مسجل مسبقاً.',
        'duplicate_email' => 'البريد الإلكتروني هذا مسجل مسبقاً.',
        'business_name_en_required' => 'اسم النشاط التجاري باللغة الإنجليزية مطلوب.',
        'business_name_ar_required' => 'اسم النشاط التجاري باللغة العربية مطلوب.',
        'city_not_found' => 'المدينة المحددة غير موجودة.',
        'doc_type_invalid' => 'نوع المستند غير صالح.',
        'file_too_large' => 'يجب ألا يتجاوز حجم الملف 10 ميغابايت.',
        'file_mime_invalid' => 'يُقبل فقط ملفات PDF وJPEG وPNG.',
        'vendor_not_approved' => 'يجب الموافقة على الملف التجاري أولاً قبل منح صلاحيات نوع المنتج.',
        'type_approval_not_found' => 'اعتماد نوع المنتج غير موجود أو تم سحبه مسبقاً.',
        'document_already_reviewed' => 'تمت مراجعة هذا المستند مسبقاً.',
        'document_not_reuploadable' => 'لا يمكن إعادة رفع إلا مستند مرفوض.',
        'document_already_pending' => 'يوجد بالفعل مستند بديل قيد المراجعة.',
        'invalid_change_request_items' => 'عنصر واحد أو أكثر لا ينتمي إلى طلب التعديل الحالي.',
        'unresolved_change_request_items' => 'يجب معالجة جميع التعديلات المطلوبة قبل إعادة الإرسال.',
        'idempotency_key_required' => 'ترويسة Idempotency-Key مطلوبة.',
        'idempotency_key_conflict' => 'تم استخدام مفتاح Idempotency-Key هذا لطلب مختلف.',
        'change_request_items_required' => 'يجب إضافة تعديل مطلوب واحد على الأقل.',
        'open_change_request_exists' => 'يوجد طلب تعديل غير محسوم لمقدم الخدمة.',
        'change_request_cycle_limit' => 'تم بلوغ الحد الأقصى لدورات طلب التعديل.',
        'revoke_reason_required' => 'يجب إدخال سبب لإلغاء صلاحية نوع المنتج.',
        'rejection_reason_required' => 'سبب الرفض مطلوب.',
        'city_governorate_mismatch' => 'المدينة المحددة لا تتبع المحافظة المحددة.',
        'password_confirmation' => 'تأكيد كلمة المرور غير مطابق.',
        'vendor_not_suspended' => 'المورد غير موقوف.',
        'changes_not_requested' => 'ملف المورد لا ينتظر تعديلات مطلوبة.',
    ],

    // Success messages
    'messages' => [
        'registered' => 'تم التسجيل بنجاح. يرجى التحقق من رقم هاتفك.',
        'phone_verified' => 'تم التحقق من رقم الهاتف بنجاح.',
        'logged_in' => 'تم تسجيل الدخول بنجاح.',
        'logged_out' => 'تم تسجيل الخروج بنجاح.',
        'profile_updated' => 'تم تحديث الملف الشخصي بنجاح.',
        'document_uploaded' => 'تم رفع المستند بنجاح.',
        'coverage_area_added' => 'تمت إضافة منطقة التغطية بنجاح.',
        'business_hours_updated' => 'تم تحديث ساعات العمل بنجاح.',
        'address_added' => 'تمت إضافة العنوان بنجاح.',
        'address_deleted' => 'تم حذف العنوان.',
        'vendor_approved' => 'تمت الموافقة على الملف التجاري.',
        'vendor_rejected' => 'تم رفض الملف التجاري.',
        'vendor_suspended' => 'تم إيقاف المورد.',
        'type_approved' => 'تم اعتماد المورد لنوع المنتج: :type.',
        'type_revoked' => 'تم سحب اعتماد نوع المنتج.',
    ],
    'days' => [
        'sunday' => 'الأحد',
        'monday' => 'الإثنين',
        'tuesday' => 'الثلاثاء',
        'wednesday' => 'الأربعاء',
        'thursday' => 'الخميس',
        'friday' => 'الجمعة',
        'saturday' => 'السبت',
    ],
    'account_suspended' => 'تم إيقاف حسابك. يرجى التواصل مع الدعم.',
    'account_suspended_title' => 'الحساب موقوف',
    'account_suspended_body' => 'تم إيقاف حساب المورد الخاص بك. يرجى التواصل مع دعم إنستا باتي لحل هذه المشكلة.',
    'vendor_portal' => [
        'impersonation_banner' => 'فريق دعم إنستا باتي يتصرف الآن نيابةً عن حسابك. بدأ في: :time.',
        'impersonation_end' => 'إنهاء الجلسة',
    ],

    'errors' => [
        'document_not_owned' => 'هذه الوثيقة لا تنتمي إلى ملفك الشخصي.',
        'document_not_deletable' => 'يمكن حذف الوثائق المرفوضة فقط.',
        'profile_not_approved' => 'يجب أن يكون ملفك الشخصي معتمداً قبل طلب اعتماد نوع المنتج.',
        'type_already_approved' => 'أنت معتمد بالفعل لهذا النوع من المنتجات.',
        'vendor_suspended' => 'حسابك موقوف. يمكنك عرض بياناتك ولكن لا يمكنك إجراء تغييرات.',
        'vendor_approval_required' => 'يجب اعتماد حساب المورد قبل استخدام هذه الميزة.',
        'email_verification_required' => 'يرجى التحقق من بريدك الإلكتروني قبل استخدام عمليات المورد.',
    ],
    'expiry_date_must_be_future' => 'يجب أن يكون تاريخ الانتهاء اليوم أو بعده.',
];
