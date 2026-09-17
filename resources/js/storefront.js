const dialog = document.querySelector('[data-storefront-nav-dialog]');
const openButton = document.querySelector('[data-storefront-nav-open]');
const closeButton = document.querySelector('[data-storefront-nav-close]');
const storefrontHeader = document.querySelector('[data-storefront-header]');

document.querySelectorAll('[data-service-gallery]').forEach((gallery) => {
    const image = gallery.querySelector('[data-service-gallery-main]');
    if (!(image instanceof HTMLImageElement)) return;
    const buttons = gallery.querySelectorAll('[data-service-gallery-image]');
    buttons.forEach((button) => button.addEventListener('click', () => {
        image.src = button.dataset.serviceGalleryImage;
        buttons.forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
    }));
});

document.querySelectorAll('[data-localized-date]').forEach((wrapper) => {
    const input = wrapper.querySelector('[data-localized-date-input]');
    if (!(input instanceof HTMLInputElement)) return;
    const sync = () => wrapper.classList.toggle('is-filled', input.value !== '');
    input.addEventListener('input', sync);
    input.addEventListener('change', sync);
    sync();
});

document.querySelectorAll('[data-browser-timezone]').forEach((input) => {
    if (input instanceof HTMLInputElement) input.value = Intl.DateTimeFormat().resolvedOptions().timeZone || 'Africa/Cairo';
});

if (storefrontHeader instanceof HTMLElement) {
    const syncHeaderSurface = () => storefrontHeader.classList.toggle('is-scrolled', window.scrollY > 12);
    syncHeaderSurface();
    window.addEventListener('scroll', syncHeaderSurface, { passive: true });
}

if (dialog instanceof HTMLDialogElement && openButton instanceof HTMLElement) {
    const closeDrawer = () => {
        if (dialog.open) {
            dialog.close();
        }
    };

    openButton.addEventListener('click', () => dialog.showModal());
    closeButton?.addEventListener('click', closeDrawer);

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            closeDrawer();
        }
    });

    dialog.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeDrawer));
}

const hero = document.querySelector('[data-home-hero]');

if (hero instanceof HTMLElement) {
    const slides = [...hero.querySelectorAll('[data-home-hero-slide]')];
    const artwork = [...hero.querySelectorAll('[data-home-hero-art]')];
    const dots = [...hero.querySelectorAll('[data-home-hero-dot]')];
    let active = 0;

    const showSlide = (next) => {
        active = (next + slides.length) % slides.length;
        slides.forEach((slide, index) => {
            const visible = index === active;
            slide.classList.toggle('hidden', !visible);
            slide.setAttribute('aria-hidden', String(!visible));
        });
        artwork.forEach((art, index) => {
            const visible = index === active;
            art.classList.toggle('hidden', !visible);
            art.setAttribute('aria-hidden', String(!visible));
        });
        dots.forEach((dot, index) => dot.setAttribute('aria-current', String(index === active)));
    };

    hero.querySelector('[data-home-hero-prev]')?.addEventListener('click', () => showSlide(active - 1));
    hero.querySelector('[data-home-hero-next]')?.addEventListener('click', () => showSlide(active + 1));
    dots.forEach((dot, index) => dot.addEventListener('click', () => showSlide(index)));

    if (slides.length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        window.setInterval(() => showSlide(active + 1), 8000);
    }
}

const planner = document.querySelector('[data-storefront-planner]');

if (planner instanceof HTMLElement) {
    const panels = [...planner.querySelectorAll('[data-planner-panel]')];
    const indicators = [...planner.querySelectorAll('[data-planner-step-indicator]')];
    const occasionInput = planner.querySelector('[data-planner-occasion]');
    const cityInput = planner.querySelector('[data-planner-city]');
    const bookingCityInput = planner.querySelector('[data-planner-booking-city]');
    const governorateSelect = planner.querySelector('[data-planner-governorate]');
    const citySelect = planner.querySelector('[data-planner-city-select]');
    const form = planner.querySelector('[data-planner-form]');
    const occasionOptions = [...planner.querySelectorAll('[data-planner-occasion-option]')];
    const loadingMessage = planner.querySelector('[data-planner-loading]');
    let activeStep = Math.min(
        Math.max(Number.parseInt(planner.dataset.initialStep ?? '0', 10) || 0, 0),
        Math.max(panels.length - 1, 0),
    );
    const bookingMode = planner.dataset.bookingMode === 'true';
    const initialGovernorate = planner.dataset.initialGovernorate ?? '';
    const initialCity = planner.dataset.initialCity ?? '';
    const errorBox = planner.querySelector('[data-planner-error]');

    const showPlannerError = (message) => {
        if (!(errorBox instanceof HTMLElement)) return;
        errorBox.textContent = message;
        errorBox.classList.remove('hidden');
        errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    const clearPlannerError = () => {
        if (!(errorBox instanceof HTMLElement)) return;
        errorBox.textContent = '';
        errorBox.classList.add('hidden');
    };

    const currentStepIsValid = () => {
        clearPlannerError();

        if (activeStep === 0) {
            if (!(occasionInput instanceof HTMLInputElement) || occasionInput.value === '') {
                showPlannerError(planner.dataset.errorOccasion ?? '');
                return false;
            }

            const startsAt = planner.querySelector('[name="event_starts_at"]');
            const endsAt = planner.querySelector('[name="event_ends_at"]');
            if (!(startsAt instanceof HTMLInputElement) || !(endsAt instanceof HTMLInputElement) || startsAt.value === '' || endsAt.value === '') {
                showPlannerError(planner.dataset.errorTimesRequired ?? '');
                return false;
            }
            const startsDate = new Date(startsAt.value);
            const endsDate = new Date(endsAt.value);
            if (Number.isNaN(startsDate.valueOf()) || Number.isNaN(endsDate.valueOf()) || startsDate <= new Date() || endsDate <= startsDate) {
                showPlannerError(planner.dataset.errorTimesInvalid ?? '');
                return false;
            }
        }

        const governorateValue = governorateSelect instanceof HTMLSelectElement ? governorateSelect.value : '';
        if (activeStep === 1 && (governorateValue === '' || !(cityInput instanceof HTMLInputElement) || cityInput.value === '')) {
            showPlannerError(planner.dataset.errorLocation ?? '');
            return false;
        }

        return true;
    };

    const syncPlanner = () => {
        panels.forEach((panel, index) => {
            const visible = index === activeStep;
            panel.classList.toggle('hidden', !visible);
            panel.setAttribute('aria-hidden', String(!visible));
        });
        indicators.forEach((indicator, index) => {
            const state = index < activeStep ? 'completed' : (index === activeStep ? 'active' : 'upcoming');
            const mark = indicator.querySelector('[data-planner-step-mark]');
            const number = indicator.querySelector('[data-planner-step-number]');
            const check = indicator.querySelector('[data-planner-step-check]');
            const connector = indicator.querySelector('[data-planner-step-connector]');

            indicator.dataset.plannerStepState = state;
            if (connector instanceof HTMLElement) {
                connector.dataset.plannerStepState = state === 'completed' ? 'completed' : 'upcoming';
            }
            number?.classList.toggle('hidden', state === 'completed');
            check?.classList.toggle('hidden', state !== 'completed');
            if (index === activeStep) indicator.setAttribute('aria-current', 'step');
            else indicator.removeAttribute('aria-current');
        });
    };

    const advance = () => {
        if (!currentStepIsValid()) return;
        activeStep = Math.min(activeStep + 1, panels.length - 1);
        syncPlanner();
    };

    planner.querySelectorAll('[data-planner-next]').forEach((button) => {
        button.addEventListener('click', advance);
    });

    planner.querySelectorAll('[data-planner-back]').forEach((button) => {
        button.addEventListener('click', () => {
            clearPlannerError();
            activeStep = Math.max(activeStep - 1, 0);
            syncPlanner();
        });
    });

    const selectOccasion = (button) => {
        if (occasionInput instanceof HTMLInputElement) {
            occasionInput.value = button.dataset.plannerOccasionOption ?? '';
        }
        occasionOptions.forEach((option) => {
            option.setAttribute('aria-checked', String(option === button));
        });
        clearPlannerError();
    };

    occasionOptions.forEach((button) => {
        button.addEventListener('click', () => {
            selectOccasion(button);
        });
    });
    if (occasionInput instanceof HTMLInputElement && occasionInput.value !== '') {
        const selectedOccasion = planner.querySelector(`[data-planner-occasion-option="${CSS.escape(occasionInput.value)}"]`);
        if (selectedOccasion instanceof HTMLButtonElement) selectOccasion(selectedOccasion);
    }

    const unwrapData = (payload) => Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : []);
    const loadJson = async (url) => {
        const response = await fetch(url, { headers: { Accept: 'application/json', 'Accept-Language': document.documentElement.lang } });
        if (!response.ok) throw new Error(`Request failed with status ${response.status}`);
        return unwrapData(await response.json());
    };
    const optionLabel = (item) => item.name?.[document.documentElement.lang] ?? item.name?.en ?? item.name ?? '';
    const appendOptions = (select, items, placeholder) => {
        if (!(select instanceof HTMLSelectElement)) return;
        select.replaceChildren(new Option(placeholder, ''));
        items.forEach((item) => select.add(new Option(optionLabel(item), item.public_id)));
    };
    const locationLoadingText = loadingMessage?.textContent ?? '';
    const setLocationStatus = (message, state = 'idle') => {
        if (!(loadingMessage instanceof HTMLElement)) return;
        loadingMessage.textContent = message;
        loadingMessage.dataset.plannerStatus = state;
        loadingMessage.classList.toggle('hidden', state === 'idle');
    };

    if (governorateSelect instanceof HTMLSelectElement && citySelect instanceof HTMLSelectElement) {
        setLocationStatus(locationLoadingText, 'loading');
        loadJson('/api/v1/customer/governorates').then((items) => {
            appendOptions(governorateSelect, items, governorateSelect.options[0]?.text ?? '');
            if (initialGovernorate) {
                governorateSelect.value = initialGovernorate;
                governorateSelect.dispatchEvent(new Event('change'));
            } else {
                setLocationStatus('', 'idle');
            }
        }).catch(() => setLocationStatus(planner.dataset.errorLocationLoad ?? '', 'error'));
        governorateSelect.addEventListener('change', async () => {
            if (governorateSelect.value === '') {
                appendOptions(citySelect, [], citySelect.options[0]?.text ?? '');
                if (cityInput instanceof HTMLInputElement) cityInput.value = '';
                if (bookingCityInput instanceof HTMLInputElement) bookingCityInput.value = '';
                setLocationStatus('', 'idle');
                return;
            }

            setLocationStatus(locationLoadingText, 'loading');
            appendOptions(citySelect, [], citySelect.options[0]?.text ?? '');
            try {
                const regions = await loadJson(`/api/v1/customer/governorates/${governorateSelect.value}/regions`);
                const cities = [];
                for (const region of regions) {
                    cities.push(...await loadJson(`/api/v1/customer/regions/${region.public_id}/cities`));
                }
                appendOptions(citySelect, cities, citySelect.options[0]?.text ?? '');
                if (initialCity) {
                    citySelect.value = initialCity;
                    citySelect.dispatchEvent(new Event('change'));
                }
                setLocationStatus('', 'idle');
            } catch {
                setLocationStatus(planner.dataset.errorLocationLoad ?? '', 'error');
            } finally {
                if (loadingMessage?.dataset.plannerStatus === 'loading') setLocationStatus('', 'idle');
            }
        });
        citySelect.addEventListener('change', () => {
            if (cityInput instanceof HTMLInputElement) cityInput.value = citySelect.value;
            if (bookingCityInput instanceof HTMLInputElement) bookingCityInput.value = citySelect.value;
            clearPlannerError();
        });
    }

    planner.querySelectorAll('[name="event_starts_at"], [name="event_ends_at"]').forEach((input) => {
        input.addEventListener('input', clearPlannerError);
        input.addEventListener('change', clearPlannerError);
    });

    form?.addEventListener('submit', (event) => {
        if (activeStep < panels.length - 1) {
            event.preventDefault();
            advance();
        }
    });

    syncPlanner();
}

const faqPage = document.querySelector('[data-faq-page]');

if (faqPage instanceof HTMLElement) {
    const form = faqPage.querySelector('[data-faq-search-form]');
    const input = faqPage.querySelector('[data-faq-search-input]');
    const clearButton = faqPage.querySelector('[data-faq-search-clear]');
    const list = faqPage.querySelector('[data-faq-list]');
    const emptyState = faqPage.querySelector('[data-faq-empty]');
    const resultCount = faqPage.querySelector('[data-faq-count]');
    const language = document.documentElement.lang || 'en';

    if (form instanceof HTMLFormElement && input instanceof HTMLInputElement && list instanceof HTMLElement) {
        const normalize = (value) => String(value ?? '').normalize('NFC').toLocaleLowerCase(language).trim();
        const items = [...list.querySelectorAll('[data-faq-item]')];

        const syncResults = () => {
            const query = normalize(input.value);
            let visibleCount = 0;

            items.forEach((item) => {
                const matches = query === '' || normalize(item.dataset.faqSearch ?? '').includes(query);
                item.hidden = !matches;
                if (!matches && item instanceof HTMLDetailsElement) item.open = false;
                if (matches) visibleCount += 1;
            });

            if (emptyState instanceof HTMLElement) emptyState.hidden = visibleCount > 0;
            list.hidden = visibleCount === 0;
            if (clearButton instanceof HTMLButtonElement) clearButton.hidden = query === '';
            if (resultCount instanceof HTMLElement) {
                resultCount.textContent = query === '' ? '' : (resultCount.dataset.faqCountTemplate ?? '').replace(':count', String(visibleCount));
            }
        };

        form.addEventListener('submit', (event) => event.preventDefault());
        input.addEventListener('input', syncResults);
        clearButton?.addEventListener('click', () => {
            input.value = '';
            input.focus();
            syncResults();
        });
        syncResults();
    }
}

const contactForm = document.querySelector('[data-contact-form]');

if (contactForm instanceof HTMLFormElement) {
    const submitButton = contactForm.querySelector('[data-contact-submit]');
    const status = contactForm.querySelector('[data-contact-status]');
    const fieldErrors = new Map([...contactForm.querySelectorAll('[data-contact-error]')].map((element) => [element.dataset.contactError, element]));

    const clearFieldError = (field) => {
        const error = fieldErrors.get(field.name);
        field.removeAttribute('aria-invalid');
        if (!(error instanceof HTMLElement)) return;
        error.textContent = '';
        error.hidden = true;
    };

    const showFieldError = (fieldName, fromServer = false) => {
        const field = contactForm.elements.namedItem(fieldName);
        const error = fieldErrors.get(fieldName);
        if (!(field instanceof HTMLElement) || !(error instanceof HTMLElement)) return;
        field.setAttribute('aria-invalid', 'true');
        error.textContent = fromServer ? (error.dataset.serverMessage ?? error.dataset.errorMessage ?? '') : (error.dataset.errorMessage ?? '');
        error.hidden = false;
    };

    contactForm.addEventListener('input', (event) => {
        if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) clearFieldError(event.target);
    });

    contactForm.addEventListener('invalid', (event) => {
        if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) showFieldError(event.target.name);
    }, true);

    contactForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!(submitButton instanceof HTMLButtonElement) || !(status instanceof HTMLElement) || submitButton.disabled) return;

        [...contactForm.elements].forEach((field) => {
            if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) clearFieldError(field);
        });
        submitButton.disabled = true;
        submitButton.textContent = contactForm.dataset.submittingLabel ?? '';
        contactForm.setAttribute('aria-busy', 'true');
        status.className = 'hidden rounded-xl px-4 py-3 text-sm font-bold';

        try {
            const response = await fetch(contactForm.dataset.endpoint ?? '', {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'Accept-Language': document.documentElement.lang || 'en' },
                body: JSON.stringify(Object.fromEntries(new FormData(contactForm))),
            });
            const payload = await response.json();

            if (!response.ok) {
                if (response.status === 422 && payload.errors && typeof payload.errors === 'object') {
                    Object.keys(payload.errors).forEach((fieldName) => showFieldError(fieldName, true));
                    contactForm.querySelector('[aria-invalid="true"]')?.focus();
                }
                throw new Error();
            }

            const reference = payload.data?.reference ? ` (${payload.data.reference})` : '';
            status.textContent = `${payload.data?.message ?? ''}${reference}`;
            status.className = 'rounded-xl bg-secondary-50 px-4 py-3 text-sm font-bold text-secondary-800';
            contactForm.reset();
        } catch {
            status.textContent = contactForm.dataset.errorMessage ?? '';
            status.className = 'rounded-xl bg-red-50 px-4 py-3 text-sm font-bold text-red-800';
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = contactForm.dataset.submitLabel ?? '';
            contactForm.removeAttribute('aria-busy');
        }
    });
}

document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
    if (!(toggle instanceof HTMLButtonElement)) return;

    const input = document.getElementById(toggle.getAttribute('aria-controls') ?? '');
    if (!(input instanceof HTMLInputElement)) return;

    toggle.addEventListener('click', () => {
        const hidden = input.type === 'password';
        input.type = hidden ? 'text' : 'password';
        toggle.setAttribute('aria-label', hidden ? (toggle.dataset.hideLabel ?? '') : (toggle.dataset.showLabel ?? ''));
        toggle.setAttribute('aria-pressed', String(hidden));
    });
});

document.querySelectorAll('[data-otp-input]').forEach((container) => {
    const form = container.closest('form');
    const digits = [...container.querySelectorAll('[data-otp-digit]')].filter((input) => input instanceof HTMLInputElement);
    const value = form?.querySelector('[data-otp-value]');
    if (!(form instanceof HTMLFormElement) || !(value instanceof HTMLInputElement) || digits.length === 0) return;

    const sync = () => { value.value = digits.map((input) => input.value).join(''); };
    const focus = (index) => digits[index]?.focus();

    digits.forEach((input, index) => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/g, '').slice(-1);
            sync();
            if (input.value) focus(index + 1);
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && !input.value) focus(index - 1);
            if (event.key === 'ArrowLeft') focus(index - 1);
            if (event.key === 'ArrowRight') focus(index + 1);
        });
        input.addEventListener('paste', (event) => {
            const pasted = event.clipboardData?.getData('text').replace(/\D/g, '').slice(0, digits.length);
            if (!pasted) return;
            event.preventDefault();
            pasted.split('').forEach((digit, pastedIndex) => { if (digits[index + pastedIndex]) digits[index + pastedIndex].value = digit; });
            sync();
            focus(Math.min(index + pasted.length, digits.length - 1));
        });
    });

    const existing = value.value.replace(/\D/g, '');
    existing.split('').forEach((digit, index) => { if (digits[index]) digits[index].value = digit; });
    sync();
    focus(existing.length < digits.length ? existing.length : 0);
});

document.querySelectorAll('[data-password-match]').forEach((form) => {
    if (!(form instanceof HTMLFormElement)) return;
    const password = form.elements.namedItem('password');
    const confirmation = form.elements.namedItem('password_confirmation');
    if (!(password instanceof HTMLInputElement) || !(confirmation instanceof HTMLInputElement)) return;

    const validate = () => confirmation.setCustomValidity(
        confirmation.value && confirmation.value !== password.value ? (form.dataset.passwordMatchMessage ?? '') : '',
    );
    password.addEventListener('input', validate);
    confirmation.addEventListener('input', validate);
});

document.querySelectorAll('[data-auth-form]').forEach((form) => {
    if (!(form instanceof HTMLFormElement)) return;
    form.addEventListener('submit', () => {
        const submit = form.querySelector('button[type="submit"]');
        if (!(submit instanceof HTMLButtonElement) || submit.disabled) return;
        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        submit.textContent = submit.dataset.submitting ?? submit.textContent;
    });
});
