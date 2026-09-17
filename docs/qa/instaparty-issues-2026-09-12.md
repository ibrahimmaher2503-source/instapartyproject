تقرير مشكلات InstaParty المكتشفة حتى الآن

<div dir="rtl">

تاريخ إعداد التقرير: 12 سبتمبر 2026
مصدر التقرير: آخر نسخة من قائمة الفحص التفاعلية والاختبارات الحية المسجلة حتى 6 سبتمبر 2026.
إجمالي بنود الـChecklist: 381 بندًا.

هذا التقرير يفصل بين العطل المؤكد، ومشكلة التصميم، وعائق الاختبار، والتحقق غير المكتمل. إجمالي سجلات المتابعة 73، لكنه لا يساوي بالضرورة 73 سببًا برمجيًا مستقلًا؛ بعض السجلات تغطي العيب نفسه من زاوية PRD وزاوية QA.

الملخص التنفيذي

التصنيف

العدد

المعنى

يحتاج إصلاح

48

سلوك مخالف أو خطأ وظيفي مثبت

تحسين تصميم

17

الصفحة تعمل لكن UI/UX أو التعريب غير مقبول

محجوب

3

لا يمكن إكمال الاختبار دون بيئة/بيانات مناسبة

قيد التحقق

5

بدأ الفحص ولم تتوافر أدلة كافية للحكم النهائي

إجمالي سجلات المتابعة

73

قبل دمج الأسباب المتكررة

أولويات الإصلاح المقترحة

سلامة الصلاحيات ومسارات الإدارة والعميل والمورد.

أعطال 500 التي تمنع عرض التفاصيل أو تنفيذ دورة العمل.

دورة اعتماد المورد والخدمات ومنع تجاوز شروط الأهلية.

دورة الحجز والتعديلات والمدفوعات والاسترداد والتسوية.

تلف البيانات العربية والتعريب واتجاه RTL.

تحسينات الواجهة البصرية والاستجابة والموبايل.

مشكلات مؤكدة تحتاج إصلاح (48)

PRD — 7.1 Customer Discovery and Entry

FR-2 — The system must collect event context data early — location, date, overall event time range, and other relevant filters

الحالة: يحتاج إصلاح

التصنيف الأصلي: PRD الملزم

المشكلة/نتيجة الفحص: المعالج يعرض المحافظة والمدينة وتاريخ البداية والنهاية، وربط القاهرة بمدنها يعمل؛ لكنه يسمح بإكمال المعالج والانتقال إلى نتائج البحث مع event_starts_at وevent_ends_at فارغين. هذا يخالف شرط جمع نطاق وقت المناسبة مبكرًا. كما يظل نص «جاري تحميل المناطق…» ظاهرًا بعد اكتمال التحميل.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-06

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Fix the Laravel Blade event planner in /home/instaparty/public_html. Make occasion, valid governorate/city, event start, and event end required before discovery; validate end after start and reject past or invalid ranges server-side as well as in the UI. Remove the stale loading message after cities load, localize date/time controls for Arabic RTL and English LTR, preserve selected values across validation, and add focused feature/browser tests. Do not change APIs in this workstream.

</details>

FR-4 — The system must also present package-like recommendations based on event context without requiring a full architectural replacement of the current flow

الحالة: يحتاج إصلاح

التصنيف الأصلي: PRD الملزم

المشكلة/نتيجة الفحص: الصفحة الرئيسية تعرض باقات عامة، لكن معالج المناسبة بعد اختيار المناسبة والمدينة ينقل إلى قائمة خدمات فقط ولا يعرض توصيات باقات مرتبطة بسياق المناسبة. في الاختبار ظهرت 0 خدمة للسياق المختار ولم يظهر بديل باقة أو تفسير مفيد.

الدليل: دليل 1 · دليل 2 · دليل 3

تاريخ آخر تحقق: 2026-09-06

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Implement context-aware package recommendations in the Laravel Blade storefront without replacing the current discovery flow. Use occasion, city, date/time, guest count, availability, coverage, and published-service eligibility; explain empty recommendation states and provide safe broadening suggestions. Reuse existing package recommendation data and public identifiers, preserve Arabic/English parity, and add feature/browser tests for matched, partially matched, and empty contexts.

</details>

4. تسجيل الموردين وإدارتهم

VEN-004 — قائمة انتظار الموردين وترتيب الأولويات وحالة كل طلب.

الحالة: يحتاج إصلاح

التصنيف الأصلي: QA والتشغيل

المشكلة/نتيجة الفحص: القائمة تعرض المراجع العامة والحالة والتاريخ، وتحسن عرض changes_requested داخل قائمة الانتظار. لكن البحث بالمرجع العام لم يرشح الجدول أو يُظهر مورد QA، والقائمة تبدأ بسجلات مايو/يونيو القديمة دون إبراز SLA أو أولوية واضحة؛ لذلك محور قائمة الانتظار لا يجتاز وظيفيًا.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-06

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Fix the Filament vendor approval queue query and search in /home/instaparty/public_html. Search must match vendor public ULID, Arabic/English business name, normalized email and phone without losing authorization scope or duplicating rows. Keep pending and changes_requested visible with state-appropriate actions, sort by explicit SLA/oldest actionable priority, and highlight overdue records. Add focused Livewire/Filament tests and verify the QA public id 01M1TAPCGAHFM0N9N5KVBCE5NC appears when searched.

</details>

VEN-005 — قبول أو رفض المورد مع سبب وإشعار مناسب.

الحالة: يحتاج إصلاح

التصنيف الأصلي: QA والتشغيل

المشكلة/نتيجة الفحص: مورد QA غير موثق البريد ولا يملك مستندات أو ساعات عمل أو تغطية، ومع ذلك يظهر له زر موافقة. الضغط فتح نافذة «اعتماد هذا المورد؟» وبها إلغاء/تأكيد فقط، دون Checklist أهلية أو عرض العناصر الناقصة. أُغلقت النافذة دون تنفيذ القرار.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-06

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Enforce vendor approval eligibility in one authorized transactional Laravel Action. For incomplete vendors, hide or disable Approve and show a localized checklist covering verified email/phone, valid governorate-city pair, required approved non-expired documents, business hours, coverage, and required business identity fields. Revalidate server-side under a row lock, reject stale/concurrent decisions, require and audit rejection/change-request reasons, and never rely on the Filament modal alone. Verify with the isolated QA vendor 01M1TAPCGAHFM0N9N5KVBCE5NC without approving it until it becomes eligible.

</details>

6. واجهة العميل والصفحة الرئيسية

WEB-015 — اختبار كامل بالعربية RTL والإنجليزية LTR.

الحالة: يحتاج إصلاح

التصنيف الأصلي: QA والتشغيل

المشكلة/نتيجة الفحص: فحص RTL كشف خلط اتجاه ولغة في عدة أسطح: mm/dd/yyyy و10x10 وmin 45 وFooter primary/secondary، مع انعكاس/تفكك عرض السعر وبعض القيم. العربية ليست متسقة بصريًا بعد.

الدليل: دليل 1 · دليل 2 · دليل 3 · دليل 4 · دليل 5

تاريخ آخر تحقق: 2026-09-06

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Perform a focused Arabic RTL visual repair for the Laravel Blade storefront in /home/instaparty/public_html. Fix hero readability and overlap, media fallbacks, service/vendor card imagery, bidirectional isolation for prices, dimensions and durations, localized date/time presentation, raw footer translation keys, empty-state spacing, and responsive hierarchy across home, wizard, search, vendor list, service detail, and cart. Preserve English LTR. Add screenshot-based desktop/mobile regression coverage and do not modify APIs or business logic.

</details>

المشكلات المكتشفة — الحجوزات والتفاوض

BUG-OPS-001 — عدم تطابق ملخص لوحة التحكم مع صفحة متابعة التفاوض.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: تأكد السبب من قراءة الكود: كروت «تأخر رد المورد» و«المفاوضات المفتوحة» تعد صفوف BookingVendor، بينما جدول المتابعة يعرض Bookings. الكروت قد تضاعف الحجز متعدد الموردين وتضم حصصًا تابعة لحجز ملغي، وروابطها تفتح الجدول بلا فلتر المؤشر. وقت القياس كان كل كارت 6 حصص تخص 5 حجوزات، والجدول 4 حجوزات. الرقم 4 خاص بهذه البيانات وليس قاعدة دائمة.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Use AGENTS.md and treat the following contract as proposed until the product owner approves it, because docs/specs/01_PRD.md is missing. Implement a shared GetNegotiationMonitorQueryAction returning a Booking builder and reuse it in LateVendorResponsesWidget, AdminDashboardHeaderWidget, AdminDashboardSectionsWidget, and BookingsMonitorResource. Count distinct bookings, never BookingVendor rows. Late = booking lifecycle vendor_review or customer_review AND at least one vendor allocation pending with non-null response deadline strictly before one captured UTC asOf. Open = same booking lifecycle AND at least one vendor allocation pending or modified, without a deadline condition. Exclude timed_out and all other vendor statuses; exclude every booking lifecycle outside the two review statuses. Add negotiation_scope=late|open table filters and make each card URL reproduce its exact query. Evaluate count and linked result at the same UTC instant; do not require late and open totals to match and do not hardcode the observed 4. Keep authorization at the Filament/policy boundary, public ULIDs, bilingual labels, and no tenancy. Add isolated Pest tests for multi-vendor distinct counting, future/overdue pending, modified, timed_out, accepted/rejected, cancelled/completed, null/equal/earlier deadlines, UTC equivalence, cache freshness, card-to-filter equality, localization, and authorization. Do not mutate production or install dependencies there. Report product assumptions, files, before/after fixture counts, and focused test output.

</details>

BUG-OPS-PERMISSION-001 — اسم صلاحية كروت المتابعة لا يطابق صلاحية Policy الخاصة بمتابعة الحجوزات.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: الكروت تتحقق من view_any_bookings_monitor، بينما Booking policy تتطلب view_any_bookings::monitor. عدم التطابق قد يُخفي المؤشر عن مستخدم مصرح له أو يظهر رابطًا لا يستطيع فتحه، ويمنع إثبات اتساق الصلاحية بين الكارت ووجهته.

الدليل: قراءة الكود: LateVendorResponsesWidget / AdminDashboardHeaderWidget / AdminDashboardSectionsWidget / Booking policy / BookingsMonitorResource

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Treat this as an authorization consistency defect. Inventory every permission string used by the negotiation widgets, BookingsMonitorResource, navigation visibility, Booking policy, Shield seeders, roles, tests, and translations. Establish one canonical permission, preferably the policy-backed view_any_bookings::monitor unless the approved permission registry says otherwise. Replace aliases through a documented backward-compatible migration/role-sync plan; do not silently revoke or grant production permissions. Ensure the card, navigation item, destination page, direct URL, and query all use the same policy decision. Add tests for an authorized monitor-only role, unauthorized role, Super Admin, stale legacy permission, direct URL access, hidden widgets/navigation, and no cross-role leakage. Produce a read-only affected-role inventory and reversible rollout plan before any production role update. Report canonical name, occurrences, compatibility steps, changed files, and tests.

</details>

BUG-OPS-002 — صفوف متابعة التفاوض لا توفر فتح تفاصيل الحجز أو التفاوض.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: رقم المرجع والصف غير قابلين للفتح، ولا يوجد إجراء «عرض التفاصيل». الإجراء الوحيد الظاهر هو «إلغاء إجباري»، وهو إجراء مدمر. هذا يمنع الأدمن من الفهم والمتابعة قبل اتخاذ قرار.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Use the project instructions and current PRD. Improve the Filament booking monitor without changing booking state rules. Add a read-only View/Details action for every row and make the booking reference open the same detail view. The detail surface must show the booking summary, customer, vendor portions, line items, negotiation history, vendor response deadlines, payment state, and state-transition timeline. Keep Force Cancel visually secondary and separated from read-only actions; retain its existing authorization and confirmation. Do not add migrations unless an already-approved spec proves they are required. Add authorization and feature tests showing authorized admins can view details, unauthorized roles cannot, and opening details performs no writes. Report exact files changed and focused test results.

</details>

المشكلات المكتشفة — قائمة الحجوزات

BUG-BKG-001 — صفحة تفاصيل الحجوزات تعيد خطأ 500 لجميع السجلات المختبرة.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: فتح IP-2026-000024 أعاد صفحة 500، وتكرر الخطأ بعد reload. ثم فتح BK-DEV-1001، وهو حجز مكتمل ومسترد جزئيًا، وأعاد 500 أيضًا. هذا يرجح عطلًا عامًا في ViewBooking/BookingResource وليس بيانات سجل واحد، ويمنع الأدمن من مراجعة التفاصيل والحالة والدفع.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Use AGENTS.md, the active PRD, and the current task specification. Diagnose the universal HTTP 500 on Filament admin booking detail routes before editing. Reproduce in a prepared development/staging copy using at least these state shapes: vendor_review + unpaid, and completed + partially_refunded. Inspect the application exception/log and trace the BookingResource/ViewBooking page, infolists, relation managers, enum/state serialization, money formatting, and nullable relationships. Do not expose .env values or production customer data, do not change live data, and do not hide the exception with a broad try/catch. Fix the root cause with null-safe and type-safe presentation while preserving Brick Money minor units, public ULIDs, UTC, thin Filament pages, and module contracts. Add Pest feature tests for authorized admin access and successful 200 rendering across booking/payment/fulfilment combinations and rental/sale/digital types; include a 404 test for an unknown public ULID and authorization tests for non-admin roles. Run focused tests plus the relevant static analysis, then report root cause, exact changed files, test commands/results, and any remaining unsupported state.

</details>

BUG-BKG-002 — رابط العودة من خطأ صفحة الأدمن يوجّه إلى بوابة المورد.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: التحويل الخاطئ بين اللوحتين ما زال قائمًا بصورة أوسع من رابط العودة: من جلسة مورد غير متحقق، فتح /admin/login انتهى فعليًا إلى /vendor-portal/email-verification/prompt. هذا يثبت أن عزل url.intended/guards بين admin وvendor غير منشور أو غير كافٍ على النسخة الحية.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-06

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Work directly in /home/instaparty/public_html. Fix and deploy Filament admin/vendor session isolation for the Laravel web UI only. Explicitly bind the admin panel to the web guard and the vendor panel to the vendor guard; validate and clear cross-panel url.intended values; ensure an authenticated or unverified vendor can open /admin/login without being redirected into vendor-portal, and vice versa. Verify clean, admin, vendor, and unverified-vendor sessions in Arabic and English. Rebuild only required Laravel caches and report focused live evidence. Do not change production users or expose credentials.

</details>

المشكلات المكتشفة — تعديلات الحجوزات

BUG-MOD-001 — تفاصيل تعديل الحجز تعيد خطأ 500 للحالات المعلقة والمقبولة.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: فتح التعديل Pending رقم 30VPSN48B12C47WN80E9QWN3SM أعاد 500. فتح تعديل Customer Accepted رقم 01KTD359F6HK9NJR2MPV3P6QKQ أعاد 500 أيضًا. العطل عام ويمنع مقارنة النسخة الأصلية بالتعديل أو مراجعة السجل قبل القرار.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Use AGENTS.md, the active PRD, and the latest task spec. Diagnose the universal HTTP 500 on Filament admin booking-modification detail routes before editing. Reproduce with both Pending and Customer Accepted records in a prepared development/staging copy. Inspect the real exception and trace BookingModificationResource/ViewBookingModification, infolists, state enums, proposal payload serialization, money values, timestamps, proposer/vendor/customer relations, and nullable fields. Do not expose credentials or production PII, modify live records, or suppress the error with a broad catch. Fix the root cause while preserving public ULIDs, Brick Money minor units, UTC, bilingual responses, thin Filament pages, and module contracts. The read-only detail page must render the original values beside every proposed value, visually identify additions/changes/removals, show proposer, reason/notes, expiry, decision timeline, and audit metadata. Add Pest feature tests for Pending, Customer Accepted, Customer Rejected, Expired, and malformed/legacy payload shapes plus authorization/404 tests. Run focused tests and static analysis; report root cause, exact files, commands/results, and remaining legacy-data risks.

</details>

BUG-MOD-002 — الأدمن يستطيع قبول أو رفض تعديل المورد نيابة عن العميل بالمخالفة للـ PRD.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: التعديل Pending يعرض Accept (Admin Proxy) وReject (Admin Proxy). هذا يتعارض مع FR-14 التي تشترط موافقة العميل الصريحة على تعديلات المورد، ومع FR-16–18 التي تجعل دور الأدمن متابعة وتسهيل فقط. لم يتم الضغط على الإجراءين.

الدليل: دليل 1 · PRD FR-14, FR-16, FR-17, FR-18

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Treat PRD FR-14 and FR-16 through FR-18 as binding. Audit every admin, API, Action, policy, command, and queued path that can accept or reject a vendor booking modification on behalf of a customer. Do not merely hide the Filament buttons: remove or deny the underlying admin-proxy mutation capability unless a newer explicitly approved specification overrides the PRD. Keep admin read-only monitoring, reminders, escalation notes, and communication/facilitation. The customer must remain the only actor who can accept or reject vendor-originated modifications, with explicit re-review of highlighted changes. Preserve immutable audit history for any legacy proxy decisions. Add authorization tests proving super_admin and ordinary admin receive denial on customer decision endpoints/actions, while the correct owning customer can decide once and duplicate decisions are idempotently rejected. Cover late/expired proposals and all three product types. Update API docs, registry, and Bruno examples if endpoints change. Report conflicts found, changed files, migration impact if any, and test results; do not deploy or mutate production data.

</details>

BUG-MOD-003 — تعديل منتهي ما زال Pending وتاريخ انتهائه يسبق تاريخ إنشائه.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: سجل BK-DEV-1002 حالته Pending، تاريخ الانتهاء 19 مايو 2026 08:30، وتاريخ الإنشاء 23 مايو 2026 17:41:58؛ أي أن الانتهاء يسبق الإنشاء بأكثر من أربعة أيام، وما زال السجل معروضًا كمعلق في سبتمبر 2026. هذا يكشف ضعف validation أو seed/legacy data وعدم تطبيق انتهاء المقترحات.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Diagnose booking-modification expiry integrity before changing data. Trace how expires_at is calculated, validated, persisted, seeded/imported, queried, displayed, and transitioned to Expired. Identify why a Pending record can have expires_at earlier than created_at and remain actionable months later. Add application validation and a database constraint where compatible so expires_at must be null only for allowed terminal states or strictly later than created_at for active proposals. Ensure Accept/Reject/Customer decision Actions reject expired proposals using server UTC time under a row lock and idempotent state transition. Add the documented scheduler/queue job or query-time expiry mechanism, with tests for boundary time, timezone display, concurrency, and stale legacy rows. Provide a read-only inventory/report of affected production rows and a reversible remediation plan; do not mutate production rows or run migrations without explicit approval. Report root cause, affected count, proposed data repair, changed files, and test results.

</details>

BUG-MOD-004 — البحث في تعديلات الحجوزات لا يرشح النتائج.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: تم إدخال BK-DEV-1002 والضغط على Enter، لكن الجدول استمر في عرض السجلات الأربعة بدل نتيجة واحدة. البحث الظاهر للمستخدم لا يعمل على رقم المرجع المختبر.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Fix the Filament booking-modifications table search after first reproducing that searching BK-DEV-1002 returns all four rows. Inspect the Resource table searchable-column configuration and relationship query scopes. Support exact/partial search by booking reference, modification public ULID, vendor display name, and proposer display name without exposing numeric internal IDs. Keep queries tenant/permission scoped and avoid N+1 regressions. Use database-appropriate case-insensitive matching and escape wildcard input. Add feature tests for each supported field, zero results, Arabic vendor names, pagination reset, and unauthorized records remaining hidden. Report the root cause, generated SQL/query change where useful, changed files, and test results.

</details>

المشكلات المكتشفة — جودة البيانات

BUG-DATA-001 — اسم مورد عربي مخزن أو معروض كعلامات استفهام.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: الفحص العميق لـ100 مسار في 6 سبتمبر 2026 أثبت تلف النص العربي في 16 شاشة إدارة. أعلى النتائج: مستندات الموردين 90 موضعًا، سجل الولاء 50، ملفات الموردين 40، أنواع المنتجات 36، قائمة الاعتماد 30، خدمات التأجير 21، واستيراد إكسل 18، بالإضافة إلى التغطية والحجوزات والبيع والرقمي والعمولات والسحوبات. هذا عيب بيانات/ترميز واسع وليس مجرد مشكلة عرض في صفحة واحدة.

الدليل: دليل 1 · دليل 2 · دليل 3 · دليل 4 · دليل 5

تاريخ آخر تحقق: 2026-09-06

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Perform a read-only Unicode data-path audit for the corrupted Arabic vendor name shown as question marks in both the dashboard and booking modifications. Trace the original vendor_profile value through request validation, PHP connection charset, MySQL/MariaDB database/table/column character sets and collations, imports/seeders, serialization, and Filament rendering. Determine whether the stored bytes are already replaced with literal '?' characters; if so, rendering changes cannot restore the name. Standardize the supported path on utf8mb4 with a compatible collation without broad unsafe schema rewrites. Add Arabic round-trip tests covering create, update, Excel import, API JSON, search, dashboard aggregation, and admin display. Produce a read-only list of affected rows and a reversible repair plan requiring an authoritative source for each lost name. Do not guess or auto-reconstruct production names. Report diagnosis, affected scope, proposed migration/config changes, and test results.

</details>

المشكلات المكتشفة — اعتماد الموردين

BUG-VENDORQ-001 — إجراءات اعتماد ورفض ظاهرة لملفات موردين ناقصة البيانات والمستندات.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: المورد QA الجديد ظهر Pending Review قبل تحقق البريد أو إضافة المستندات وساعات العمل والتغطية، وزر Approve ظاهر في قائمة الموردين. تعذر إكمال الضغط بسبب timeout في واجهة المتصفح، لذلك المثبت هو قابلية الإجراء في UI وليس نجاح الاعتماد على السيرفر.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-06

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Use the approved vendor-onboarding requirements as the source of truth. Audit the vendor approval eligibility contract in UI, policies, Actions, API endpoints, and domain services. A vendor missing required identity/contact fields or mandatory verified documents must not be approvable. Enforce the rule server-side inside the transactional approval Action under authorization; do not rely on disabled buttons alone. In the queue and review page, disable or omit approval actions and show a localized checklist of missing requirements. Keep rejection available only with a required reason and confirmation if the PRD permits it. Add tests for zero documents, missing email/contact, pending/rejected documents, fully eligible profiles, race conditions, repeated approval, role authorization, and immutable audit logs. Produce a read-only inventory of currently incomplete queued profiles; do not mutate production data or approve/reject records. Report the exact eligibility rule, changed files, and tests.

</details>

المشكلات المكتشفة — تدخل الحجوزات

BUG-INTERVENTION-001 — حجوزات تجاوزت المواعيد والأحداث منذ شهور ما زالت نشطة وقابلة للتدخل.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: السجلات الأربعة ما زالت في vendor_review رغم أن مواعيدها النهائية وأحداثها في مايو ويونيو ويوليو 2026، بينما الاختبار في سبتمبر 2026. هذا يرجح عدم تشغيل أو فشل آلية انتهاء المهلة/المعالجة الدورية.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Use the active PRD booking state machine. Diagnose why vendor_review bookings remain active months after vendor deadlines and event start times. Trace scheduler registration, cron/queue health, timeout commands/jobs, query scopes, UTC comparisons, row locks, idempotency, and failure/retry logs. Do not bulk-update production. Define the correct terminal/escalated outcome for each expired scenario from the PRD, preserving the rule that admins facilitate and customers choose alternatives. Add focused tests for deadline boundary, event already started, multi-vendor partial responses, queued retries, concurrent worker execution, and repeated runs. Provide a read-only affected-record inventory and a reversible remediation runbook requiring explicit approval before production mutation. Report root cause, changed files, and test results.

</details>

BUG-INTERVENTION-002 — إجراء اقتراح البدائل لا يعرض مراجعة أو تأكيدًا أو نتيجة واضحة.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: عند فتح «اقتراح بدائل» للفحص لم تظهر نافذة توضح البدائل أو المستلمين أو أثر الإجراء، ولم تظهر رسالة نجاح/فشل واضحة، بينما تعطلت أزرار الإجراءات. لم يتم تأكيد إرسال أو تغيير حالة. هذا يجعل الأدمن غير قادر على معرفة هل نُفذ إجراء تواصلي أم لا.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Audit the Suggest alternatives admin action end to end before editing. Determine whether clicking it immediately mutates state or sends notifications, opens a modal, dispatches a job, or currently fails/hangs. It must never choose a replacement vendor for the customer. Implement an explicit review step showing eligible alternatives, why each is eligible, recipients, message preview, and the exact non-destructive effect; require a separate confirmed submit. Prevent double submission with idempotency and show accessible loading, success, and actionable failure feedback while keeping unrelated row actions usable. Enforce authorization and booking-state eligibility server-side and emit one redacted audit event. Add browser/Pest tests for cancel-without-write, confirmed send, no eligible alternatives, provider failure, retry/idempotency, stale booking, and customer-choice preservation. Do not send production notifications or mutate production while diagnosing. Report the current side effect, root cause, changed files, and tests.

</details>

المشكلات المكتشفة — التدقيق والسجلات

BUG-STATE-001 — سجل تغييرات الحالة غير كافٍ للتدقيق ويحتوي انتقالًا بلا تغيير.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: السجل يعرض أرقام Booking وActor داخلية بدل المرجع العام واسم/دور المنفذ، وبعض المنفذين فارغون، ولا يظهر السبب أو المصدر أو correlation ID. وُجد انتقال cancelled إلى cancelled، وهو حدث بلا تغيير يحتاج منعًا أو تفسيرًا.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Audit the booking transition log against the PRD state machine and security requirements. Preserve it as append-only evidence. Prevent new no-op transitions such as cancelled→cancelled at the domain Action/state-machine boundary unless an explicitly named retry event is required; in that case store it as an event, not a state transition. Improve the admin read model to show booking public reference/ULID, localized old/new states, actor display name and role or a localized System actor, reason/source, request/correlation ID, and exact UTC timestamp with localized display. Do not rewrite historical production rows. Add tests for valid/invalid/no-op transitions, system actors, authorization, concurrency, immutability, and linking to the read-only booking detail. Produce a read-only inventory of no-op and missing-actor history, then report root cause, schema impact, changed files, and tests.

</details>

BUG-ACTIVITY-001 — سجل النشاط لا يوضح الحدث الفعلي ولا يصلح كأثر تدقيق موثوق.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: نوع السجلات ظاهر كـ Default وحقل Event فارغ في الصفوف المختبرة. الموضوعات تستخدم أرقامًا داخلية مثل Vendor Document #20، ولا تظهر تفاصيل التغيير أو النتيجة أو correlation ID؛ لذلك لا يمكن للأدمن معرفة ما حدث فعليًا.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Define and enforce a structured immutable audit-event contract for every sensitive admin/domain action. Each new event must include a stable event name, localized human label, subject type plus public reference, actor id/display name/role or System, timestamp, outcome, reason, correlation/request ID, and a redacted before/after diff where permitted. Never log passwords, tokens, bank numbers, document contents, or unnecessary PII. Update producers rather than fabricating meaning only in the UI; keep legacy events visible and explicitly labeled Legacy/Unknown. Improve filters for event, actor, subject reference, outcome, and date. Add tests that approval, rejection, booking decisions, refunds, settings changes, and failed authorization attempts emit exactly one redacted immutable event; include authorization and export tests. Do not rewrite production history without an approved migration plan. Report coverage gaps, changed files, and test results.

</details>

المشكلات المكتشفة — تسجيل وإدارة الموردين

BUG-VREG-SEARCH-001 — البحث لا يفلتر نتائج قوائم تسجيل وإدارة الموردين.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: إعادة اختبار حي باسم المورد الجديد QA Vendor Sep 06 داخل ملفات الموردين، ثم بالمرجع العام 01M1TAPCGAHFM0N9N5KVBCE5NC داخل قائمة الانتظار: البحث لم يصفِّ أيًا من الجدولين، واستمرت السجلات القديمة ظاهرة ولم يظهر مورد QA في نتيجة قائمة الانتظار.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-06

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Diagnose the shared Filament table-search failure across vendor documents, coverage areas, approved product types, and vendor profiles. Reproduce with the exact queries live_test.png, جوي رينتالز القاهرة, بطاقات بيكسل بارتي, and 01KTDBVDR1EGC13JB2JDRM99P9. Inspect table searchable-column declarations, relationship search queries, Livewire state binding/debounce, pagination reset, database collation, and permission scopes. Implement reliable exact and partial search using public identifiers and user-facing names; never expose or depend on numeric internal IDs. Escape wildcard input and keep authorization/tenant constraints in every relationship query. Add Pest/Livewire tests for each page, Arabic and English text, ULID, filename, zero results, pagination reset, and unauthorized records remaining hidden. Report the shared root cause, per-resource changes, query performance, and focused test results.

</details>

BUG-VPROFILE-001 — صفحة تفاصيل ملف المورد ترجع خطأ HTTP 500.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: إعادة اختبار حي على المورد QA الجديد 01M1TAPCGAHFM0N9N5KVBCE5NC: صفحة التفاصيل أعادت 500 Something Went Wrong. العيب قابل للتكرار على سجل أُنشئ في نفس الجولة.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-06

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Use AGENTS.md and the approved vendor-onboarding specification. Diagnose the HTTP 500 on the Filament vendor-profile detail route using the real exception in development/staging. Trace VendorProfileResource/ViewVendorProfile, infolists, document/coverage/hour/product-type relations, approval enums, nullable user/contact/bank fields, Unicode values, and public-ULID route binding. Do not expose credentials, bank data, document contents, or production PII, and do not mask the exception with a broad catch. Fix the root cause with null-safe typed presentation. The read-only detail must show an eligibility checklist and relevant relations before any decision. Add authorized-admin 200 tests for complete, incomplete, suspended, rejected, individual/company, Arabic-name, and missing-relation profiles; include unauthorized and unknown-ULID tests. Reuse the panel-aware error-page fix so admin errors return safely to /admin. Run focused tests/static analysis and report root cause, changed files, and results.

</details>

BUG-VDOC-001 — صفحة تعديل مستند المورد تفتقد سياق المستند والمراجعة الآمنة.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: صفحة التعديل تعرض فقط تاريخ الانتهاء ومفتاح «مستند حرج» مع حفظ وحذف؛ لا تعرض اسم المورد أو نوع المستند أو اسم الملف أو حالته أو معاينة/فتح المستند. زر «عرض» في القائمة لم يقدم نتيجة مرئية واضحة أثناء الفحص. لم يتم الحفظ أو الحذف.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Redesign the vendor-document review/edit flow around safe verification. Before editable expiry/critical fields, show a read-only header with localized vendor name and public reference, document type, original filename, upload date, current review status, reviewer, decision timestamp, and a secure preview/download action. Diagnose why the list View action provides no visible result and make it a reliable read-only modal/page with loading and error feedback. Keep Delete destructive, visually separated, permission-gated, and confirmed with impact text; do not weaken storage authorization or expose raw storage paths. Decision/state changes must use domain Actions and immutable audit events. Add tests for supported file types, missing file, unauthorized access, path traversal, expired document, Arabic filename, view-without-write, edit validation, delete confirmation, and audit redaction. Do not mutate production documents while diagnosing. Report root cause, changed files, screenshots, and tests.

</details>

المشكلات المكتشفة — الخدمات والاستيراد

BUG-SERVICE-STATE-001 — إجراءات الخدمة لا تحترم حالتها الحالية، ومنها اعتماد ونشر خدمة مؤرشفة.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: نماذج إنشاء/تعديل خدمات التأجير والرقمي تعرض اختيار الحالة الخام مباشرة: Draft وPendingReview وChangesRequested وPublished وRejected وArchived. هذا يؤكد أن الانتقالات ليست محمية بالكامل بإجراءات سياقية، ويتيح اختيار حالات لا تناسب دورة المراجعة من النموذج نفسه.

الدليل: دليل 1 · دليل 2 · دليل 3

تاريخ آخر تحقق: 2026-09-06

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Use the active PRD service lifecycle as binding. Audit every rental/sale/digital service transition in Filament actions, policies, API endpoints, commands, and domain Actions. Define an explicit allowed-transition matrix for Draft, PendingReview, ChangesRequested, Published, Rejected, and Archived. An Archived service must not expose or accept Approve & Publish, Reject, or Request Changes unless it first passes an authorized restore transition; Draft must follow the specified submit-for-review path. Enforce eligibility server-side transactionally and derive UI visibility from the same policy, not duplicated conditions. Preserve immutable audit history and idempotency. Add parameterized tests across all states, all three product types, roles, stale forms, concurrent decisions, and forged direct requests. Do not mutate production services. Report the matrix, current violations, changed files, and focused tests.

</details>

BUG-SALE-DETAIL-001 — صفحة تعديل خدمة البيع تعيد خطأ HTTP 500.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: إعادة اختبار حي: قائمة خدمات البيع تفتح، لكن /admin/sale-services/create تعيد 500 حدث خطأ ما. بذلك العيب أوسع من صفحة التفاصيل/التعديل ويمنع إنشاء خدمة بيع من لوحة الأدمن.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-06

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Diagnose the HTTP 500 on the Filament sale-service edit route using the real exception in development/staging. Compare the SaleService resource/form schema and this record shape with the working rental and digital edit pages. Inspect nullable relations, translations, money minor units, inventory/stock fields, media, categories, status enums, vendor relation, casts, and public-ULID binding. Do not expose production data or hide the error with a broad catch. Fix the root cause with typed null-safe form hydration while preserving domain Actions and state restrictions. Add authorized edit-page 200 tests for every sale-service state, Arabic/English translations, missing optional fields/media, corrupted legacy shapes, and authorization/404 cases. Include a save test only in isolated test data and verify no unintended status transition. Apply the panel-aware error-page fix and report root cause, files, and results.

</details>

BUG-INVENTORY-001 — حجز مخزون منتهي ما زال محجوزًا مؤقتًا ويعرض معرفات داخلية.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: أحد السجلات حالته «محجوز مؤقتاً» وexpires_at في 7 يونيو 2026 رغم أن الفحص في سبتمبر 2026. الجدول يعرض ULID الخدمة الخام ورقم المستخدم 85 بدل اسم الخدمة والعميل، وبعض فترات البداية والنهاية فارغة.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Audit temporary service-inventory reservation expiry end to end. Diagnose why a cart reservation with expires_at in June remains Reserved in September. Trace scheduler/queue execution, UTC comparisons, release Action, stock counters, row locks, idempotency, retries, and cart/checkout reconciliation. Do not release or repair production rows automatically. Enforce server-side expiry during availability checks as well as a documented scheduled cleanup so stale holds cannot block inventory. Improve the admin read model to show service name/public reference, customer display name/public reference, localized hold type/status, reserved period, expiry and overdue duration, never numeric foreign keys. Explicitly label non-applicable start/end dates for sale items. Add concurrency tests for expiry vs checkout, repeated cleanup, partial inventory, multi-item carts, and all product types. Provide a read-only affected inventory and reversible repair plan; report root cause, changes, and tests.

</details>

BUG-IMPORT-001 — عملية استيراد إكسل قديمة عالقة Pending وصفحة تفاصيلها تعيد 500.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: الاستيراد 01KTD2MSFCSNZPQDKDW3CY2V55 لملف filled.xlsx ما زال pending منذ 5 يونيو 2026 مع 0 إجمالي و0 مستورد و0 أخطاء. فتح صفحة التفاصيل أعاد 500، لذلك لا يمكن معرفة سبب التعطل أو إعادة المحاولة بأمان.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Diagnose both the stale Pending Excel import and the HTTP 500 on its admin detail page using logs and isolated development/staging fixtures. Trace upload persistence, job dispatch, queue routing/workers, transaction boundaries, parser initialization, status transitions, counters, failure capture, timeouts, retries, and relation/form rendering. Do not retry or mutate production imports. Define terminal Completed/Failed/Cancelled behavior plus a stale-processing watchdog; retries must be explicit, authorized, idempotent, and must not duplicate services. The detail page must render sanitized row-level errors, counts, timestamps, actor/vendor, and job correlation without exposing storage paths or spreadsheet PII. Add tests for valid/invalid/empty files, worker failure, timeout, duplicate retry, mixed row outcomes, unsupported columns, authorization, and detail rendering for every state. Provide a read-only inventory and remediation runbook, then report root causes, changed files, and tests.

</details>

المشكلات المكتشفة — الإشراف والمدفوعات والتسويات

BUG-PAYMENT-DETAIL-001 — تفاصيل الدفع والاسترداد تعيد خطأ HTTP 500.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: فتح الدفع 01KSH6S2B1VTYQGYJ1F1X6X1MA والاسترداد 427WDXFXT7C0K756XDYRETXN2T أعاد 500 في الحالتين. صفحة الخطأ تعيد الأدمن إلى /vendor-portal، ما يمنع التدقيق قبل أي قرار مالي.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Use AGENTS.md, the active PRD, and payment module contracts. Diagnose the HTTP 500 on both Filament Payment and Refund detail pages from the real exceptions in development/staging. Inspect Money casts/minor units, gateway enums, booking/public references, attempts, webhook relations, refund reason/status enums, nullable timestamps, provider payload redaction, and public-ULID route binding. Never expose card data, gateway secrets, tokens, raw payload PII, or production customer details; do not hide errors with broad catches. Fix typed null-safe read-only rendering and the panel-aware admin error return. Add authorized 200 tests for captured/failed/pending/partially refunded payments and pending/completed/failed refunds, missing optional relations, legacy payloads, authorization, and unknown ULIDs. Assert sensitive fields are redacted. Run focused tests/static analysis and report root causes, files, and results.

</details>

BUG-WITHDRAWAL-001 — طلبات سحب قديمة ما زالت Pending وتفاصيل سجل المراجعة ترجع 500.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: طلبا سحب بقيمتي 650 و500 جنيه ما زالا Pending منذ 27 و23 مايو 2026. فتح تفاصيل سجل السحب المرفوض 01KTGYS2WM5XRFV9VVB9J4AAF9 أعاد 500. لم يتم اعتماد أي طلب.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Audit the withdrawal lifecycle and the HTTP 500 on withdrawal-audit detail. Determine whether old Pending requests are valid backlog, missing SLA escalation, or failed workflow/queue processing. Trace reserve ledger entries, available/pending balances, approval/rejection Actions, bank-destination validation, dual-control policy, idempotency, notifications, and immutable audit records. Do not approve, reject, or transfer production funds. Fix the detail page with redacted financial data and public identifiers. Add server-side transition guards, explicit stale/SLA indicators, and tests for insufficient balance, concurrent approval, duplicate requests, rejection release, payout failure/retry, role separation, stale pending, and audit rendering. Produce a read-only inventory and reversible remediation runbook; report root causes, changed files, and tests.

</details>

BUG-WEBHOOK-001 — سجل Webhook لا يوضح صحة التوقيع أو المعالجة بصورة موثوقة.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: عمود «التوقيع صحيح» فارغ في السجلات المختبرة، وبعض الأحداث لها تاريخ معالجة وبعضها لا، وأسماء النوع غير موحدة مثل Transaction وT R A N S A C T I O N وRefund Completed. هذا يمنع التمييز بين حدث آمن، مرفوض، عالق أو معالج.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Define a secure normalized gateway-webhook audit contract. Persist and display an explicit signature result (valid/invalid/not-checked with reason), normalized event type, receive/process timestamps, processing status, attempt count, idempotency outcome, sanitized error code, and correlation reference. Never display secrets, signature material, full headers, card data, or raw customer payloads. Normalize historical display without rewriting evidence; label legacy unknowns explicitly. Ensure invalid signatures cannot dispatch business events and duplicate valid webhooks are idempotent. Add tests for valid/invalid/missing signatures, duplicate delivery, malformed payload, unsupported event, processing exception/retry, ordering, and redaction. Provide a read-only count of blank/unknown signature records and report root cause, files, and results.

</details>

BUG-IDEMPOTENCY-001 — مفاتيح منع التكرار ظاهرة كاملة في لوحة الإدارة بعد انتهاء صلاحيتها.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: الجدول يعرض القيم الكاملة للمفاتيح والمسارات، ومنها مفاتيح QA طويلة، رغم أن expires_at انتهى في يونيو. هذا يزيد تسريب تفاصيل الطلبات ويشير إلى غياب سياسة احتفاظ أو إخفاء واضحة.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Treat idempotency keys as sensitive operational identifiers. Mask them by default in the admin UI, exposing only a short prefix/suffix and a privileged audited copy action if strictly required. Never expose stored request/response bodies or authorization data. Define and implement a documented retention/cleanup policy for expired keys that preserves financial replay safety for the required window; do not delete production rows until retention/legal requirements and affected counts are reviewed. Keep uniqueness scoped correctly by actor/endpoint and hash stored keys if the current contract allows. Add authorization, masking, expiry-boundary, concurrent replay, cross-user isolation, cleanup idempotency, and audit-redaction tests. Report threat model, current retention, proposed policy, migration impact, and results.

</details>

BUG-FINANCE-IDENTITY-001 — الشاشات المالية تستخدم أرقامًا داخلية وأسماء كلاس تقنية بدل المراجع العامة.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: الاسترداد يعرض الحجز 1، والمحفظة تعرض VendorProfile والمالك 2، وروابط دفتر الأستاذ والمطابقة تستخدم /12 و/1 رغم وجود ULID عام، ونتيجة المطابقة تعرض App\Modules\Settlement\Domain... ومعرّف المورد 1.

الدليل: دليل 1 · دليل 2 · دليل 3 · دليل 4 · دليل 5

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Audit identifier exposure and route binding across refunds, wallets, ledger groups, reconciliation runs, and findings. Replace numeric foreign keys and PHP class names in user-facing read models with localized entity labels, human references, display names, and public ULIDs. Bind admin detail routes to public identifiers consistently and return 404 for unknown references without leaking existence. Keep internal IDs only inside server-side joins and logs protected by policy. Create a typed presenter/map for morph classes and enums rather than string manipulation. Add tests preventing numeric-id route access where not explicitly required, verifying ULID links, missing/deleted actor fallbacks, authorization, Arabic/English labels, and no class-name leakage. Preserve financial ledger integrity and do not rewrite historical entries. Report affected resources/routes, backward-compatibility plan, files, and tests.

</details>

BUG-MODERATION-SLA-001 — تقييمات Pending قديمة ما زالت في طابور الإشراف دون أولوية أو SLA.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: شاشة الإشراف الافتراضية تعرض تقييمات Pending منذ 23 مايو 2026 في سبتمبر، دون مدة انتظار أو شارة متأخر أو تصعيد. الإجراءات قبول ورفض فقط، ولا يظهر سبب/سياق أو سجل سابق قبل القرار.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Use the review-moderation policy and PRD. Define the moderation SLA and diagnose why Pending reviews can remain unresolved for months. Add queue-age and overdue indicators, priority sorting, safe read-only context, previous moderation history, and reason/policy-code requirements before Reject/Hide. Keep Accept/Reject/Hide server-authorized, confirmed, idempotent, and logged; do not moderate production records during diagnosis. If automatic escalation is required, implement it via a documented scheduled job without auto-accepting content. Add tests for pending age boundaries, duplicate decisions, conflicting moderators, hidden/accepted/rejected transitions, abusive content flags, language handling, authorization, notifications, and audit events. Provide a read-only stale-review count and report policy, root cause, files, and results.

</details>

المشكلات المكتشفة — الدعم والثقة والاتصالات

BUG-SUPPORT-COMMS-DETAIL-001 — تفاصيل البلاغ والحملة والمحادثة وعلامة الإشراف تعيد 500.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: أربع صفحات تفاصيل مختبرة تعيد صفحة الخطأ العامة: Report 580X513K5SGNM72XKNAAY1CXJ7، Campaign 1، Chat Thread 4، وChat Moderation Flag 1. صفحة الخطأ تعيد الأدمن إلى /vendor-portal.

الدليل: دليل 1 · دليل 2 · دليل 3 · دليل 4

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Use AGENTS.md and the active PRD. Diagnose the four Filament admin detail-page 500s from their real exceptions in development/staging: Report, Campaign, ChatThread, and ChatModerationFlag. Inspect public/numeric route binding, enums and translation keys, polymorphic targets, nullable actors/reviewers, campaign schedule/audience casts, chat message/flag relations, PII redaction, and authorization. Do not expose production message content, phone/email data, tokens, or campaign recipients; do not hide errors with broad catches. Fix typed null-safe read-only rendering and panel-aware admin error recovery. Add authorized 200 tests for every relevant state and legacy/null relation, plus unauthorized/404/redaction tests. Run focused tests/static analysis and report a root cause per page, shared causes, changed files, and results.

</details>

BUG-NOTIFICATION-QUEUE-001 — حملة وإشعارات قديمة ما زالت مجدولة أو Queued دون تنفيذ.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: حملة May party reminder ما زالت «مجدولة» منذ 23 مايو 2026. عدة إرساليات payment.captured ما زالت Queued منذ 8 أغسطس 2026، بمحاولات 0 ومزود خدمة فارغ. هذا يرجح فشل scheduler/queue أو dispatch routing.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Diagnose stale Scheduled campaigns and Queued notification dispatches end to end before editing. Trace scheduler registration/cron, queue connections and workers, delayed jobs, provider routing, user preferences/quiet hours/timezones, campaign audience expansion, transaction commit timing, retries, dead-letter/failure storage, and idempotency. Do not send or retry production notifications. Define terminal Failed/Cancelled/Sent states and a stale-job watchdog with observable reason codes. Ensure campaign execution and per-recipient dispatch are idempotent and respect opt-outs and quiet hours. Add tests for due scheduling, timezone boundaries, missing provider, queue outage/recovery, duplicate worker delivery, partial channel failure, user preference suppression, and stale jobs. Provide a read-only affected-count report and approved remediation runbook; report root causes, files, and results.

</details>

BUG-CHAT-PII-001 — علامات إشراف المحادثة تكشف رقم الهاتف كاملًا.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: عمود matched_pattern يعرض الرقم +201001234567 كاملًا داخل قائمة الإشراف. حتى للأدمن، كشف القيمة المطابقة كاملة غير ضروري لفرز البلاغ ويزيد مخاطر إساءة استخدام البيانات.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Treat moderation matches as sensitive PII. Mask phone numbers, emails, links, and other detected values in list views; show only the minimum context needed for review. If full reveal is legally and operationally required, gate it behind a dedicated least-privilege permission, explicit reason, short-lived reveal, and immutable audit event. Never log or export full matched PII by default. Preserve evidence through encrypted/restricted storage and show redacted message context. Add tests for Arabic/international phone formats, emails, URLs, false positives, copy/export, role authorization, audit logging, and no PII in HTML/search indexes/exception logs. Produce a read-only exposure inventory and report threat model, changed files, and tests without repeating real production values.

</details>

BUG-CHAT-MODERATION-SLA-001 — محادثة وبلاغ إشراف مفتوحان منذ مايو دون معالجة ظاهرة.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: Chat Thread 4 مفتوح وبه بلاغ واحد منذ 16 مايو 2026، وعلامة الإشراف ما زالت unresolved بلا reviewer أو reviewed_at. لا توجد شارة مدة انتظار أو أولوية أو تصعيد واضحة.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Use the trust-and-safety moderation policy to define SLA, ownership, and allowed transitions for chat flags and restricted threads. Diagnose why an unresolved flag can remain open for months. Add queue age, severity, assignment, reviewer, escalation, and decision reason; keep Freeze/Unfreeze/Resolve/Dismiss server-authorized, confirmed, idempotent, and audited. Do not resolve or expose production conversations while diagnosing. Preserve customer/vendor due process and prevent administrators from silently editing message evidence. Add concurrency tests for two reviewers, SLA boundaries, repeated decisions, frozen threads, false positives, appeals if specified, notifications, and audit immutability. Provide a read-only stale-flag inventory and report policy gaps, root cause, files, and results.

</details>

BUG-NOTIFICATION-TEMPLATE-001 — قالب الإشعار يستخدم متغيرًا غير موثق في جدول المتغيرات.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: قالب booking.alternative يحتوي {{booking_id}} داخل الرسالة، بينما جدول «المتغيرات» فارغ. لا تظهر معاينة أو تحقق من المتغيرات المطلوبة قبل الحفظ/الاستخدام، ما قد ينتج رسائل بplaceholder خام أو معرف داخلي غير مناسب.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Define a schema-backed notification-template variable contract per event/channel/audience. For booking.alternative, document and validate every placeholder and prefer a customer-safe booking reference rather than an internal id. Parse templates at save time; reject unknown, missing, malformed, or forbidden variables and validate EN/AR parity. Add a redacted preview with representative synthetic data, channel length/format checks, and clear fallback behavior so unresolved placeholders can never be sent. Keep template edits authorized, versioned, audited, and reversible; do not send production messages while testing. Add tests for unknown/missing variables, nested values, escaping/injection, Arabic/English rendering, SMS length, HTML email sanitization, inactive template, and rollback. Report the event schemas, changed files, migration impact, and results.

</details>

BUG-SUPPORT-WORKFLOW-001 — تفاصيل تذكرة الدعم لا توفر دورة معالجة كاملة.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: صفحة التذكرة تعرض ID والحالة والعميل والبريد والموضوع والنص فقط. لا يظهر assignee أو priority أو SLA أو سجل الردود/الملاحظات أو ارتباط موثوق بالحجز، ولا يوجد رد أو تغيير حالة داخل صفحة التفاصيل.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Implement the support-ticket admin workflow from the approved support specification. The read/detail page must show public reference, requester, verified related booking/payment link, category, priority, SLA/age, assignee, status history, customer-visible replies, internal notes, attachments, and immutable audit events. Add authorized Actions for assign, reply, internal note, mark in progress, resolve, and reopen with confirmations where appropriate; never expose internal notes to customers. Sanitize attachments/content and restrict PII by role. Use server-side transition guards and idempotent notification dispatch. Add tests for ownership/assignment, SLA, reply visibility, attachment security, status transitions, concurrent agents, authorization, notification failure, and audit logs. Do not update production tickets during development. Report the current missing contract, changed files, and results.

</details>

المشكلات المكتشفة — بقية أقسام الإدارة

BUG-LOYALTY-I18N-001 — وحدة الولاء تعرض مفاتيح الترجمة الخام بدل أسماء الأعمدة.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: شاشات البرامج والقواعد والسجل تعرض loyalty.columns.public_id وloyalty.columns.vendor وبقية المفاتيح حرفيًا. هذا يجعل الجداول غير قابلة للاستخدام العملي ويكشف أسماء تنفيذ داخلية.

الدليل: دليل 1 · دليل 2 · دليل 3

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Fix the loyalty admin localization contract across programs, rules, ledgers, and redemptions. Inventory every loyalty.* translation key used by Filament resources/actions/forms and add complete Arabic and English strings with correct namespaces and fallback behavior. Do not hardcode labels in resources as a shortcut. Localize enum values, directions, rule kinds, dates, point values, empty states, actions, validation, and audit reasons. Add automated tests that render each page in ar and en and fail if any translation-key pattern such as loyalty. or :: remains visible. Include RTL/LTR headed-browser screenshots, long names, missing legacy values, and accessibility. Do not change loyalty calculations or production data. Report missing keys, root cause, files, and tests.

</details>

BUG-SUBSCRIPTION-INTEGRITY-001 — بيانات الاشتراك والفاتورة والحالة غير متسقة زمنيًا وماليًا.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: اشتراك silver حالته active رغم انتهائه في 31 أغسطس 2026 والفحص في 5 سبتمبر، واسم المورد فارغ. فاتورة Paid بقيمة 99 جنيه تبدأ فترتها 15 أغسطس لكن تاريخ استحقاقها 1 أغسطس، أي قبل بداية الفترة. صفحة Subscription Payments فارغة رغم وجود فاتورة مدفوعة؛ قد يكون هناك مصدر دفع آخر لكن الواجهة لا تفسر ذلك.

الدليل: دليل 1 · دليل 2 · دليل 3

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Audit subscription lifecycle and billing integrity before changing data. Trace start/end/renewal/grace/override timestamps, scheduler/queue expiry, invoice period and due-date generation, payment linkage, status derivation, vendor relation, UTC handling, and legacy seeds/imports. Define and enforce invariants: active subscriptions cannot remain active past end/grace without an explicit audited override; invoice due_at must follow the approved billing policy relative to period_start; every Paid invoice must have an explainable payment/credit/manual settlement reference. Add application validation and compatible database constraints where safe. Add tests for renewal boundaries, timezone, failed payment, grace, override expiry, missing vendor relation, manual settlement, duplicate gateway events, and concurrency. Produce a read-only affected-record inventory and reversible remediation plan; do not mutate production or run migrations without approval. Report root causes, schema impact, files, and tests.

</details>

BUG-ANALYTICS-EMPTY-001 — صفحتا تحليلات الاشتراكات والإعلانات لا تعرضان أي مؤشرات أو حالة فارغة.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: كل صفحة تعرض العنوان فقط دون بطاقات أو جداول أو رسم أو تفسير لعدم وجود بيانات. في المقابل توجد خطط واشتراكات وفواتير وباقات إعلانات واشتراك إعلان، لذلك الفراغ غير مفسر ويحتمل تعطل widgets أو queries.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Diagnose why subscription and advertising analytics pages render only headings despite existing underlying records. Inspect widget registration, lazy loading, authorization, date defaults, query scopes, cache, Livewire errors, and aggregate models. Do not fabricate metrics or query production in an unbounded way. Implement explicit loading, empty, error, and populated states. Define metric contracts from the PRD, including time window, currency, status inclusion, timezone, and denominator; link each metric to a filtered source list. Add feature/query tests against known fixtures, zero-data tests, authorization, cache invalidation, date boundaries, multi-currency handling, and performance limits. Provide before/after screenshots and reconcile displayed totals against source records. Report root cause, metric definitions, files, and tests.

</details>

BUG-SEARCH-DATA-001 — سجلات البحث تحتوي عبارات فارغة والبحث المحفوظ يعرض مصفوفات خام.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: عدة Search Logs للضيف تعرض عبارة بحث فارغة مع 0 أو 6 نتائج. Saved Searches تعرض عوامل التصفية كنص JSON/array خام مثل ["rental"] وقيمة 300000 دون تسمية أو تنسيق مالي، ما يضعف التدقيق ويزيد عرض بيانات سلوكية بلا سياق.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Audit search telemetry and saved-search presentation with privacy minimization. Determine why blank queries are persisted and whether they represent browse/filter events; either classify them explicitly or stop logging meaningless empty searches. Render saved filters as localized labeled chips/values with properly formatted money and locations, never raw JSON. Define retention, access, and redaction rules for behavioral search data and anonymous users; avoid logging sensitive free text unnecessarily. Add tests for empty/whitespace queries, filters-only searches, Arabic/English queries, guest identity, saved-filter serialization round trip, money minor units, deletion/retention jobs, authorization, and export redaction. Produce a read-only affected count and report contract, root cause, files, and results.

</details>

BUG-ADMIN-ROUTES-001 — مسارات إدارية متعددة تستخدم أرقام قاعدة البيانات بدل المعرّفات العامة.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: ظهر ذلك في category-field-schemas/1/edit وcampaigns/1 وnotification-templates/53/edit وapp-settings/3/edit وfeature-flags/3/edit وshield/roles/1/edit وغيرها، بينما أجزاء أخرى تستخدم ULID. هذا يسبب عدم اتساق ويزيد قابلية تعداد الموارد.

الدليل: دليل 1 · دليل 2 · دليل 3 · دليل 4 · دليل 5 · دليل 6

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Inventory all admin routes that bind records by numeric database id and classify which entities already have public ULIDs. Standardize externally visible admin detail/edit routes on public identifiers where required by the project architecture, with policy authorization and non-enumerable 404 behavior. Do not simply add ULIDs or migrations without checking the approved architecture and existing traits/contracts. Preserve backward compatibility only through authenticated temporary redirects if necessary, never leaking existence across permissions. Update Filament recordRouteKeyName/resource bindings, links, tests, API docs, and audit references consistently. Add tests rejecting unauthorized numeric enumeration, accepting valid public ids, unknown ids, deleted records, and cross-resource collisions. Report inventory, migration impact, compatibility plan, files, and results.

</details>

المشكلات المكتشفة — دورة المورد الكاملة

BUG-VENDOR-SERVICE-WORKFLOW-001 — إجراءات مراجعة الخدمة لا تتقيد بحالتها الحالية وتوجد خدمات ناقصة مطلوبة الحقول.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: قائمة خدمات التأجير تعرض «اعتماد ونشر، رفض، طلب تعديلات» لكل الحالات، بما فيها منشور ومؤرشف ومسودة، بدل إظهار الانتقالات القانونية فقط. خدمة Pending Review سعرها موجود لكن الوصف المختصر المعلّم كمطلوب فارغ في شاشة التعديل، ما يدل على أن الخدمة وصلت للمراجعة دون استيفاء عقد النشر.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Use the approved catalog/vendor-service specification as the source of truth. Define one explicit Service state machine for Draft, PendingReview, ChangesRequested, Published, Rejected, and Archived. Render only legal actions for the current state and enforce the same transition guards in policies, application Actions, APIs, imports, and queued jobs; never rely on hidden buttons. Block submission to review and publication when required bilingual content, category, vendor eligibility, pricing, inventory/rental requirements, and media rules are incomplete. Preserve admin moderation without allowing arbitrary state assignment through edit forms. Add immutable actor/reason/from/to audit events and idempotent notifications. Add tests for every allowed and forbidden transition, incomplete required fields, published/archived records, repeated clicks, concurrent moderation, suspended vendors, authorization, and notification failure. Produce a read-only inventory of invalid current services and a reversible remediation plan; do not mutate production. Report the state diagram, root cause, changed files, and tests.

</details>

BUG-VENDOR-BANK-DATA-001 — بيانات المورد البنكية الحساسة تظهر كاملة افتراضيًا في شاشات الإدارة.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: صفحة مراجعة المورد وصفحة تعديل المورد تعرضان رقم IBAN كاملًا واسم صاحب الحساب واسم البنك مباشرة. حتى مع صلاحية super_admin، العرض الافتراضي غير المقنع يزيد خطر التسريب عبر الشاشة أو التسجيلات ولا يوضح وجود كشف مؤقت مسجل أو فصل لصلاحية البيانات البنكية.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Harden vendor bank-data handling without breaking settlement operations. Classify IBAN, account-holder, bank, and SWIFT fields as sensitive. Mask values by default in onboarding, profile, tables, exports, logs, activity payloads, and error reports; provide an authorized time-limited reveal only to the minimum finance/approval roles with explicit purpose and immutable audit event. Separate view-sensitive-bank-data and update-sensitive-bank-data permissions, re-authenticate or step-up if required by the security specification, and never return full values to unauthorized Livewire payloads or client HTML. Encrypt at rest using the project's approved mechanism and plan key rotation; do not log plaintext or expose it in fixtures/screenshots. Add tests for masking, reveal authorization/expiry/audit, exports, validation, update workflows, serialization, logs, and breach-safe exceptions. Inventory exposures read-only and report schema/compatibility impact, files, and tests; do not rewrite production bank data without approval.

</details>

المشكلات المكتشفة — الصلاحيات ودورة المورد

BUG-VENDOR-ROLE-EDITOR-001 — محرر دور Vendor لا يحمّل الصلاحيات الحالية وقد يمسحها عند الحفظ.

الحالة: يحتاج إصلاح

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: قائمة الأدوار تقول إن Vendor لديه 6 صلاحيات، لكن /shield/roles/2/edit عرض 1050 checkbox وكلها غير محددة بعد اكتمال التحميل، بينما زر الحفظ فعّال. لم يتم الحفظ. كذلك المسار يستخدم المعرّف الرقمي 2.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Treat this as a P0 authorization-integrity defect. Reproduce the Vendor role mismatch read-only: the index reports six permissions while the edit form hydrates zero selected out of roughly 1050 and allows save. Trace Spatie Permission, Shield form state paths, guard names, case normalization, permission naming with :: separators, cached permissions, Livewire hydration, role route binding, and plugin/version compatibility. Do not save any production role. Fix hydration and add a server-side optimistic/concurrency guard that refuses a destructive permission replacement when the submitted baseline is missing or stale. Show current/added/removed permission counts and require explicit confirmation for removals. Add tests for loading each role, exact round-trip preservation without edits, six Vendor permissions remaining six, Admin/Super Admin protections, guard mismatch, cache reset, concurrent edits, unauthorized access, and audit diff. Prefer public identifiers where required. Report root cause, affected roles read-only, changed files, and tests.

</details>

13. الجغرافيا والمحتوى والمظهر

BUG-STOREFRONT-LOCALIZATION-001 — أسماء الموردين والخدمات العربية يجب ألا تظهر كعلامات استفهام

الحالة: يحتاج إصلاح

التصنيف الأصلي: عيب واجهة حي

المشكلة/نتيجة الفحص: في الصفحة الرئيسية العربية وصفحة البحث العربية، ظهرت أسماء بيانات فعلية كبائعين أو خدمات على هيئة ???????? بدل نص مقروء. النسخة الإنجليزية لا تعرض هذا التشوه في الفحص الحالي. المشكلة مرئية للمستخدم وتمنع اعتبار تحسين الواجهة مكتملًا.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-06

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Work only in /home/instaparty/public_html and focus on the Laravel Blade storefront, not APIs. Diagnose why selected vendor/service names render as literal question marks on Arabic public pages while English is readable. Trace seeded/imported strings, model casts/accessors, database connection and collation/charset, fallback locale selection, Blade escaping, and any image/card fallback payload. Preserve existing records and do not bulk-rewrite production data. Implement the smallest safe display-layer/data-normalization fix, add only focused regression coverage for Arabic and English service/vendor cards, verify /ar and /ar/search visually with no question-mark names or horizontal overflow, then commit the fix with a concise report.

</details>

مشكلات UI/UX وتحسين بصري (17)

PRD — 7.1 Customer Discovery and Entry

FR-3 — The system must support broad browsing of vendors/services

الحالة: تحسين تصميم

التصنيف الأصلي: PRD الملزم

المشكلة/نتيجة الفحص: التصفح العام موجود ويعرض 6 خدمات من الأنواع الثلاثة مع فلاتر وترتيب، لكنه لا يجتاز بصريًا: بطاقات بخلفيات فارغة أو صور غير مرتبطة بالخدمة، وبيانات عربية تالفة بعلامات استفهام، وتاريخ بصيغة mm/dd/yyyy داخل RTL.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-06

6. واجهة العميل والصفحة الرئيسية

WEB-001 — ترتيب الصفحة المعتمد: القسم الرئيسي مع شريط البحث.

الحالة: تحسين تصميم

التصنيف الأصلي: QA والتشغيل

المشكلة/نتيجة الفحص: الفحص البصري الحي: نص الـHero موضوع فوق تفاصيل صورة مزدحمة ويضعف القراءة، والوصف يمر فوق الكعكة، كما تتداخل بطاقة البحث بصريًا مع نهاية الصورة. حقل التاريخ يعرض mm/dd/yyyy داخل الواجهة العربية.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-06

WEB-005 — مقدمو الخدمات/الموردون.

الحالة: تحسين تصميم

التصنيف الأصلي: QA والتشغيل

المشكلة/نتيجة الفحص: صفحة البائعين تعمل، لكن بطاقات البائعين تعرض كتل صور خضراء فارغة كبيرة، والـHero مرتفع جدًا مقارنة بالمحتوى، وتقييم 0.0 يظهر كأنه تقييم حقيقي بدل حالة «لا توجد تقييمات».

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-06

WEB-011 — صفحة النتائج والمرشحات والترتيب وحالات عدم وجود نتائج.

الحالة: تحسين تصميم

التصنيف الأصلي: QA والتشغيل

المشكلة/نتيجة الفحص: صفحة النتائج وظيفية لكنها لا تجتاز بصريًا: نصوص عربية تالفة، صورة خدمة مفقودة وأخرى صور مخزون غير مرتبطة بالخدمة، شريط فلاتر طويل وثقيل بصريًا، وصيغة تاريخ إنجليزية داخل RTL.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-06

WEB-012 — صفحة تفاصيل الخدمة لكل نوع منتج.

الحالة: تحسين تصميم

التصنيف الأصلي: QA والتشغيل

المشكلة/نتيجة الفحص: تفاصيل خدمة التأجير تفتح، لكن مساحة الصورة الرئيسية فارغة وضخمة، السعر واتجاه الأرقام غير مستقرين في RTL، ويظهر 10x10 وmin 45 بترتيب/لغة غير مناسبين، وبطاقة الشراء تسبق سياق الخدمة بصريًا.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-06

WEB-014 — التنقل والـ footer وصفحات المحتوى والأسئلة الشائعة.

الحالة: تحسين تصميم

التصنيف الأصلي: QA والتشغيل

المشكلة/نتيجة الفحص: حالة السلة الفارغة واضحة، لكن الصفحة تحتوي فراغات رأسية مبالغًا فيها، والـFooter يعرض مفاتيح/نصوص إنجليزية خام مثل Footer secondary وFooter primary داخل العربية.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-06

WEB-017 — حالات التحميل والفراغ والخطأ والاتصال الضعيف.

الحالة: تحسين تصميم

التصنيف الأصلي: QA والتشغيل

المشكلة/نتيجة الفحص: حالات الفراغ موجودة، لكن حالة السلة الفارغة تستخدم مساحة كبيرة بلا قيمة، ونتيجة المعالج الفارغة لا توضح أثر الفلاتر ولا تقدم بدائل/توسيع بحث مناسب، بينما بعض الوسائط المفقودة تظهر ككتل لونية فقط.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-06

تحسينات التصميم — الحجوزات والتفاوض

UX-OPS-001 — تحسين تصميم شاشة متابعة التفاوض وإبراز التأخير والمخاطر.

الحالة: تحسين تصميم

التصنيف الأصلي: تحسين تصميم

المشكلة/نتيجة الفحص: الشاشة نظيفة عمومًا لكنها لا تساعد على ترتيب الأولويات: المواعيد المستحقة القديمة لا تحمل شارة «متأخر» أو مدة التأخير، جميع الصفوف تبدو متساوية، والتاريخ بصيغة هجينة مثل «يونيو 8, 2026». كما يطغى إجراء الإلغاء الأحمر على غياب إجراء العرض.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Follow DESIGN.md and the Arabic-first RTL admin conventions. Redesign only the presentation of /admin/bookings-monitor; do not refactor backend business logic or add migrations. Add an urgency indicator derived from the existing due timestamp: overdue badge plus human-readable delay duration, with accessible text and non-color-only meaning. Use locale-correct Arabic and English date/time formatting and keep UTC storage unchanged. Add a primary read-only View action and visually demote Force Cancel as a destructive secondary action. Improve responsive table behavior on tablet/mobile without hiding reference, customer, status, due time, or the View action. Preserve Filament components and design tokens. Add/adjust headed-browser tests for Arabic RTL, English LTR, overdue and non-overdue rows, mobile width, and keyboard navigation. Return screenshots before/after, changed files, and test results.

</details>

تحسينات التصميم — قائمة الحجوزات

UX-BKG-001 — تحسين قابلية قراءة واستجابة جدول الحجوزات.

الحالة: تحسين تصميم

التصنيف الأصلي: تحسين تصميم

المشكلة/نتيجة الفحص: الجدول عريض ومقتطع بصريًا مع الشريط الجانبي. يعرض المعرّف العام الطويل بجوار رقم المرجع المفهوم، ما يستهلك المساحة، بينما الإجمالي والتاريخ والإجراء قد تخرج من الشاشة. التاريخ بصيغة عربية/إنجليزية مختلطة، والبحث لا يوضح أنه يحتاج Enter.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Follow DESIGN.md and improve only the Filament admin bookings list presentation; do not add migrations or change booking logic. Make the human booking reference the primary identifier and hide the long public ULID by default while retaining it in the column toggle/copy action. Define a compact default column set: reference, customer, booking status, payment status, fulfilment status, total, submitted date, View. Make the table usable at desktop, tablet, and mobile widths through responsive priorities or a compact row/card pattern without hiding critical status or the View action. Use locale-correct EN/AR dates and direction-safe phone/currency rendering. Either apply search on debounce or show a clear accessible search-submit affordance; do not leave hidden Enter-only behavior. Preserve keyboard access, Filament components, and design tokens. Add headed-browser coverage at desktop and mobile widths and return before/after screenshots plus test results.

</details>

تحسينات التصميم — تعديلات الحجوزات

UX-MOD-001 — تحسين تصميم وتعريب قائمة تعديلات الحجوزات.

الحالة: تحسين تصميم

التصنيف الأصلي: تحسين تصميم

المشكلة/نتيجة الفحص: الجدول يحتاج تمريرًا أفقيًا، والإجراءات تقع خارج الرؤية، والـ ULID الطويل ظاهر افتراضيًا. نوع الاقتراح والحالة والأزرار بالإنجليزية داخل واجهة عربية، والتاريخ هجين، ولا توجد مقارنة مختصرة تبين السعر أو الوقت قبل/بعد.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Follow DESIGN.md and the Arabic-first RTL requirements. Improve only the booking-modifications list/detail presentation after the 500 is fixed; do not alter decision authority or booking state logic. Hide the long public ULID by default and prioritize booking reference, vendor, proposer, localized proposal type, localized status, expiry/urgency, and View. Keep all decision actions out of the default table until their PRD authorization is resolved. Use locale-correct EN/AR labels and dates, direction-safe identifiers, and an accessible before/after summary for changed price, quantity, time, notes, and added/removed items. Make the table responsive without relying on a wide horizontal scrollbar; preserve keyboard navigation and non-color-only status cues. Add headed-browser evidence for Arabic RTL, English LTR, desktop, and mobile. Return before/after screenshots, changed files, and test results.

</details>

تحسينات التصميم — عمليات الإدارة

UX-INBOX-001 — تصحيح صياغة الحالة الفارغة في صندوق وارد المشرف.

الحالة: تحسين تصميم

التصنيف الأصلي: تحسين تصميم

المشكلة/نتيجة الفحص: النص الحالي «لا توجد صندوق وارد المشرف» غير صحيح لغويًا ويعطي حالة فارغة ضعيفة دون شرح أو إجراء تالٍ.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Fix the admin inbox empty-state copy and presentation using the existing localization system. Use natural Arabic such as «لا توجد رسائل في صندوق وارد المشرف حاليًا» and an equivalent English string. Explain whether filters may be hiding results and provide a Reset filters action only when filters are active. Preserve RTL/LTR, Filament components, and accessibility. Add localization/render tests for empty unfiltered and empty filtered states; do not change inbox business logic.

</details>

تحسينات التصميم — تدخل الحجوزات

UX-INTERVENTION-001 — تبسيط وتعريب جدول تدخل الحجوزات وإبراز الأولوية.

الحالة: تحسين تصميم

التصنيف الأصلي: تحسين تصميم

المشكلة/نتيجة الفحص: الحالة الخام vendor_review ظاهرة بالإنجليزية، التاريخ هجين، وصف طويل من الإجراءات في كل صف، ولا يوجد إبراز واضح للتأخير أو خطورة الحالة.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Follow DESIGN.md and Arabic-first RTL conventions. Redesign only the intervention table presentation: localize state labels and dates, show overdue duration and severity with accessible non-color cues, keep View as the primary row action, and move secondary actions into a labeled overflow menu grouped by communication, escalation, and chat control. Require confirmation and a visible consequence summary before every mutation. Keep customer-choice boundaries intact and do not change backend state logic. Add responsive and keyboard tests for Arabic RTL and English LTR at desktop and mobile widths, plus screenshots before/after.

</details>

تحسينات التصميم — تسجيل وإدارة الموردين

UX-VREG-001 — تحسين عرض جداول تسجيل الموردين وتوحيد اللغة والسياق.

الحالة: تحسين تصميم

التصنيف الأصلي: تحسين تصميم

المشكلة/نتيجة الفحص: التواريخ بصيغة عربية/إنجليزية هجينة، بعض الجداول بلا إجراءات عرض، ملفات الموردين تعرض ULID طويلًا افتراضيًا وتكدس خمسة إجراءات في الصف، والأيام المغلقة تظهر بخانات وقت فارغة بدل شارة واضحة.

الدليل: دليل 1 · دليل 2 · دليل 3 · دليل 4 · دليل 5

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Follow DESIGN.md and Arabic-first RTL conventions. Standardize the vendor-admin tables without changing domain rules: use localized dates/times and state labels, display business names as the primary identity, hide long ULIDs by default but retain copy access, provide a read-only View action where context is needed, label closed business days explicitly, and group destructive/mutating actions in a secondary overflow menu with confirmation. Keep approval decisions unavailable until the eligibility checklist passes. Make tables responsive at desktop/tablet/mobile widths and preserve keyboard navigation, focus states, and non-color-only status meaning. Add headed-browser coverage for Arabic RTL and English LTR with screenshots before/after and report changed files and tests.

</details>

تحسينات التصميم — الخدمات والاستيراد

UX-SERVICE-001 — تعريب وتبسيط صفحات الخدمات والاستيراد والمخزون.

الحالة: تحسين تصميم

التصنيف الأصلي: تحسين تصميم

المشكلة/نتيجة الفحص: الوظائف الأساسية لصفحات الاستيراد الثلاث تفتح وزر الاستيراد محمي، لكن عناوين الصفحات ما زالت إنجليزية خام: Import Rental Services Page وImport Sale Services Page وImport Digital Services Page. كذلك نموذج الخدمة يعرض أسماء حالات Enum إنجليزية خام داخل واجهة عربية.

الدليل: دليل 1 · دليل 2 · دليل 3

تاريخ آخر تحقق: 2026-09-06

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Follow DESIGN.md and Arabic-first RTL conventions. Localize all import headings, form labels, service status options, tab labels, media labels, dates, and feedback while preserving equivalent English LTR. Use service/vendor/customer display names as primary identifiers and keep public ULIDs available through copy/details, not default wide columns. Group state-dependent actions in a secondary menu and keep View/Edit separate from destructive actions. Replace raw minor-unit guidance with a localized currency input that still persists exact minor units. Make lists/forms responsive and accessible with keyboard focus, upload progress, non-color-only status, and concise confirmation text. Add headed-browser screenshots and tests for Arabic/English, desktop/mobile, empty/error/loading states, and long names. Do not change business rules.

</details>

تحسينات التصميم — الإشراف والمدفوعات والتسويات

UX-MODFIN-001 — توحيد تعريب وتصميم شاشات الإشراف والمالية.

الحالة: تحسين تصميم

التصنيف الأصلي: تحسين تصميم

المشكلة/نتيجة الفحص: عناوين مثل Review Moderation Page وDispute Oversight Page إنجليزية، والحالات والأسباب والأنواع تظهر Raw مثل pending وCaptured وcustomer_request وCalculated وVendorProfile، والتواريخ والعملات مختلطة الاتجاه والصيغة.

الدليل: دليل 1 · دليل 2 · دليل 3 · دليل 4 · دليل 5

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Follow DESIGN.md and Arabic-first RTL conventions. Create a shared localized presentation layer for payment, refund, moderation, settlement, withdrawal, ledger, reconciliation, gateway, event, and reason enums. Translate page headings and labels; format dates, money, identifiers, and mixed Latin values direction-safely without changing stored UTC or minor units. Prioritize human references and statuses, move raw technical codes into an optional diagnostic detail, group mutating actions behind clear confirmation, and highlight stale/failed/high-risk items with accessible non-color cues. Make dense tables responsive and keyboard accessible. Add headed-browser screenshots/tests for Arabic RTL and English LTR at desktop/mobile widths, plus empty/loading/error/long-value states. Do not change financial rules.

</details>

تحسينات التصميم — الدعم والثقة والاتصالات

UX-SUPPORT-COMMS-001 — تعريب وتوحيد واجهات الدعم والثقة والاتصالات.

الحالة: تحسين تصميم

التصنيف الأصلي: تحسين تصميم

المشكلة/نتيجة الفحص: شاشات FAQ والتذاكر والبلاغات وشارات الثقة وعناصر الحملات تستخدم عناوين وأعمدة إنجليزية، وتظهر مفاتيح ترجمة خام في البلاغات وعلامات الإشراف وسجل الرسائل، مع روابط رقمية مثل campaigns/1 وnotification-templates/53.

الدليل: دليل 1 · دليل 2 · دليل 3 · دليل 4 · دليل 5 · دليل 6

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Follow DESIGN.md and Arabic-first RTL conventions. Complete localization for FAQ, support, reports, trust badges, campaigns, notification records, and chat moderation; no raw translation keys, enum codes, English column names, or internal route ids may appear in the Arabic UI. Use localized human labels with technical codes only in an optional diagnostic detail. Replace numeric detail routes with public ULIDs, format dates and mixed-direction identifiers safely, mask PII, and show SLA/age/severity with accessible non-color cues. Make dense tables responsive and group mutating actions behind clear confirmation. Add headed-browser tests/screenshots for Arabic RTL and English LTR, desktop/mobile, empty/loading/error/stale states, and long content. Do not change business rules.

</details>

تحسينات التصميم — بقية أقسام الإدارة

UX-REMAINING-001 — توحيد تعريب وتصميم الأقسام المتبقية في لوحة الإدارة.

الحالة: تحسين تصميم

التصنيف الأصلي: تحسين تصميم

المشكلة/نتيجة الفحص: الفحص العميق لأول 100 مسار أثبت 19 شاشة بعنوان إنجليزي خام داخل الواجهة العربية: صفحات استيراد الأنواع الثلاثة، مراجعة التقييمات، FAQ، الدعم، التقارير، شارات الثقة، النزاعات، Promo Codes، تعديلات الخدمات، خمس شاشات اشتراكات، تحليلات الإعلانات، وتقرير الضرائب. تبقى أيضًا الشاشات الموثقة خارج المئة مثل قواعد توجيه البريد والعنوان الهجين «علامة ميزةs».

الدليل: دليل 1 · دليل 2 · دليل 3 · دليل 4 · دليل 5 · دليل 6

تاريخ آخر تحقق: 2026-09-06

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Follow DESIGN.md and Arabic-first RTL conventions. Complete a systematic localization and responsive-UI pass across promotions, catalog, loyalty, subscriptions, ads, tax, search, appearance, settings, and security. No raw English headings/columns, translation keys, enum codes, or malformed mixed words such as «علامة ميزةs» may remain in Arabic. Keep technical keys visible only in a clearly labeled diagnostic field when operationally necessary. Standardize dates, money, percentages, active-state switches, empty states, confirmations, and destructive-action placement. Preserve English LTR parity, public identifiers, keyboard access, and non-color-only states. Add an automated untranslated-string smoke test plus headed-browser screenshots for every section at desktop/mobile widths. Do not alter business logic.

</details>

عوائق تمنع استكمال الاختبار (3)

اختبارات معلقة — الحجوزات والتفاوض

BLOCK-BOOKING-TEST-RUNTIME-001 — نسخة الإنتاج لا تحتوي بيئة اختبار قابلة لتشغيل اختبارات مؤشرات الحجوزات بأمان.

الحالة: محجوب

التصنيف الأصلي: اختبار معلق

المشكلة/نتيجة الفحص: phpunit.xml يشير إلى SQLite في الذاكرة، لكن مجلد tests وملفات bootstrap وتنفيذ Pest/PHPUnit واعتماديات التطوير غير موجودة في النسخة المنشورة. لذلك لم تُشغّل اختبارات ولم يُدّع وجود نتائج. يلزم نسخة تطوير معزولة ومصدر كود موثوق.

الدليل: /home/instaparty/public_html/phpunit.xml · غياب /home/instaparty/public_html/tests

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Prepare an isolated development checkout from the authoritative repository and exact deployed revision before implementing metric changes. Install locked development dependencies only in that isolated environment, verify APP_ENV=testing, an isolated SQLite :memory: or dedicated disposable database, fake queue/mail/SMS/payment transports, and fail-closed guards preventing production URLs/databases. Restore or create the standard Pest bootstrap and the two focused suites NegotiationMonitorQueryTest and NegotiationMetricsTest without copying vendor/ or generated runtime state from production. Run the focused tests, static analysis, and formatting there; capture commands, versions, commit/revision, and complete pass/fail output. Do not install Composer dev packages, create tests, or execute migrations in /home/instaparty/public_html. Report environment provenance, setup gaps, files, and results.

</details>

المشكلات المكتشفة — عمليات الإدارة

BLOCK-INBOX-001 — تعذر اختبار دورة صندوق الوارد كاملة لعدم وجود بيانات اختبار.

الحالة: محجوب

التصنيف الأصلي: مشكلة مكتشفة

المشكلة/نتيجة الفحص: الحالة الفارغة تمنع التحقق من فتح الرسالة، الإسناد، الرد، الإغلاق الفردي والجماعي، الصلاحيات، وسجل التدقيق.

الدليل: دليل 1

تاريخ آخر تحقق: 2026-09-05

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Use the active PRD and project test conventions. Add deterministic development/staging fixtures for the admin inbox covering unassigned, assigned, unread, escalated, and closed conversations in both Arabic and English. Include at least two admin roles so authorization and tenant visibility can be tested. Do not seed or mutate production. Add Pest/browser tests for search, filtering, open/read, assignment, reply, individual close, bulk close with confirmation, idempotency, notification side effects, and immutable audit events. Ensure all mutating tests run in isolated transactions or a disposable environment. Report fixture files, test cases, and results.

</details>

اختبارات معلقة — دورة المورد الكاملة

BLOCK-VENDOR-E2E-001 — تنفيذ دورة المورد المتغيرة بالكامل يحتاج حسابات اختبار معزولة وبيئة غير إنتاجية.

الحالة: محجوب

التصنيف الأصلي: اختبار معلق

المشكلة/نتيجة الفحص: التسجيل أصبح ناجحًا وأنشأ المورد QA Vendor Sep 06، وبوابة المورد تعيد الحساب غير المتحقق إلى email-verification/prompt بصورة صحيحة. استكمال تعديل الملف والمستندات والخدمات والاعتماد محجوب لأن البريد المستخدم بنطاق .test لا يمكن استلام رسالة التحقق عليه، ولا توجد وسيلة تحقق QA داخل الواجهة الحالية.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-06

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Create a safe, repeatable staging E2E harness for the complete vendor lifecycle without using production side effects. Seed isolated QA Vendor, Customer, Support, Finance, and Super Admin accounts with documented non-secret identifiers and securely managed credentials; create deterministic categories, rental/sale/digital services, documents, coverage, business hours, inventory, bookings, modifications, payments, refunds, commissions, wallet, withdrawal, notifications, and audit fixtures. Use Paymob sandbox/fake gateway, fake mail/SMS/FCM transports, frozen UTC time, queue and scheduler workers, and teardown by an explicit QA run id. Cover approval and rejection branches, incomplete onboarding, service changes, concurrent inventory holds, payment failure/retry/idempotent webhook, cancellation/refund, settlement/withdrawal, role denial by direct URL, notification delivery/failure, and immutable audit trails. Never point the harness at production; fail closed on production APP_ENV/URL/database. Add Playwright and Pest suites, data factories, cleanup verification, and a result report with evidence. Report setup, commands, fixtures, changed files, and pass/fail matrix.

</details>

اختبارات بدأت ولم تُحسم (5)

PRD — 7.2 Service Selection and Booking Draft

FR-5 — Customers can assemble a booking composed of multiple services and/or products from multiple vendors

الحالة: قيد التحقق

التصنيف الأصلي: PRD الملزم

المشكلة/نتيجة الفحص: بدأ فحص تكوين حجز متعدد الخدمات والموردين. بطاقات الخدمات وروابط الإضافة موجودة، لكن رابط «أضف إلى السلة» يعيد المستخدم إلى معالج المناسبة، ولم تُثبت بعد سلة تحتوي عناصر من أكثر من مورد.

الدليل: دليل 1 · دليل 2 · دليل 3

تاريخ آخر تحقق: 2026-09-06

4. تسجيل الموردين وإدارتهم

VEN-006 — مناطق التغطية وربطها بالخدمة والعنوان.

الحالة: قيد التحقق

التصنيف الأصلي: QA والتشغيل

المشكلة/نتيجة الفحص: نموذج تعديل مورد QA يحتوي قسم مناطق التغطية وإجراء إضافة، وصفحة قائمة التغطية تفتح وتعرض المورد بالاسم والمرجع العام. لم يُختبر حفظ/منع منطقة غير صالحة بعد.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-06

VEN-007 — ساعات العمل والاستثناءات والإجازات.

الحالة: قيد التحقق

التصنيف الأصلي: QA والتشغيل

المشكلة/نتيجة الفحص: نموذج المورد يحتوي قسم ساعات العمل، وقائمة ساعات العمل تعرض الاسم والمرجع العام بصورة صحيحة. لم يُختبر بعد حفظ يوم عمل أو التداخل أو الاستثناءات والإجازات.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-06

VEN-009 — استكمال الملف التجاري وبيانات التسوية.

الحالة: قيد التحقق

التصنيف الأصلي: QA والتشغيل

المشكلة/نتيجة الفحص: نموذج تعديل المورد يفتح ويعرض بيانات النشاط والتسوية، لكن صفحة العرض العامة للمورد تعيد 500. الواجهة العربية تعرض Cairo وNasr City وRemove item بالإنجليزية، ولم يتم إرسال تعديل لتجنب تغيير البيانات قبل اكتمال تحقق الحقول.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-06

<details>
<summary>برومبت الإصلاح الجاهز</summary>

Fix the vendor profile detail 500 and fully localize the Filament edit form. Use public identifiers, safely handle missing optional relationships, localize governorate/city display and select removal labels, mask bank data by default, and preserve authorization. Add focused Blade/Filament tests for an incomplete QA vendor and verify detail/edit pages live without changing existing production vendors.

</details>

VEN-011 — منع المورد غير المعتمد من نشر خدمة أو استقبال حجز.

الحالة: قيد التحقق

التصنيف الأصلي: QA والتشغيل

المشكلة/نتيجة الفحص: المورد QA ما زال Pending Review وبوابة المورد توقفه عند تحقق البريد، وهو سلوك حماية صحيح حتى هذه النقطة. لم يمكن اختبار محاولة نشر خدمة من حسابه قبل اجتياز البريد والوصول للبوابة.

الدليل: دليل 1 · دليل 2

تاريخ آخر تحقق: 2026-09-06

ملاحظات إدارة التقرير

لا تُحوّل البنود غير المفحوصة إلى ناجحة لمجرد أن الصفحة تفتح.

يجب إعادة اختبار كل بند بعد النشر وتسجيل الدليل والتاريخ والنتيجة.

البنود المتداخلة تُجمع في Workstream واحد عند التنفيذ، مع إبقاء كل معرف لاختبار القبول.

الاختبارات المالية والحذف وتغيير الصلاحيات يجب تنفيذها ببيانات QA معزولة وبوابة دفع تجريبية.

</div>


