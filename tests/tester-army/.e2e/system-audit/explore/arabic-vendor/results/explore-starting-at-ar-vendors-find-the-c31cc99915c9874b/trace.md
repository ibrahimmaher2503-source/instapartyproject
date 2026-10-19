# ✓ Starting at /ar/vendors, find the seeded vendor, open its profile, inspect governorate labels and the rental/sale/digit…

`explore` · target `desktop` · passed · 1m 21s

Ran on: browser `chromium 153.0.8010.12` · user agent `Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/153.0.8010.12 Safari/537.36`

## Steps

1. ✓ `app.open` `/` (7.6s)
   - screen: 126 nodes at `/en`
2. ✓ `agent.extract` "Plan the next step of an exploratory test run, or finish it. Goal: Starting at /ar/vendors, find the seeded vendor, open its profile, inspect governorate labels and the rental/sale/digital filter; verify each result belongs to this vendor …" (4.8s, 1 model call)
   - screen: unchanged since step 1 at `/en`
3. ✓ `agent.act` "Switch to Arabic using the العربية link, then open the vendors directory at /ar/vendors. Find E2E Celebration Vendor and open its profile. Inspect the governorate labels and rental/sale/digital filter; interact with each filter option, che…" (33.8s, 7 model calls)
   - tap link "العربية" (1 of 2) (3.4s)
   - ↪ navigated to /ar
   - navigate to "/ar/vendors" (1.7s)
   - tap link "عرض الملف: مورد احتفالات تجريبي" in "م" (2.2s)
   - ↪ navigated to /ar/vendors/feest-ltd-sony3b
   - tap link "تأجير" (639ms)
   - ↪ navigated to /ar/vendors/feest-ltd-sony3b?type=rental#vendor-services
   - tap link "بيع" (764ms)
   - ↪ navigated to /ar/vendors/feest-ltd-sony3b?type=sale#vendor-services
   - tap link "رقمي" (1.3s)
   - ↪ navigated to /ar/vendors/feest-ltd-sony3b?type=digital#vendor-services
   - screen: 177 changes since step 2 at `/ar/vendors/feest-ltd-sony3b?type=digital#vendor-services`, 68 nodes
     - `changed document "مورد احتفالات تجريبي · إنستابارتي" (was: document "InstaParty · Find vendors for your next celebration, all in one place.")`
     - `added link "انتقل إلى المحتوى الرئيسي" href="/ar/vendors/feest-ltd-sony3b?…#…"`
     - `added link "InstaParty" href="/ar"`
     - `added navigation "English"`
     - `added link "English" href="/en/vendors/feest-ltd-sony3b?…"`
     - `added link "العربية" href="/ar/vendors/feest-ltd-sony3b?…"`
     - `added link "بحث" href="/ar/search"`
     - `added link "السلة" href="/ar/cart"`
     - `added link "تسجيل الدخول" href="/ar/auth/login"`
     - `added box`
     - `added link "تصفّح كل البائعين" href="/ar/vendors"`
     - `added text="م"`
     - 165 more
4. ✓ `agent.extract` "Plan the next step of an exploratory test run, or finish it. Goal: Starting at /ar/vendors, find the seeded vendor, open its profile, inspect governorate labels and the rental/sale/digital filter; verify each result belongs to this vendor …" (7.2s, 1 model call)
   - screen: unchanged since step 3 at `/ar/vendors/feest-ltd-sony3b?type=digital#vendor-services`
5. ✓ `agent.act` "On the current Arabic vendor profile, inspect the screenshot for RTL layout issues in the profile heading, governorate label, service search, and service card; report only reproducible visual inconsistencies. Interact with the vendor servi…" (23.2s, 5 model calls)
   - type "دعوة" into searchbox "ابحث في خدمات هذا البائع…" in "خدمات هذا البائع" (82ms)
   - tap button "بحث" in "خدمات هذا البائع" (2.3s)
   - ↪ navigated to /ar/vendors/feest-ltd-sony3b?type=digital&q=%D8%AF%D8%B9%D9%88%D8%A9#vendor-services
   - tap link "مسح جميع الفلاتر" in "خدمات هذا البائع" (644ms)
   - ↪ navigated to /ar/vendors/feest-ltd-sony3b#vendor-services
   - screen: 26 changes since step 4 at `/ar/vendors/feest-ltd-sony3b#vendor-services`, 84 nodes
     - `added link "انتقل إلى المحتوى الرئيسي" href="/ar/vendors/feest-ltd-sony3b#…"`
     - `added link "English" href="/en/vendors/feest-ltd-sony3b"`
     - `added link "العربية" href="/ar/vendors/feest-ltd-sony3b"`
     - `added article`
     - `added link "زينة حفلات تجريبية بيع et molestiae زينة حفلات تجريبية ابتداءً من ‏125.00 ج.م.‏" href="/ar/services/01M4RNA5V9VDVSK5K10YK3FT35"`
     - `added image "زينة حفلات تجريبية"`
     - `added text="بيع"`
     - `added text="et molestiae"`
     - `added heading "زينة حفلات تجريبية"`
     - `added text="ابتداءً من"`
     - `added text="‏125.00 ج.م.‏"`
     - `added article`
     - 14 more
6. ✓ `agent.extract` "Plan the next step of an exploratory test run, or finish it. Goal: Starting at /ar/vendors, find the seeded vendor, open its profile, inspect governorate labels and the rental/sale/digital filter; verify each result belongs to this vendor …" (3.9s, 1 model call)
   - screen: unchanged since step 5 at `/ar/vendors/feest-ltd-sony3b#vendor-services`

<sub>e2e 0.19.0 · run `01a13155-9dd9-70af-9734-664df4a37a38` · the whole run is in `report.json`</sub>
