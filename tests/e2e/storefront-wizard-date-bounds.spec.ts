import { expect, test } from '@playwright/test';

test.use({ timezoneId: 'America/Los_Angeles' });

test('wizard date bounds use the viewer timezone and keep the end after the start in English and Arabic', async ({ page }) => {
    for (const locale of ['en', 'ar'] as const) {
        await page.goto(`/${locale}/wizard`);

        const timezone = await page.locator('[name="timezone"]').inputValue();
        const startsAt = page.locator('[name="event_starts_at"]');
        const endsAt = page.locator('[name="event_ends_at"]');
        const minimum = await startsAt.getAttribute('min');
        expect(minimum).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/);

        const expectedMinimum = await page.evaluate((timeZone) => {
            const parts = new Intl.DateTimeFormat('en', {
                timeZone,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hourCycle: 'h23',
            }).formatToParts(new Date(Date.now() + 60_000));
            const values = Object.fromEntries(parts.map(({ type, value }) => [type, value]));

            return `${values.year}-${values.month}-${values.day}T${values.hour}:${values.minute}`;
        }, timezone);
        expect(minimum).toBe(expectedMinimum);

        const futureStart = await page.evaluate((value) => {
            const date = new Date(`${value}:00Z`);
            date.setUTCHours(date.getUTCHours() + 1);

            return date.toISOString().slice(0, 16);
        }, minimum);
        await startsAt.fill(futureStart);

        const expectedEndMinimum = await page.evaluate((value) => {
            const date = new Date(`${value}:00Z`);
            date.setUTCMinutes(date.getUTCMinutes() + 1);

            return date.toISOString().slice(0, 16);
        }, futureStart);
        await expect(endsAt).toHaveAttribute('min', expectedEndMinimum);
        await endsAt.fill(futureStart);
        expect(await endsAt.evaluate((input: HTMLInputElement) => input.validity.rangeUnderflow)).toBe(true);
    }
});

test('returns focus to the date that expires while the wizard is on details', async ({ page }) => {
    await page.route('**/api/v1/customer/governorates', (route) => route.fulfill({ json: { data: [] } }));
    await page.goto('/en/wizard');

    const startsAt = page.locator('[name="event_starts_at"]');
    const minimum = await startsAt.getAttribute('min');
    expect(minimum).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/);
    const futureStart = await page.evaluate((value) => {
        const date = new Date(`${value}:00Z`);
        date.setUTCHours(date.getUTCHours() + 1);

        return date.toISOString().slice(0, 16);
    }, minimum ?? '');
    await startsAt.fill(futureStart);
    await page.locator('[data-planner-occasion]').evaluate((input: HTMLInputElement) => {
        input.value = 'test-occasion';
    });
    await page.locator('[data-planner-next]').nth(0).click();
    await expect(page.locator('[data-planner-loading]')).toBeHidden();

    await page.locator('[data-planner-governorate]').evaluate((select: HTMLSelectElement) => {
        select.add(new Option('Test governorate', 'test-governorate'));
        select.value = 'test-governorate';
        document.querySelector<HTMLInputElement>('[data-planner-city]')!.value = 'test-city';
    });
    await page.locator('[data-planner-next]').nth(1).click();

    const submissionPrevented = await page.locator('[data-planner-form]').evaluate((form: HTMLFormElement, value) => {
        Date.now = () => new Date(`${value}:00Z`).valueOf() + 60_000;
        const event = new Event('submit', { bubbles: true, cancelable: true });
        form.dispatchEvent(event);

        return event.defaultPrevented;
    }, futureStart);

    expect(submissionPrevented).toBe(true);
    await expect(page.locator('[data-planner-panel="0"]')).toBeVisible();
    await expect(page.locator('[data-planner-error]')).toBeVisible();
    expect(await startsAt.evaluate((input: HTMLInputElement) => input === document.activeElement)).toBe(true);
});
