<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Shared\Domain\Enums\CmsSlug;
use App\Modules\Shared\Domain\Models\CmsPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InformationalCmsPagesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $page) {
            CmsPage::query()->firstOrCreate(
                ['slug' => $page['slug']->value],
                [
                    'public_id' => (string) Str::ulid(),
                    'title' => $page['title'],
                    'body' => $page['body'],
                    'meta_description' => $page['meta_description'],
                    'is_published' => true,
                    'published_at' => now(),
                ],
            );
        }
    }

    /** @return list<array{slug: CmsSlug, title: array{en: string, ar: string}, meta_description: array{en: string, ar: string}, body: array{en: string, ar: string}}> */
    private function pages(): array
    {
        return [
            [
                'slug' => CmsSlug::About,
                'title' => ['en' => 'About InstaParty', 'ar' => 'عن إنستابارتي'],
                'meta_description' => [
                    'en' => 'Meet the event marketplace designed to make discovering services and planning celebrations feel simpler.',
                    'ar' => 'تعرّف على سوق خدمات المناسبات المصمم لتسهيل اكتشاف الخدمات وتنظيم الاحتفالات.',
                ],
                'body' => [
                    'en' => <<<'HTML'
<h2>Our story</h2>
<p>Celebrations bring people together, but planning them can mean scattered conversations, unclear options, and many separate decisions. InstaParty was created to give customers one organised place to discover event services and shape an occasion around their date, location, and needs.</p>
<p>The marketplace brings service information, providers, packages, and booking steps into a clearer journey while keeping the people behind every celebration at its heart.</p>
<h2>What we do</h2>
<p>InstaParty helps customers explore event services, review available options, and connect the pieces of an event in one place. Providers can present what they offer with useful details so customers can make informed choices.</p>
<ul><li>Discover services for different kinds of occasions.</li><li>Compare options using the details made available by providers.</li><li>Keep booking information and updates together.</li><li>Build an event step by step without losing sight of the whole plan.</li></ul>
<h2>Our mission</h2>
<p>Our mission is to make event planning feel more understandable and organised for customers, while giving service providers a thoughtful marketplace in which to present their work.</p>
<blockquote>Every celebration is different. The platform is designed to help people find the combination of services that fits their own occasion.</blockquote>
<h2>How InstaParty helps</h2>
<ol><li><strong>Explore:</strong> browse event services and ideas from one marketplace.</li><li><strong>Compare:</strong> review the information, packages, and availability providers share.</li><li><strong>Plan:</strong> bring selected services into a single event journey.</li><li><strong>Stay organised:</strong> follow booking details and updates from one account.</li></ol>
<h2>For service providers</h2>
<p>InstaParty gives providers a dedicated place to showcase their services, explain their packages, and reach customers who are actively planning occasions. Providers remain responsible for keeping their service information accurate and fulfilling confirmed commitments.</p>
<h2>Celebrations, brought together</h2>
<p>From the first idea to the final details, InstaParty aims to make the planning journey calmer, clearer, and easier to follow. We are continuing to improve that journey for customers and providers alike.</p>
HTML,
                    'ar' => <<<'HTML'
<h2>قصتنا</h2>
<p>تجمع المناسبات الناس حول لحظات مميزة، لكن التخطيط لها قد يتطلب محادثات متفرقة وخيارات غير واضحة وقرارات كثيرة منفصلة. انطلقت إنستابارتي لتمنح العملاء مكاناً منظماً واحداً لاكتشاف خدمات المناسبات وتنسيق احتفال يناسب التاريخ والموقع والاحتياج.</p>
<p>يجمع السوق معلومات الخدمات ومقدميها والباقات وخطوات الحجز في رحلة أوضح، مع إبقاء الأشخاص الذين يصنعون كل مناسبة في قلب التجربة.</p>
<h2>ماذا نقدم</h2>
<p>تساعد إنستابارتي العملاء على استكشاف خدمات المناسبات ومراجعة الخيارات المتاحة وجمع تفاصيل المناسبة في مكان واحد. كما يستطيع مقدمو الخدمات عرض ما يقدمونه بمعلومات مفيدة تساعد العملاء على اتخاذ قرارات مدروسة.</p>
<ul><li>اكتشاف خدمات تناسب أنواعاً مختلفة من المناسبات.</li><li>مقارنة الخيارات بالاعتماد على التفاصيل التي يضيفها مقدمو الخدمات.</li><li>الاحتفاظ بمعلومات الحجوزات والتحديثات في مكان واحد.</li><li>بناء المناسبة خطوة بخطوة مع رؤية الخطة كاملة.</li></ul>
<h2>مهمتنا</h2>
<p>مهمتنا أن نجعل تخطيط المناسبات أكثر وضوحاً وتنظيماً للعملاء، وأن نوفر لمقدمي الخدمات سوقاً مناسباً لعرض أعمالهم والتواصل مع المهتمين بها.</p>
<blockquote>لكل مناسبة طابعها الخاص، ولذلك صُممت المنصة لتساعد كل شخص على اختيار مزيج الخدمات المناسب لاحتفاله.</blockquote>
<h2>كيف تساعدك إنستابارتي</h2>
<ol><li><strong>استكشف:</strong> تصفح خدمات وأفكار المناسبات من سوق واحد.</li><li><strong>قارن:</strong> راجع المعلومات والباقات والتوفر الذي يشاركه مقدمو الخدمات.</li><li><strong>خطط:</strong> اجمع الخدمات المختارة ضمن رحلة مناسبة واحدة.</li><li><strong>ابق منظماً:</strong> تابع تفاصيل الحجوزات والتحديثات من حساب واحد.</li></ol>
<h2>لمقدمي الخدمات</h2>
<p>تمنح إنستابارتي مقدمي الخدمات مساحة مخصصة لعرض خدماتهم وشرح باقاتهم والوصول إلى عملاء يخططون بالفعل لمناسباتهم. ويظل مقدم الخدمة مسؤولاً عن دقة معلوماته وتنفيذ الالتزامات المؤكدة.</p>
<h2>كل تفاصيل المناسبة في مكان واحد</h2>
<p>من الفكرة الأولى حتى التفاصيل الأخيرة، تهدف إنستابارتي إلى جعل رحلة التخطيط أكثر هدوءاً ووضوحاً وسهولة في المتابعة، مع مواصلة تطوير التجربة للعملاء ومقدمي الخدمات.</p>
HTML,
                ],
            ],
            [
                'slug' => CmsSlug::Privacy,
                'title' => ['en' => 'Privacy Policy', 'ar' => 'سياسة الخصوصية'],
                'meta_description' => ['en' => 'An overview of how InstaParty handles account, booking, payment, and technical information.', 'ar' => 'نظرة عامة على كيفية تعامل إنستابارتي مع بيانات الحساب والحجز والدفع والبيانات التقنية.'],
                'body' => [
                    'en' => <<<'HTML'
<blockquote><strong>Initial content for review:</strong> This policy is demonstration content for the development environment and requires legal review before production publication.</blockquote>
<h2>Introduction</h2><p>This policy explains, in general terms, the information InstaParty may handle when people use the marketplace, create accounts, arrange bookings, communicate, or ask for support.</p>
<h2>Information we collect</h2><p>The information involved depends on how you use the platform. It may include details you provide, records created through marketplace activity, and technical information produced when the service is used.</p>
<h2>Account information</h2><p>Account details may include your name, contact information, sign-in details, preferred language, saved addresses, and information used to keep your profile and access secure.</p>
<h2>Booking information</h2><p>When you plan an event or request a service, the platform may handle event dates, locations, selected services, attendee-related preferences, messages, booking status, and changes needed to coordinate the request.</p>
<h2>Payment information</h2><p>Payment-related records may include amounts, currency, transaction references, payment status, and refund information. Payment credentials may be handled by the configured payment provider rather than stored directly by InstaParty.</p>
<h2>How we use information</h2><ul><li>Operate accounts and provide marketplace features.</li><li>Coordinate bookings and share relevant details with involved providers.</li><li>Process and reconcile payments through configured providers.</li><li>Respond to support requests and communicate service updates.</li><li>Protect the platform, investigate misuse, and improve reliability.</li></ul>
<h2>Sharing information</h2><p>Relevant information may be shared with the providers involved in a booking, vendors supporting platform operations, or authorities when disclosure is required. The information shared should relate to the purpose for which it is needed.</p>
<h2>Service providers</h2><p>InstaParty may rely on hosting, communication, analytics, identity, payment, and support providers. Their access depends on the service they provide and the applicable arrangements with the platform.</p>
<h2>Cookies and technical data</h2><p>Browsers and devices may provide IP address, device type, browser details, timestamps, language, diagnostics, and interaction data. Cookies or similar technologies may support sessions, preferences, security, and service measurement.</p>
<h2>Data retention</h2><p>Information is kept only for as long as reasonably needed for the purpose it supports, platform security, record keeping, dispute handling, or applicable obligations. Different records may follow different retention needs.</p>
<h2>Your choices and rights</h2><p>You may be able to update profile information through your account and request help with access, correction, or other privacy questions through the available support channels. Available choices can depend on the request and applicable requirements.</p>
<h2>Security</h2><p>InstaParty uses organisational and technical measures intended to protect information. No online service can promise absolute security, so users should also protect their credentials and report suspicious activity.</p>
<h2>Changes to this policy</h2><p>This policy may be updated as the marketplace, its providers, or relevant requirements change. The current published version will appear on this page.</p>
<h2>Contact</h2><p>For questions about this policy or information associated with your account, use the contact options published on the InstaParty Contact page.</p>
HTML,
                    'ar' => <<<'HTML'
<blockquote><strong>محتوى أولي للمراجعة:</strong> هذه السياسة محتوى توضيحي لبيئة التطوير وتحتاج إلى مراجعة قانونية قبل نشرها في الإنتاج.</blockquote>
<h2>مقدمة</h2><p>توضح هذه السياسة بصورة عامة المعلومات التي قد تتعامل معها إنستابارتي عند استخدام السوق أو إنشاء حساب أو ترتيب حجز أو التواصل أو طلب الدعم.</p>
<h2>المعلومات التي نجمعها</h2><p>تختلف المعلومات بحسب طريقة استخدام المنصة، وقد تشمل البيانات التي تقدمها والسجلات الناتجة عن نشاطك في السوق والمعلومات التقنية التي تنشأ أثناء استخدام الخدمة.</p>
<h2>بيانات الحساب</h2><p>قد تشمل بيانات الحساب الاسم ومعلومات التواصل وبيانات تسجيل الدخول واللغة المفضلة والعناوين المحفوظة والمعلومات المستخدمة للحفاظ على الملف الشخصي وأمان الوصول.</p>
<h2>بيانات الحجوزات</h2><p>عند التخطيط لمناسبة أو طلب خدمة، قد تتعامل المنصة مع تاريخ المناسبة وموقعها والخدمات المختارة والتفضيلات المتعلقة بالحضور والرسائل وحالة الحجز والتعديلات اللازمة لتنسيق الطلب.</p>
<h2>بيانات الدفع</h2><p>قد تشمل سجلات الدفع المبالغ والعملة ومراجع المعاملات وحالة الدفع ومعلومات الاسترداد. وقد يتولى مزود الدفع المعتمد معالجة بيانات الدفع الحساسة بدلاً من تخزينها مباشرة لدى إنستابارتي.</p>
<h2>كيفية استخدام المعلومات</h2><ul><li>تشغيل الحسابات وتقديم وظائف السوق.</li><li>تنسيق الحجوزات ومشاركة التفاصيل اللازمة مع مقدمي الخدمات المعنيين.</li><li>معالجة المدفوعات وتسويتها عبر المزودين المعتمدين.</li><li>الرد على طلبات الدعم وإرسال تحديثات الخدمة.</li><li>حماية المنصة والتحقق من إساءة الاستخدام وتحسين الاعتمادية.</li></ul>
<h2>مشاركة المعلومات</h2><p>قد تُشارك المعلومات ذات الصلة مع مقدمي الخدمات المشاركين في الحجز أو الجهات التي تدعم تشغيل المنصة أو الجهات المختصة عندما يكون الإفصاح مطلوباً. وينبغي أن ترتبط المعلومات المشاركة بالغرض الذي تُستخدم من أجله.</p>
<h2>مقدمو الخدمات</h2><p>قد تعتمد إنستابارتي على مزودي استضافة واتصالات وتحليلات وهوية ودفع ودعم. ويتحدد وصولهم إلى المعلومات بحسب الخدمة التي يقدمونها والترتيبات المطبقة مع المنصة.</p>
<h2>ملفات تعريف الارتباط والبيانات التقنية</h2><p>قد توفر المتصفحات والأجهزة عنوان الشبكة ونوع الجهاز وتفاصيل المتصفح والتوقيت واللغة وبيانات التشخيص والتفاعل. وقد تُستخدم ملفات تعريف الارتباط أو تقنيات مشابهة للجلسات والتفضيلات والأمان وقياس الخدمة.</p>
<h2>الاحتفاظ بالبيانات</h2><p>تُحتفظ المعلومات للمدة اللازمة بشكل معقول للغرض الذي تدعمه أو لأمان المنصة أو حفظ السجلات أو معالجة النزاعات أو الوفاء بالمتطلبات المطبقة. وقد تختلف احتياجات الاحتفاظ باختلاف نوع السجل.</p>
<h2>خياراتك وحقوقك</h2><p>يمكنك تحديث بعض بيانات الملف الشخصي من حسابك وطلب المساعدة بشأن الوصول أو التصحيح أو غيرها من أسئلة الخصوصية عبر قنوات الدعم المتاحة. وقد تختلف الخيارات المتاحة بحسب الطلب والمتطلبات المطبقة.</p>
<h2>الأمان</h2><p>تستخدم إنستابارتي إجراءات تنظيمية وتقنية تهدف إلى حماية المعلومات. ولا توجد خدمة عبر الإنترنت يمكنها ضمان الأمان المطلق، لذلك ينبغي للمستخدم حماية بيانات الدخول والإبلاغ عن أي نشاط مريب.</p>
<h2>التغييرات على السياسة</h2><p>قد تُحدّث هذه السياسة مع تطور السوق أو مزوديه أو المتطلبات ذات الصلة، وستظهر النسخة المنشورة الحالية في هذه الصفحة.</p>
<h2>التواصل</h2><p>للاستفسار عن هذه السياسة أو المعلومات المرتبطة بحسابك، استخدم خيارات التواصل المنشورة في صفحة تواصل معنا على إنستابارتي.</p>
HTML,
                ],
            ],
            [
                'slug' => CmsSlug::Terms,
                'title' => ['en' => 'Terms & Conditions', 'ar' => 'الشروط والأحكام'],
                'meta_description' => ['en' => 'The general terms for accounts, providers, bookings, payments, and responsible use of InstaParty.', 'ar' => 'الشروط العامة للحسابات ومقدمي الخدمات والحجوزات والمدفوعات والاستخدام المسؤول لإنستابارتي.'],
                'body' => [
                    'en' => <<<'HTML'
<blockquote><strong>Initial content for review:</strong> These terms are demonstration content for the development environment and require legal review before production publication.</blockquote>
<h2>Introduction</h2><p>These terms describe the general rules for using the InstaParty marketplace. By using the platform, users agree to follow the current published terms and the information shown during the relevant booking journey.</p>
<h2>Using InstaParty</h2><p>Users should provide accurate information, use the platform lawfully, and avoid activity that harms other users, providers, or the operation of the marketplace.</p>
<h2>Accounts</h2><p>You are responsible for information submitted through your account and for protecting your sign-in credentials. Tell support promptly if you believe an account has been accessed without permission.</p>
<h2>Marketplace role</h2><p>InstaParty provides a marketplace through which customers can discover services and providers can present their offerings. Unless a service states otherwise, the provider remains responsible for the service it offers and fulfils.</p>
<h2>Services and providers</h2><p>Service descriptions, inclusions, exclusions, location coverage, availability, and provider requirements should be reviewed before booking. Providers are responsible for keeping their listings reasonably accurate and current.</p>
<h2>Bookings</h2><p>A request or draft is not necessarily a confirmed booking. The status displayed in the customer account identifies the current stage. Users should review the final service details, event information, and provider messages before confirmation.</p>
<h2>Prices and payments</h2><p>Prices, currency, charges, and available payment methods are shown during the applicable booking flow. Payment processing may be completed by an external payment provider under its own service terms.</p>
<h2>Changes and cancellations</h2><p>Available changes, cancellations, and refund handling may depend on the service, provider, booking stage, and terms shown before confirmation. Users should review the applicable information before committing to a booking.</p>
<h2>User responsibilities</h2><ul><li>Submit accurate event and contact details.</li><li>Review booking information and respond to necessary updates.</li><li>Treat providers and support staff respectfully.</li><li>Use platform communication and payment features responsibly.</li></ul>
<h2>Provider responsibilities</h2><ul><li>Describe services and availability accurately.</li><li>Communicate material changes promptly.</li><li>Fulfil confirmed services according to the agreed details.</li><li>Follow marketplace, safety, and account requirements.</li></ul>
<h2>Prohibited use</h2><p>Users must not misuse accounts, attempt unauthorised access, publish unlawful or deceptive material, interfere with platform operation, abuse other people, or use the marketplace to facilitate prohibited activity.</p>
<h2>Intellectual property</h2><p>The platform interface, brand assets, and original platform materials belong to their respective owners. Providers and users remain responsible for having the rights needed for content they upload or publish.</p>
<h2>Platform availability</h2><p>The team works to keep InstaParty available and useful, but maintenance, provider outages, security events, or technical problems may occasionally affect access or individual features.</p>
<h2>Changes to these terms</h2><p>These terms may change as the marketplace and its services develop. The current published version will be available on this page, and continued use is subject to that version.</p>
<h2>Contact</h2><p>Questions about these terms or a particular booking can be sent through the support options published on the InstaParty Contact page.</p>
HTML,
                    'ar' => <<<'HTML'
<blockquote><strong>محتوى أولي للمراجعة:</strong> هذه الشروط محتوى توضيحي لبيئة التطوير وتحتاج إلى مراجعة قانونية قبل نشرها في الإنتاج.</blockquote>
<h2>مقدمة</h2><p>توضح هذه الشروط القواعد العامة لاستخدام سوق إنستابارتي. وباستخدام المنصة يوافق المستخدم على اتباع الشروط المنشورة حالياً والمعلومات المعروضة خلال رحلة الحجز ذات الصلة.</p>
<h2>استخدام إنستابارتي</h2><p>ينبغي للمستخدم تقديم معلومات صحيحة واستخدام المنصة بصورة مشروعة وتجنب أي نشاط يضر بالمستخدمين أو مقدمي الخدمات أو تشغيل السوق.</p>
<h2>الحسابات</h2><p>أنت مسؤول عن المعلومات المقدمة من خلال حسابك وعن حماية بيانات تسجيل الدخول. أخبر الدعم سريعاً إذا اعتقدت أن حسابك استُخدم دون إذن.</p>
<h2>دور المنصة</h2><p>توفر إنستابارتي سوقاً يكتشف العملاء من خلاله الخدمات ويعرض مقدمو الخدمات ما يقدمونه. وما لم توضح الخدمة خلاف ذلك، يظل مقدم الخدمة مسؤولاً عن الخدمة التي يعرضها وينفذها.</p>
<h2>الخدمات ومقدمو الخدمات</h2><p>ينبغي مراجعة وصف الخدمة وما تشمله وما لا تشمله ونطاق الموقع والتوفر ومتطلبات مقدم الخدمة قبل الحجز. ويتحمل مقدمو الخدمات مسؤولية إبقاء قوائمهم دقيقة ومحدثة بصورة معقولة.</p>
<h2>الحجوزات</h2><p>لا يعني الطلب أو المسودة بالضرورة أن الحجز أصبح مؤكداً. وتوضح الحالة المعروضة في حساب العميل المرحلة الحالية. ينبغي مراجعة التفاصيل النهائية للخدمة ومعلومات المناسبة ورسائل مقدم الخدمة قبل التأكيد.</p>
<h2>الأسعار والمدفوعات</h2><p>تظهر الأسعار والعملة والرسوم وطرق الدفع المتاحة ضمن مسار الحجز المعني. وقد يتولى مزود دفع خارجي معالجة الدفع وفق شروط خدمته.</p>
<h2>التعديلات والإلغاء</h2><p>قد تعتمد إمكانات التعديل والإلغاء ومعالجة الاسترداد على الخدمة ومقدمها ومرحلة الحجز والشروط المعروضة قبل التأكيد. وعلى المستخدم مراجعة المعلومات المطبقة قبل الالتزام بالحجز.</p>
<h2>مسؤوليات المستخدم</h2><ul><li>تقديم بيانات دقيقة عن المناسبة والتواصل.</li><li>مراجعة معلومات الحجز والرد على التحديثات اللازمة.</li><li>التعامل باحترام مع مقدمي الخدمات وفريق الدعم.</li><li>استخدام خصائص التواصل والدفع في المنصة بمسؤولية.</li></ul>
<h2>مسؤوليات مقدم الخدمة</h2><ul><li>وصف الخدمات والتوفر بدقة.</li><li>الإبلاغ سريعاً عن التغييرات المؤثرة.</li><li>تنفيذ الخدمات المؤكدة وفق التفاصيل المتفق عليها.</li><li>اتباع متطلبات السوق والسلامة والحساب.</li></ul>
<h2>الاستخدام غير المسموح</h2><p>يُحظر إساءة استخدام الحسابات أو محاولة الوصول دون تصريح أو نشر مواد غير مشروعة أو مضللة أو تعطيل تشغيل المنصة أو الإساءة للآخرين أو استخدام السوق لتسهيل نشاط محظور.</p>
<h2>الملكية الفكرية</h2><p>تعود واجهة المنصة وأصول العلامة والمواد الأصلية إلى أصحابها المعنيين. ويظل مقدم الخدمة أو المستخدم مسؤولاً عن امتلاك الحقوق اللازمة للمحتوى الذي يرفعه أو ينشره.</p>
<h2>توفر المنصة</h2><p>يعمل الفريق على إبقاء إنستابارتي متاحة ومفيدة، لكن أعمال الصيانة أو انقطاع خدمات المزودين أو الأحداث الأمنية أو المشكلات التقنية قد تؤثر أحياناً في الوصول أو بعض الخصائص.</p>
<h2>تعديل الشروط</h2><p>قد تتغير هذه الشروط مع تطور السوق وخدماته. وستتوفر النسخة المنشورة الحالية في هذه الصفحة، ويخضع استمرار الاستخدام لتلك النسخة.</p>
<h2>التواصل</h2><p>يمكن إرسال الأسئلة عن هذه الشروط أو عن حجز محدد عبر خيارات الدعم المنشورة في صفحة تواصل معنا على إنستابارتي.</p>
HTML,
                ],
            ],
        ];
    }
}
