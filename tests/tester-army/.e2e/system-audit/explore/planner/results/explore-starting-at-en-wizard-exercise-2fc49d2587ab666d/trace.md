# ✓ Starting at /en/wizard, exercise the event planner occasion, future dates and location steps, test an end before the st…

`explore` · target `desktop` · passed · 1m 54s

Ran on: browser `chromium 153.0.8010.12` · user agent `Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/153.0.8010.12 Safari/537.36`

## Steps

1. ✓ `app.open` `/` (7.6s)
   - screen: 126 nodes at `/en`
2. ✓ `agent.extract` "Plan the next step of an exploratory test run, or finish it. Goal: Starting at /en/wizard, exercise the event planner occasion, future dates and location steps, test an end before the start and a past start, and inspect Arabic equivalents …" (3.8s, 1 model call)
   - screen: unchanged since step 1 at `/en`
3. ✓ `agent.act` "Open /en/wizard and exercise the event planner without submitting a booking. Select the available occasion, set a future start and end date, choose a governorate and location, and proceed through the location step. Then try an end date bef…" (1m 5s, 15 model calls)
   - navigate to "/en/wizard" (3.7s)
   - tap node "Location" in "2" (132ms)
   - tap radio "Party Dolorem aut quibusdam architecto …" in "Occasion" (122ms)
   - scroll down (146ms)
   - type "2027-08-15T18:00" into textbox "Starts at" in "Occasion" (65ms)
   - type "2027-08-16T18:00" into textbox "Ends at (optional)" in "Occasion" (85ms)
   - tap button "Next" in "Occasion" (123ms)
   - select "Cairo" in combobox "Governorate" in "Location" (140ms)
   - tap button "Next" in "Location" (70ms)
   - tool:report_finding (1.0s)
   - navigate to "/ar/wizard" (980ms)
   - tap radio "حفلة Quis aut dicta suscipit ipsum." in "المناسبة" (89ms)
   - [screenshot](attempt-1/finding-1.png) `.e2e/system-audit/explore/planner/results/explore-starting-at-en-wizard-exercise-2fc49d2587ab666d/attempt-1/finding-1.png`
   - screen: 176 changes since step 2 at `/ar/wizard`, 63 nodes
     - `changed document "خطّط لمناسبتك · إنستابارتي" (was: document "InstaParty · Find vendors for your next celebration, all in one place.")`
     - `added link "انتقل إلى المحتوى الرئيسي" href="/ar/wizard#…"`
     - `added link "InstaParty" href="/ar"`
     - `added navigation "English"`
     - `added link "English" href="/en/wizard"`
     - `added link "العربية" href="/ar/wizard"`
     - `added link "بحث" href="/ar/search"`
     - `added link "السلة" href="/ar/cart"`
     - `added link "تسجيل الدخول" href="/ar/auth/login"`
     - `added text="خطّط لتفاصيل مناسبتك"`
     - `added heading "خطّط لمناسبتك"`
     - `added text="أجب عن بعض الأسئلة وسنريك ما هو متاح."`
     - 164 more
4. ✓ `agent.extract` "Plan the next step of an exploratory test run, or finish it. Goal: Starting at /en/wizard, exercise the event planner occasion, future dates and location steps, test an end before the start and a past start, and inspect Arabic equivalents …" (4.4s, 1 model call)
   - screen: unchanged since step 3 at `/ar/wizard`
5. ✗ `agent.act` "On the Arabic wizard occasion step, use the date controls to test validation without submitting a booking. Enter a future start date and an end date earlier than that start, then activate التالي and record whether the wizard advances or sh…" (17.3s, 5 model calls) — **ACTION_FAILED**
   > Arabic occasion step is visible, but the date inputs would not accept either an ISO date or a locale-formatted date, returning a malformed-value action error and leaving the fields unchanged. I could not test end-before-start or past-start transitions; the wizard did not advance and no booking was submitted.
   - ✗ type: elementHandle.fill: Error: Malformed value: **ENGINE_FAILURE**
   - ⚠ console warning: `The specified value "2027-08-20" does not conform to the required format. The format is "yyyy-MM-ddThh:mm" followed by optional ":ss" or ":ss.SSS".`
   - ✗ type: elementHandle.fill: Error: Malformed value: **ENGINE_FAILURE**
   - ⚠ console warning: `The specified value "08/20/2027" does not conform to the required format. The format is "yyyy-MM-ddThh:mm" followed by optional ":ss" or ":ss.SSS".`
   - tap textbox "تبدأ في" in "المناسبة" (89ms)
   - screen: unchanged since step 4 at `/ar/wizard`
6. ✓ `agent.extract` "Plan the next step of an exploratory test run, or finish it. Goal: Starting at /en/wizard, exercise the event planner occasion, future dates and location steps, test an end before the start and a past start, and inspect Arabic equivalents …" (5.3s, 1 model call)
   - screen: unchanged since step 5 at `/ar/wizard`
7. ✗ `agent.act` "Continue the Arabic wizard from the occasion screen. Keep the existing Party selection, leave the date fields unchanged because date entry was rejected, and activate التالي. Inspect the Arabic location controls, try selecting a governorate…" (5.6s, 2 model calls) — **ASSERTION_FAILED**
   > Activated التالي on the Arabic occasion screen while keeping Party selected and date fields unchanged. The wizard did not reach location; it showed the alert «أضف وقت بداية المناسبة.» (add an event start time), so Arabic location controls and their validation could not be inspected here. No booking was submitted.
   - tap button "التالي" in "المناسبة" (74ms)
   - screen: 1 change since step 6 at `/ar/wizard`, 64 nodes
     - `added alert "أضف وقت بداية المناسبة."`
8. ✓ `agent.extract` "Finish an exploratory test run with your overall assessment. Goal: Starting at /en/wizard, exercise the event planner occasion, future dates and location steps, test an end before the start and a past start, and inspect Arabic equivalents …" (3.9s, 1 model call)
   - screen: unchanged since step 7 at `/ar/wizard`

## App log

What the app logged, oldest first, with the step it happened in.

- step 5 · ⚠ console warning: `The specified value "2027-08-20" does not conform to the required format. The format is "yyyy-MM-ddThh:mm" followed by optional ":ss" or ":ss.SSS".`
- step 5 · ⚠ console warning: `The specified value "08/20/2027" does not conform to the required format. The format is "yyyy-MM-ddThh:mm" followed by optional ":ss" or ":ss.SSS".`

## Agent turns of step 5

The last 5 turns the model took, oldest first: what it called and what came back.

**Turn 1** `type({"target":"n153036","value":"2027-08-20"})`  
> \[type\] type #n153036 failed: APP_UNREACHABLE: elementHandle.fill: Error: Malformed value
> Call log:
> \- fill("2027-08-20")
> \- attempting fill action
> \- waiting for element to be visible, enabled and editable
> 
> 
> Screen unchanged since revision b48 (re-observed as revision b50, path /ar/wizard); the ids you have stay valid.

**Turn 2** `screenshot({})`  
> \[screenshot\] Screen unchanged since revision b50 (re-observed as revision b52, path /ar/wizard); the ids you have stay valid.
> 
> Screenshot attached: 768 by 480 pixels (0.5333333333333333 per CSS pixel). Point coordinates (tap_at and the other _at verbs) are pixels of this image: x from the left edge, y from the top edge.
> \[screenshot, 39338 bytes\]

**Turn 3** `type_at({"x":506,"y":242,"value":"08/20/2027","replace":true})`  
> \[type_at\] type_at (506, 242) failed: APP_UNREACHABLE: elementHandle.fill: Error: Malformed value
> Call log:
> \- fill("08/20/2027")
> \- attempting fill action
> \- waiting for element to be visible, enabled and editable
> 
> 
> Screen unchanged since revision b52 (re-observed as revision b54, path /ar/wizard); the ids you have stay valid.
> 
> Screenshot attached: 768 by 480 pixels (0.5333333333333333 per CSS pixel). Point coordinates (tap_at and the other _at verbs) are pixels of this image: x from the left edge, y from the top edge.
> \[screenshot, 39338 bytes\]

**Turn 4** `tap({"target":"n153036"})`  
> \[tap\] Tapped #n153036.
> 
> The screen did not change within the wait after this action (re-observed as revision b62, path /ar/wizard); the ids you have stay valid. If a change was expected, the control had no visible effect here: look for another way rather than rep…
> 
> Screenshot attached: 768 by 480 pixels (0.5333333333333333 per CSS pixel). Point coordinates (tap_at and the other _at verbs) are pixels of this image: x from the left edge, y from the top edge.
> \[screenshot, 39338 bytes\]

**Turn 5** `complete_step({"status":"failed","summary":"Arabic occasion step is visible, but the date inputs would not accept either an ISO date or a locale-formatted date, returning a malformed-value action erro…[truncated]`  
> \[complete_step\] Step concluded.

## Evidence

- [screenshot](attempt-1/finding-1.png) `.e2e/system-audit/explore/planner/results/explore-starting-at-en-wizard-exercise-2fc49d2587ab666d/attempt-1/finding-1.png`

<sub>e2e 0.19.0 · run `01a13155-9de7-73fc-9fd6-72aff1f4bd90` · the whole run is in `report.json`</sub>
