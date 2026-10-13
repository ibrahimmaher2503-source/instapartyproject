document.querySelectorAll('[data-booking-api-form]').forEach((form) => {
    if (!(form instanceof HTMLFormElement)) return;
    const feedback = form.querySelector('[data-booking-feedback]');
    const submit = form.querySelector('button[type="submit"]');
    if (!(feedback instanceof HTMLElement) || !(submit instanceof HTMLButtonElement)) return;

    const headers = {
        Accept: 'application/json',
        'Accept-Language': document.documentElement.lang,
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
    };
    let requestKey;
    let requestBody;
    const request = async (url, options = {}) => {
        const response = await fetch(url, { credentials: 'same-origin', headers, ...options });
        const payload = await response.json();
        if (!response.ok) {
            const errors = payload.errors ?? [];
            const message = errors.message ?? (Array.isArray(errors) ? errors.map((error) => typeof error === 'string' ? error : error.message).filter(Boolean).join(' ') : Object.values(errors).flat().join(' '));
            throw new Error(message || payload.message || form.dataset.error);
        }
        return payload.data;
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (submit.disabled) return;
        submit.disabled = true;
        form.setAttribute('aria-busy', 'true');
        feedback.textContent = '';

        try {
            if (form.dataset.preview) {
                const preview = await request(form.dataset.preview);
                if (!preview.cancellable) throw new Error(form.dataset.blocked);
                const amount = new Intl.NumberFormat(document.documentElement.lang, { style: 'currency', currency: preview.currency }).format(preview.estimated_refund_minor / 100);
                const summary = form.dataset.confirm.replace(':amount', amount);
                const reasons = preview.items.map((item) => `${item.name[document.documentElement.lang] ?? item.name.en}: ${item.reason_message}`).join('\n');
                feedback.textContent = [summary, reasons, preview.requires_manual_refund_review ? form.dataset.manualReview : ''].filter(Boolean).join('\n');
                if (!window.confirm(feedback.textContent)) return;
            }

            const body = JSON.stringify(Object.fromEntries(new FormData(form)));
            if (requestBody !== body) { requestKey = crypto.randomUUID(); requestBody = body; }
            const data = await request(form.action, { method: 'POST', headers: { ...headers, 'Idempotency-Key': requestKey }, body });
            if (form.dataset.payment !== undefined) {
                if (!data?.redirect_url) throw new Error(form.dataset.error);
                const redirect = new URL(data.redirect_url, window.location.origin);
                if (!['https:', 'http:'].includes(redirect.protocol)) throw new Error(form.dataset.error);
                window.location.assign(redirect.href);
            } else {
                window.location.reload();
            }
        } catch (error) {
            feedback.textContent = error instanceof Error ? error.message : form.dataset.error;
        } finally {
            submit.disabled = false;
            form.removeAttribute('aria-busy');
        }
    });
});
