<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Shared\Domain\Enums\CmsSlug;
use App\Modules\Shared\Domain\Models\CmsPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CmsPagesSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug' => CmsSlug::Terms,
                'title' => ['en' => 'Terms & Conditions', 'ar' => 'الشروط والأحكام'],
                'meta_description' => ['en' => 'The clear rules that keep bookings fair for customers and vendors.', 'ar' => 'القواعد الواضحة التي تحافظ على عدالة الحجوزات للعملاء والبائعين.'],
                'body' => [
                    'en' => "Using InstaParty\n\nInstaParty connects customers with independent event vendors. Service details, availability, and fulfilment remain the responsibility of each vendor.\n\nBookings and payments\n\nA booking is confirmed according to the status shown in Your Event. Prices are displayed in EGP and payments are processed securely through the configured provider.\n\nCancellations and changes\n\nTerms may vary by service and booking stage. The applicable terms are shown before confirmation.",
                    'ar' => "استخدام إنستابارتي\n\nيربط إنستابارتي العملاء ببائعي خدمات المناسبات المستقلين، ويتحمل كل بائع مسؤولية تفاصيل خدمته وتوفرها وتنفيذها.\n\nالحجوزات والمدفوعات\n\nيتم تأكيد الحجز وفق الحالة الظاهرة في مناسبتك. تعرض الأسعار بالجنيه المصري وتعالج المدفوعات بأمان عبر مزود الدفع المعتمد.\n\nالإلغاء والتعديلات\n\nقد تختلف الشروط حسب الخدمة ومرحلة الحجز، وتظهر الشروط المطبقة قبل التأكيد.",
                ],
            ],
            [
                'slug' => CmsSlug::Privacy,
                'title' => ['en' => 'Privacy Policy', 'ar' => 'سياسة الخصوصية'],
                'meta_description' => ['en' => 'How InstaParty handles account, booking, and communication data.', 'ar' => 'كيف يتعامل إنستابارتي مع بيانات الحساب والحجز والتواصل.'],
                'body' => [
                    'en' => "Information we use\n\nWe use the details needed to operate your account, arrange bookings, process payments, and support you.\n\nWho receives booking details\n\nRelevant event and contact details are shared only with involved vendors and providers needed to complete payment or communication.\n\nYour choices\n\nYou can update account information and request help through our support channels.",
                    'ar' => "المعلومات التي نستخدمها\n\nنستخدم البيانات اللازمة لتشغيل حسابك وتنظيم الحجوزات ومعالجة المدفوعات وتقديم الدعم.\n\nمن يستلم تفاصيل الحجز\n\nتشارك تفاصيل المناسبة والتواصل ذات الصلة فقط مع البائعين المشاركين ومزودي الخدمات اللازمة للدفع أو التواصل.\n\nخياراتك\n\nيمكنك تحديث معلومات حسابك وطلب المساعدة عبر قنوات الدعم.",
                ],
            ],
            [
                'slug' => CmsSlug::About,
                'title' => ['en' => 'About InstaParty', 'ar' => 'عن إنستابارتي'],
                'meta_description' => ['en' => 'A simpler, more trustworthy way to plan celebrations across Egypt.', 'ar' => 'طريقة أبسط وأكثر ثقة لتخطيط المناسبات في مصر.'],
                'body' => [
                    'en' => "Celebrations should feel joyful from the first idea.\n\nInstaParty brings trusted event vendors, clear service details, and one organised booking flow together. Families can compare the people behind each service, build an event around their date and city, and keep every update in one place.\n\nWe are building from Benha for celebrations across Egypt, with Arabic-first support and an experience designed for how local customers and vendors actually work.",
                    'ar' => "يجب أن تبدأ فرحة المناسبة من لحظة الفكرة الأولى.\n\nيجمع إنستابارتي بائعي خدمات المناسبات الموثوقين وتفاصيل الخدمات الواضحة ومسار الحجز المنظم في مكان واحد. يمكن للعائلات المقارنة وبناء مناسبتهم حسب التاريخ والمدينة ومتابعة كل تحديث بسهولة.\n\nنبني المنصة من بنها لخدمة المناسبات في أنحاء مصر، بدعم عربي أساسي وتجربة تناسب طريقة عمل العملاء والبائعين محلياً.",
                ],
            ],
            [
                'slug' => CmsSlug::Contact,
                'title' => ['en' => 'Contact Us', 'ar' => 'اتصل بنا'],
                'meta_description' => ['en' => 'Questions about an event, booking, or vendor application? We are here.', 'ar' => 'لديك سؤال عن مناسبة أو حجز أو طلب بائع؟ نحن هنا للمساعدة.'],
                'body' => [
                    'en' => "Customer support\n\nsupport@instaparty.local\n+20 100 000 0000\n\nVendor applications\n\nUse the Join as vendor page to start your application. The team will review your details and show the next step in the vendor portal.\n\nLocation\n\nBenha, Qalyubia, Egypt",
                    'ar' => "دعم العملاء\n\nsupport@instaparty.local\n+20 100 000 0000\n\nطلبات البائعين\n\nاستخدم صفحة الانضمام كبائع لبدء طلبك. سيراجع الفريق بياناتك ويعرض الخطوة التالية في بوابة البائع.\n\nالموقع\n\nبنها، القليوبية، مصر",
                ],
            ],
        ];

        foreach ($pages as $entry) {
            CmsPage::query()->firstOrCreate(
                ['slug' => $entry['slug']->value],
                [
                    'public_id' => (string) Str::ulid(),
                    'title' => $entry['title'],
                    'body' => $entry['body'],
                    'meta_description' => $entry['meta_description'],
                    'is_published' => true,
                    'published_at' => now(),
                ],
            );
        }
    }
}
