import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/booking-actions.js', import.meta.url), 'utf8');

function harness({ dataset = {}, responses = [], confirm = true } = {}) {
    class HTMLElement {}
    class HTMLButtonElement extends HTMLElement {}
    class HTMLFormElement extends HTMLElement {}
    const feedback = new HTMLElement();
    const submit = new HTMLButtonElement();
    const form = new HTMLFormElement();
    form.action = 'https://example.test/api/action';
    form.dataset = { error: 'Action failed', ...dataset };
    form.fields = { method: 'card' };
    form.querySelector = (selector) => selector === '[data-booking-feedback]' ? feedback : submit;
    form.setAttribute = () => {};
    form.removeAttribute = () => {};
    form.addEventListener = (_, callback) => { form.onSubmit = callback; };
    const calls = [];
    const redirects = [];
    let reloads = 0;
    let keys = 0;
    vm.runInNewContext(source, {
        HTMLElement, HTMLButtonElement, HTMLFormElement, Error, URL, Intl,
        FormData: class { constructor() { return Object.entries(form.fields); } },
        crypto: { randomUUID: () => 'request-' + ++keys },
        document: {
            documentElement: { lang: 'en' },
            querySelectorAll: () => [form],
            querySelector: () => ({ content: 'csrf-test-token' }),
        },
        window: {
            confirm: () => confirm,
            location: { origin: 'https://example.test', assign: (url) => redirects.push(url), reload: () => reloads++ },
        },
        fetch: async (url, options) => {
            calls.push({ url, options });
            const response = responses.shift();
            if (response instanceof Error) throw response;
            return { ok: response.ok ?? true, json: async () => response.payload };
        },
    });
    return { form, feedback, submit, calls, redirects, reloads: () => reloads, send: () => form.onSubmit({ preventDefault() {} }) };
}

test('payment submits the CSRF and idempotency headers and follows the gateway URL', async () => {
    const h = harness({ dataset: { payment: '' }, responses: [{ payload: { data: { redirect_url: 'https://gateway.example/pay' } } }] });
    await h.send();
    assert.equal(h.calls[0].options.method, 'POST');
    assert.equal(h.calls[0].options.credentials, 'same-origin');
    assert.equal(h.calls[0].options.headers['X-CSRF-TOKEN'], 'csrf-test-token');
    assert.equal(h.calls[0].options.headers['Idempotency-Key'], 'request-1');
    assert.deepEqual(h.redirects, ['https://gateway.example/pay']);
    assert.equal(h.submit.disabled, false);
});

test('an uncertain network result reuses the idempotency key on retry', async () => {
    const h = harness({ responses: [new Error('Network unavailable'), { payload: { data: {} } }] });
    await h.send();
    assert.equal(h.feedback.textContent, 'Network unavailable');
    await h.send();
    assert.equal(h.calls[0].options.headers['Idempotency-Key'], h.calls[1].options.headers['Idempotency-Key']);
    assert.equal(h.reloads(), 1);
});

test('blocked cancellation does not send a mutation', async () => {
    const h = harness({ dataset: { preview: '/preview', blocked: 'Already active' }, responses: [{ payload: { data: { cancellable: false } } }] });
    await h.send();
    assert.equal(h.calls.length, 1);
    assert.equal(h.feedback.textContent, 'Already active');
    assert.equal(h.submit.disabled, false);
});

test('declining the cancellation policy confirmation does not send a mutation', async () => {
    const h = harness({
        dataset: { preview: '/preview', confirm: 'Refund: :amount' }, confirm: false,
        responses: [{ payload: { data: { cancellable: true, estimated_refund_minor: 7500, currency: 'EGP', items: [{ name: { en: 'Cake' }, reason_message: 'Refund eligible' }] } } }],
    });
    await h.send();
    assert.equal(h.calls.length, 1);
    assert.match(h.feedback.textContent, /Cake: Refund eligible/);
});

test('payment rejects executable redirect schemes and keeps a readable error', async () => {
    const h = harness({ dataset: { payment: '' }, responses: [{ payload: { data: { redirect_url: 'javascript:alert(1)' } } }] });
    await h.send();
    assert.deepEqual(h.redirects, []);
    assert.equal(h.feedback.textContent, 'Action failed');
});
