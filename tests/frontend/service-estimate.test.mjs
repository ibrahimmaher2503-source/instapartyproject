import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/storefront.js', import.meta.url), 'utf8');
const estimateStart = source.indexOf("const serviceEstimate = document.querySelector('[data-service-estimate]');");
const estimateEnd = source.indexOf("const searchGeography = document.querySelector('[data-search-geography]');", estimateStart);
const estimateSource = source.slice(estimateStart, estimateEnd);

test('preserves a selected rental end on initial load and recalculates it after duration edits', () => {
    class HTMLElement { textContent = ''; }
    class HTMLInputElement extends HTMLElement { constructor(value) { super(); this.value = value; } }
    class HTMLAnchorElement extends HTMLElement {
        #href = 'https://example.test/en/wizard?service=service-id';
        get href() { return new URL(this.#href, 'https://example.test').href; }
        set href(value) { this.#href = new URL(value, 'https://example.test').href; }
        attributes = {};
        setAttribute(name, value) { this.attributes[name] = value; }
        addEventListener() {}
    }
    class HTMLFormElement extends HTMLElement {
        dataset = { estimateQuantity: '1' };
        listeners = {};
        elements = { namedItem: (name) => name === 'timezone' ? timezone : null };
        querySelector(selector) {
            return ({
                '[data-service-estimate-start]': start,
                '[data-service-estimate-duration]': duration,
                '[data-service-estimate-quantity]': quantity,
                '[data-service-estimate-end]': end,
                '[data-service-check-availability]': null,
                '[data-service-availability-status]': null,
                '[data-service-estimate-refresh-status]': refreshStatus,
            })[selector] ?? null;
        }
        addEventListener(name, callback) { this.listeners[name] = callback; }
    }

    const start = new HTMLInputElement('2030-06-10T10:00');
    const duration = new HTMLInputElement('36');
    const quantity = new HTMLInputElement('1');
    const end = new HTMLInputElement('2030-06-12T04:00');
    const timezone = new HTMLInputElement('Africa/Cairo');
    const refreshStatus = new HTMLElement();
    const form = new HTMLFormElement();
    const link = new HTMLAnchorElement();

    vm.runInNewContext(estimateSource, {
        document: { querySelector: (selector) => selector === '[data-service-estimate]' ? form : link },
        HTMLAnchorElement, HTMLButtonElement: class {}, HTMLFormElement, HTMLInputElement, HTMLElement, URL, Date, Number,
    });

    assert.equal(new URL(link.href).searchParams.get('event_ends_at'), end.value);

    duration.value = '48';
    form.listeners.input();
    const expectedEnd = new Date(start.value);
    expectedEnd.setTime(expectedEnd.getTime() + 48 * 60 * 60 * 1000);
    const expectedValue = [expectedEnd.getFullYear(), String(expectedEnd.getMonth() + 1).padStart(2, '0'), String(expectedEnd.getDate()).padStart(2, '0')].join('-')
        + `T${String(expectedEnd.getHours()).padStart(2, '0')}:${String(expectedEnd.getMinutes()).padStart(2, '0')}`;

    assert.equal(end.value, expectedValue);
    assert.equal(new URL(link.href).searchParams.get('event_ends_at'), expectedValue);
});
