<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Shared\Domain\Enums\CmsSlug;
use App\Modules\Shared\Domain\Enums\NavigationSlot;
use App\Modules\Shared\Domain\Enums\NavigationTargetType;
use App\Modules\Shared\Domain\Models\CmsPage;
use App\Modules\Shared\Domain\Models\NavigationMenu;
use App\Modules\Shared\Domain\Models\NavigationMenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RefundPolicyCmsPageSeeder extends Seeder
{
    public function run(): void
    {
        CmsPage::query()->firstOrCreate(
            ['slug' => CmsSlug::RefundPolicy->value],
            [
                'public_id' => (string) Str::ulid(),
                'title' => [
                    'en' => 'Refunds and cancellations',
                    'ar' => 'سياسة الاسترداد والإلغاء',
                ],
                'meta_description' => [
                    'en' => 'How refunds and cancellations work for purchased goods, rentals, booked services, and digital items.',
                    'ar' => 'معلومات الاسترداد والإلغاء للسلع المشتراة والإيجارات والخدمات المحجوزة والمنتجات الرقمية.',
                ],
                // shortcut: summarizes current automatic eligibility checks; keep aligned with RefundPolicyService and preserve statutory rights.
                'body' => [
                    'en' => <<<'HTML'
<h2>Scope and your rights</h2>
<p>This page explains how to request cancellations and refunds through InstaParty. It does not replace mandatory rights under Egyptian law or the terms accepted for a specific item or booking. Where mandatory law gives you a right or remedy that conflicts with a platform or vendor term, the law applies.</p>
<h2>Goods you purchase</h2>
<p>The Egyptian Consumer Protection Agency describes a 14-day return or exchange period after receipt without a reason, subject to exceptions, and a 30-day period for defective goods. For an eligible defect, the supplier refunds without additional cost through the original payment method within one week of the request. Its listed exceptions include perishable, qualifying custom-made, and consumer-altered goods; each applies only under its legal conditions.</p>
<p><a href="https://cpa.gov.eg/ar-eg/%D8%A8%D9%8A%D8%A7%D9%86%D8%A7%D8%AA-%D8%A7%D8%B9%D9%84%D8%A7%D9%85%D9%8A%D8%A9/ArtMID/654/ArticleID/6790">Read the Consumer Protection Agency's official exchange and return guidance.</a></p>
<h2>Rentals and booked services</h2>
<p>The goods guidance above concerns purchases. For a rental or booked service, review the confirmed details, event date, and cancellation terms before paying; mandatory legal rights still apply. Use the cancellation option in Your Event when it is available. Cancellation and refund are separate outcomes. The accepted terms, product stage, event timing where relevant, and applicable law determine any refund. Your Event may show an estimated refund before you confirm cancellation; it is only an estimate, not proof of approval or completion. An unpaid booking has no payment refund to process. Contact support if your account says review is needed or the refund is not shown as completed. A booking marked cancelled is not proof that a refund is complete.</p>
<h2>Digital items and services</h2>
<p>Before buying, review the listing's delivery and refund information. Some software may meet a legal exception; that does not exclude all digital products or services.</p>
<h2>Request help and check status</h2>
<p>For a cancellation or refund request, a vendor cancellation, or a service issue, contact InstaParty through the <a href="/en/p/contact">Contact Us page</a>. Include the booking or order reference, event or delivery date, amount, payment reference if available, and a short explanation. A request is not an approval. Your Event and Payments show cancellation and refund status separately; rely on a completed refund status, not a cancellation or account credit alone, to confirm a refund.</p>
HTML,
                    'ar' => <<<'HTML'
<h2>النطاق وحقوقك</h2>
<p>توضح هذه الصفحة كيفية طلب الإلغاء والاسترداد عبر إنستابارتي. ولا تحل محل الحقوق الإلزامية بموجب القانون المصري أو الشروط المقبولة لمنتج أو حجز محدد. وعند التعارض مع شرط للمنصة أو مقدم الخدمة، تسود الحقوق القانونية الإلزامية.</p>
<h2>السلع المشتراة</h2>
<p>يذكر جهاز حماية المستهلك مهلة 14 يوماً بعد استلام السلعة للاستبدال أو الرد دون سبب، مع استثناءات، ومهلة 30 يوماً للسلع المعيبة. وفي الحالة المؤهلة، يرد المورد المبلغ دون تكلفة إضافية وبطريقة الدفع الأصلية خلال أسبوع من الطلب. وتشمل استثناءاته السلع سريعة التلف أو المصنوعة وفق مواصفات المستهلك أو التي تغيرت حالتها بسببه؛ ولا يطبق أي استثناء إلا بشروطه القانونية.</p>
<p><a href="https://cpa.gov.eg/ar-eg/%D8%A8%D9%8A%D8%A7%D9%86%D8%A7%D8%AA-%D8%A7%D8%B9%D9%84%D8%A7%D9%85%D9%8A%D8%A9/ArtMID/654/ArticleID/6790">اقرأ إرشادات جهاز حماية المستهلك بشأن الاستبدال والاسترجاع.</a></p>
<h2>الإيجارات والخدمات المحجوزة</h2>
<p>تخص الإرشادات أعلاه السلع المشتراة. بالنسبة للإيجار أو الخدمة المحجوزة، راجع التفاصيل المؤكدة وتاريخ المناسبة وشروط الإلغاء قبل الدفع؛ وتظل الحقوق القانونية الإلزامية سارية. استخدم خيار الإلغاء في «مناسبتك» متى كان متاحاً. الإلغاء والاسترداد نتيجتان منفصلتان. وتتحدد أهلية الاسترداد وفق الشروط المقبولة ومرحلة المنتج وتوقيت المناسبة عند انطباقه والقانون المطبق. قد يعرض حسابك مبلغاً تقديرياً قبل تأكيد الإلغاء؛ وهو تقدير لا يثبت الموافقة أو اكتمال الاسترداد. لا يوجد مبلغ مدفوع لاسترداده في الحجز غير المدفوع. تواصل مع الدعم إذا أشار حسابك إلى ضرورة المراجعة أو لم يظهر الاسترداد مكتملاً. ظهور الحجز بحالة «ملغى» لا يثبت اكتمال الاسترداد.</p>
<h2>المنتجات والخدمات الرقمية</h2>
<p>راجع معلومات التسليم والاسترداد في صفحة المنتج قبل الشراء. وقد يشمل الاستثناء بعض البرامج؛ ولا يعني ذلك استبعاد جميع المنتجات أو الخدمات الرقمية.</p>
<h2>طلب المساعدة ومتابعة الحالة</h2>
<p>لطلب الإلغاء أو الاسترداد أو الإبلاغ عن إلغاء من مقدم الخدمة أو مشكلة في الخدمة، تواصل مع إنستابارتي من خلال <a href="/ar/p/contact">صفحة اتصل بنا</a>. أرسل مرجع الحجز أو الطلب، وتاريخ المناسبة أو التسليم، والمبلغ، ومرجع الدفع إن توفر، مع شرح مختصر. تقديم الطلب لا يعني الموافقة عليه. يعرض حسابك حالة الإلغاء والاسترداد كلّاً على حدة؛ اعتمد حالة الاسترداد المكتملة، ولا تعتبر الإلغاء أو رصيد الحساب وحده دليلاً على إتمامه.</p>
HTML,
                ],
                'is_published' => true,
                'published_at' => now(),
            ],
        );

        $page = CmsPage::query()
            ->published()
            ->where('slug', CmsSlug::RefundPolicy->value)
            ->first();
        $menu = NavigationMenu::query()->where('slot', NavigationSlot::FooterSecondary->value)->first();

        if ($page === null || $menu === null) {
            return;
        }

        if (NavigationMenuItem::query()
            ->where('menu_id', $menu->id)
            ->where('target_value', CmsSlug::RefundPolicy->value)
            ->exists()) {
            return;
        }

        $position = (int) $menu->items()->whereNull('parent_id')->max('position') + 1;

        $menu->items()->create([
            'public_id' => (string) Str::ulid(),
            'parent_id' => null,
            'label' => [
                'en' => 'Refunds and cancellations',
                'ar' => 'الاسترداد والإلغاء',
            ],
            'target_type' => NavigationTargetType::CmsPage,
            'target_value' => CmsSlug::RefundPolicy->value,
            'is_visible' => true,
            'opens_in_new_tab' => false,
            'position' => $position,
        ]);

        Cache::forget('theme:menus:footer_secondary:en');
        Cache::forget('theme:menus:footer_secondary:ar');
    }
}
