import './booking-actions';

document.querySelectorAll('[data-home-vendors]').forEach((section) => {
    const rail = section.querySelector('.sf-vendor-rail');
    const pagination = section.querySelector('[data-vendor-pages]');
    if (!(rail instanceof HTMLElement) || !(pagination instanceof HTMLElement)) return;
    const rtl = getComputedStyle(rail).direction === 'rtl';
    let distance = 0;
    let perPage = 1;
    const sync = () => {
        const position = Math.abs(rail.scrollLeft);
        const atEnd = position >= rail.scrollWidth - rail.clientWidth - 2;
        const active = atEnd ? pagination.childElementCount - 1 : Math.round(position / (distance * perPage));
        [...pagination.children].forEach((button, index) => button.setAttribute('aria-pressed', String(index === active)));
    };
    const resize = () => {
        distance = rail.firstElementChild.getBoundingClientRect().width + parseFloat(getComputedStyle(rail).columnGap);
        perPage = Math.max(1, Math.floor((rail.clientWidth + parseFloat(getComputedStyle(rail).columnGap) + 1) / distance));
        const count = Math.ceil(rail.childElementCount / perPage);
        if (pagination.childElementCount !== count) {
            pagination.replaceChildren();
            for (let index = 0; index < count; index++) {
                const button = document.createElement('button');
                button.type = 'button';
                button.setAttribute('aria-controls', rail.id);
                button.setAttribute('aria-label', pagination.dataset.pageLabel.replace(':number', String(index + 1)));
                button.addEventListener('click', () => rail.scrollTo({
                    left: Math.min(index * perPage * distance, rail.scrollWidth - rail.clientWidth) * (rtl ? -1 : 1),
                    behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
                }));
                pagination.append(button);
            }
        }
        sync();
    };
    rail.addEventListener('scroll', sync, { passive: true });
    new ResizeObserver(resize).observe(rail);
    resize();
});

document.querySelectorAll('[data-home-services]').forEach((section) => {
    const cards = [...section.querySelectorAll('[data-service-category]')];
    const filters = [...section.querySelectorAll('[data-service-filter]')];
    const pages = [...section.querySelectorAll('[data-service-page]')];
    let category = '';
    const showPage = (page) => {
        const matches = cards.filter((card) => !category || card.dataset.serviceCategory === category);
        cards.forEach((card) => { card.hidden = !matches.slice(page * 8, page * 8 + 8).includes(card); });
        section.querySelector('.sf-service-rail').scrollLeft = 0;
        pages.forEach((button, index) => {
            button.hidden = index >= Math.ceil(matches.length / 8);
            button.setAttribute('aria-pressed', String(index === page));
        });
    };
    filters.forEach((button) => button.addEventListener('click', () => {
        category = button.dataset.serviceFilter;
        filters.forEach((filter) => filter.setAttribute('aria-pressed', String(filter === button)));
        showPage(0);
    }));
    pages.forEach((button) => button.addEventListener('click', () => showPage(Number(button.dataset.servicePage))));
    showPage(0);
});

const dialog = document.querySelector('[data-storefront-nav-dialog]');
const openButton = document.querySelector('[data-storefront-nav-open]');
const closeButton = document.querySelector('[data-storefront-nav-close]');
const storefrontHeader = document.querySelector('[data-storefront-header]');

document.querySelectorAll('[data-service-gallery]').forEach((gallery) => {
    const image = gallery.querySelector('[data-service-gallery-main]');
    if (!(image instanceof HTMLImageElement)) return;
    let buttons = Array.from(gallery.querySelectorAll('[data-service-gallery-image]'));
    buttons.forEach((button) => button.addEventListener('click', () => {
        image.src = button.dataset.serviceGalleryImage;
        buttons.forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
        const position = gallery.querySelector('[data-gallery-position]');
        if (position instanceof HTMLElement) {
            position.textContent = `${buttons.indexOf(button) + 1} / ${buttons.length}`;
        }
    }));
    gallery.querySelectorAll('[data-gallery-step]').forEach((control) => control.addEventListener('click', () => {
        if (buttons.length === 0) return;
        const current = Math.max(0, Array.from(buttons).findIndex((item) => item.getAttribute('aria-pressed') === 'true'));
        const next = (current + Number(control.dataset.galleryStep) + buttons.length) % buttons.length;
        buttons[next].click();
    }));
    if (gallery.hasAttribute('data-vendor-gallery')) {
        const skipBrokenImage = () => {
            buttons = buttons.filter((button) => {
                if (button.dataset.serviceGalleryImage === image.getAttribute('src')) {
                    button.remove();
                    return false;
                }
                return true;
            });
            if (buttons.length > 0) {
                buttons[0].click();
            } else {
                gallery.hidden = true;
                const empty = gallery.parentElement.querySelector('[data-vendor-gallery-empty]');
                if (empty) empty.hidden = false;
            }
        };
        image.addEventListener('error', skipBrokenImage);
        if (image.complete && image.naturalWidth === 0) skipBrokenImage();
        buttons.forEach((button) => {
            const thumbnail = button.querySelector('img');
            if (!(thumbnail instanceof HTMLImageElement)) return;
            thumbnail.addEventListener('error', () => { thumbnail.hidden = true; });
            if (thumbnail.complete && thumbnail.naturalWidth === 0) thumbnail.hidden = true;
        });
    }
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
    if (!(input instanceof HTMLInputElement) || input.hasAttribute('data-explicit-timezone')) return;
    const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'Africa/Cairo';
    try {
        new Intl.DateTimeFormat('en', { timeZone: input.value || browserTimezone });
    } catch {
        input.value = browserTimezone;
    }
    if (input.value === '') input.value = browserTimezone;
});

const serviceEstimate = document.querySelector('[data-service-estimate]');

if (serviceEstimate instanceof HTMLFormElement) {
    const startInput = serviceEstimate.querySelector('[data-service-estimate-start]');
    const durationInput = serviceEstimate.querySelector('[data-service-estimate-duration]');
    const quantityInput = serviceEstimate.querySelector('[data-service-estimate-quantity]');
    const checkButton = serviceEstimate.querySelector('[data-service-check-availability]');
    const status = serviceEstimate.querySelector('[data-service-availability-status]');
    const estimateRefreshStatus = serviceEstimate.querySelector('[data-service-estimate-refresh-status]');
    const wizardLink = document.querySelector('[data-service-wizard-link]');

    const updateWizardLink = () => {
        if (!(wizardLink instanceof HTMLAnchorElement)) return;
        const url = new URL(wizardLink.href);
        const startsAt = startInput instanceof HTMLInputElement ? startInput.value : '';
        const quantity = quantityInput instanceof HTMLInputElement ? quantityInput.value : '1';

        if (startsAt) url.searchParams.set('event_starts_at', startsAt);
        else url.searchParams.delete('event_starts_at');

        if (durationInput instanceof HTMLInputElement && startsAt && Number(durationInput.value) > 0) {
            const end = new Date(startsAt);
            end.setTime(end.getTime() + Number(durationInput.value) * 60 * 60 * 1000);
            const localEnd = [end.getFullYear(), String(end.getMonth() + 1).padStart(2, '0'), String(end.getDate()).padStart(2, '0')].join('-')
                + `T${String(end.getHours()).padStart(2, '0')}:${String(end.getMinutes()).padStart(2, '0')}`;
            url.searchParams.set('event_ends_at', localEnd);
        } else {
            url.searchParams.delete('event_ends_at');
        }

        if (quantity) url.searchParams.set('quantity', quantity);
        if (serviceEstimate.elements.namedItem('timezone') instanceof HTMLInputElement) {
            url.searchParams.set('timezone', serviceEstimate.elements.namedItem('timezone').value);
        }
        wizardLink.href = `${url.pathname}?${url.searchParams.toString()}`;

        const estimateIsStale = serviceEstimate.dataset.estimateQuantity !== quantity;
        wizardLink.setAttribute('aria-disabled', String(estimateIsStale));
        if (estimateRefreshStatus instanceof HTMLElement) {
            estimateRefreshStatus.textContent = estimateIsStale ? (serviceEstimate.dataset.estimateRefreshMessage ?? '') : '';
        }
    };

    wizardLink?.addEventListener('click', (event) => {
        if (wizardLink.getAttribute('aria-disabled') !== 'true') return;
        event.preventDefault();
        estimateRefreshStatus?.focus?.();
    });

    serviceEstimate.addEventListener('input', () => {
        updateWizardLink();
        if (status instanceof HTMLElement) status.textContent = '';
    });
    serviceEstimate.addEventListener('change', () => {
        updateWizardLink();
        if (status instanceof HTMLElement) status.textContent = '';
    });
    updateWizardLink();

    if (checkButton instanceof HTMLButtonElement && status instanceof HTMLElement) {
        checkButton.addEventListener('click', async () => {
            const productType = serviceEstimate.dataset.productType ?? '';
            const startsAt = startInput instanceof HTMLInputElement ? startInput.value : '';
            const duration = durationInput instanceof HTMLInputElement ? Number(durationInput.value) : null;
            const quantity = quantityInput instanceof HTMLInputElement ? Number(quantityInput.value) : 1;

            if (productType === 'rental' && startsAt === '') {
                status.textContent = serviceEstimate.dataset.availabilityDateRequired ?? '';
                startInput?.focus();
                return;
            }
            if (startsAt !== '' && (!Number.isFinite(new Date(startsAt).getTime()) || new Date(startsAt).getTime() <= Date.now())) {
                status.textContent = serviceEstimate.dataset.availabilityDateInvalid ?? '';
                startInput?.focus();
                return;
            }
            if (productType === 'rental' && (!Number.isInteger(duration) || duration < 1 || duration > 720)) {
                status.textContent = serviceEstimate.dataset.availabilityDurationInvalid ?? '';
                durationInput?.focus();
                return;
            }
            if (!Number.isInteger(quantity) || quantity < 1 || quantity > 100) {
                status.textContent = serviceEstimate.dataset.availabilityQuantityInvalid ?? '';
                quantityInput?.focus();
                return;
            }

            const payload = { quantity };
            if (productType === 'rental' && startInput instanceof HTMLInputElement) {
                const start = new Date(startInput.value);
                const end = new Date(start.getTime() + duration * 60 * 60 * 1000);
                payload.starts_at = start.toISOString();
                payload.ends_at = end.toISOString();
            }

            checkButton.disabled = true;
            status.textContent = serviceEstimate.dataset.availabilityChecking ?? '';
            try {
                const response = await fetch(serviceEstimate.dataset.availabilityEndpoint ?? '', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Accept-Language': document.documentElement.lang,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });
                const result = await response.json();
                if (!response.ok || typeof result?.data?.available !== 'boolean') throw new Error();

                status.textContent = result.data.available
                    ? (serviceEstimate.dataset[`availabilityAvailable${productType[0].toUpperCase()}${productType.slice(1)}`] ?? '')
                    : (serviceEstimate.dataset.availabilityUnavailable ?? '');
            } catch {
                status.textContent = serviceEstimate.dataset.availabilityError ?? '';
            } finally {
                checkButton.disabled = false;
            }
        });
    }
}

const searchGeography = document.querySelector('[data-search-geography]');

if (searchGeography instanceof HTMLFormElement) {
    const governorateSelect = searchGeography.querySelector('[data-search-governorate]');
    const citySelect = searchGeography.querySelector('[data-search-city]');

    if (governorateSelect instanceof HTMLSelectElement && citySelect instanceof HTMLSelectElement) {
        const cityPlaceholder = citySelect.options[0]?.text ?? '';

        governorateSelect.addEventListener('change', async () => {
            citySelect.replaceChildren(new Option(cityPlaceholder, ''));
            citySelect.disabled = true;

            if (governorateSelect.value === '') return;

            try {
                const response = await fetch(`/api/v1/customer/cities?governorate=${encodeURIComponent(governorateSelect.value)}`, {
                    headers: { Accept: 'application/json', 'Accept-Language': document.documentElement.lang },
                });
                if (!response.ok) return;

                const payload = await response.json();
                const cities = Array.isArray(payload?.data) ? payload.data : [];
                cities.forEach((city) => citySelect.add(new Option(city.name, city.public_id)));
            } finally {
                citySelect.disabled = governorateSelect.value === '';
            }
        });
    }

    const categorySelect = searchGeography.querySelector('[data-search-category]');
    const subcategorySelect = searchGeography.querySelector('[data-search-subcategory]');
    const subcategoryOptionsNode = searchGeography.querySelector('[data-search-subcategory-options]');
    if (categorySelect instanceof HTMLSelectElement && subcategorySelect instanceof HTMLSelectElement
        && subcategoryOptionsNode instanceof HTMLScriptElement) {
        const placeholder = subcategorySelect.options[0]?.text ?? '';
        const options = JSON.parse(subcategoryOptionsNode.textContent || '[]');
        categorySelect.addEventListener('change', () => {
            subcategorySelect.replaceChildren(new Option(placeholder, ''));
            const children = options.filter((option) => option.parent === categorySelect.value);
            children.forEach((option) => subcategorySelect.add(new Option(option.name, option.code)));
            subcategorySelect.disabled = children.length === 0;
        });
    }
    const occasionSelect = searchGeography.querySelector('[data-search-occasion]');
    const packageSelect = searchGeography.querySelector('[data-search-package]');
    const packageOptionsNode = searchGeography.querySelector('[data-search-package-options]');
    const vendorSelect = searchGeography.querySelector('[data-search-vendor]');
    const vendorOptionsNode = searchGeography.querySelector('[data-search-vendor-options]');

    if (categorySelect instanceof HTMLSelectElement
        && occasionSelect instanceof HTMLSelectElement
        && vendorSelect instanceof HTMLSelectElement
        && vendorOptionsNode instanceof HTMLScriptElement) {
        const vendorPlaceholder = vendorSelect.options[0]?.text ?? '';
        const vendorOptions = JSON.parse(vendorOptionsNode.textContent || '[]');

        const refreshVendors = () => {
            const selectedVendor = vendorSelect.value;
            const category = subcategorySelect?.value || categorySelect.value;
            const occasion = occasionSelect.value;
            const matching = vendorOptions.filter((vendor) => vendor.combinations.some((combination) => (
                (category === '' || combination.categories.includes(category))
                && (occasion === '' || combination.occasions.includes(occasion))
            )));

            vendorSelect.replaceChildren(new Option(vendorPlaceholder, ''));
            matching.forEach((vendor) => vendorSelect.add(new Option(vendor.label, vendor.public_id)));
            vendorSelect.value = matching.some((vendor) => vendor.public_id === selectedVendor) ? selectedVendor : '';
        };

        categorySelect.addEventListener('change', refreshVendors);
        subcategorySelect?.addEventListener('change', refreshVendors);
        occasionSelect.addEventListener('change', refreshVendors);
    }

    if (occasionSelect instanceof HTMLSelectElement
        && packageSelect instanceof HTMLSelectElement
        && packageOptionsNode instanceof HTMLScriptElement) {
        const packageEmptyPlaceholder = packageSelect.options[0]?.text ?? '';
        const packageAllLabel = packageSelect.dataset.searchPackageAllLabel ?? packageEmptyPlaceholder;
        const packageOptions = JSON.parse(packageOptionsNode.textContent || '[]');

        occasionSelect.addEventListener('change', () => {
            const occasion = occasionSelect.value;
            packageSelect.replaceChildren(new Option(occasion === '' ? packageEmptyPlaceholder : packageAllLabel, ''));
            packageOptions
                .filter((packageOption) => packageOption.occasion_code === occasion)
                .forEach((packageOption) => packageSelect.add(new Option(packageOption.name, packageOption.public_id)));
            packageSelect.disabled = occasion === '';
        });
    }
}

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
    const timezoneInput = planner.querySelector('[name="timezone"]');
    const startsAtInput = planner.querySelector('[name="event_starts_at"]');
    const endsAtInput = planner.querySelector('[name="event_ends_at"]');

    const plannerTimezone = () => timezoneInput instanceof HTMLInputElement && timezoneInput.value !== ''
        ? timezoneInput.value
        : (Intl.DateTimeFormat().resolvedOptions().timeZone || 'Africa/Cairo');
    const formatDateTimeInTimezone = (date, timeZone) => {
        const parts = new Intl.DateTimeFormat('en', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        }).formatToParts(date);
        const values = Object.fromEntries(parts.map(({ type, value }) => [type, value]));
        return `${values.year}-${values.month}-${values.day}T${values.hour}:${values.minute}`;
    };
    const addLocalMinutes = (value, minutes) => {
        const date = new Date(`${value}:00Z`);
        if (Number.isNaN(date.valueOf())) return '';
        date.setUTCMinutes(date.getUTCMinutes() + minutes);
        return date.toISOString().slice(0, 16);
    };
    const syncDateMinimums = () => {
        if (!(startsAtInput instanceof HTMLInputElement) || !(endsAtInput instanceof HTMLInputElement)) return;
        const minimum = formatDateTimeInTimezone(new Date(Date.now() + 60_000), plannerTimezone());
        startsAtInput.min = minimum;
        const afterStart = startsAtInput.value === '' ? '' : addLocalMinutes(startsAtInput.value, 1);
        endsAtInput.min = afterStart !== '' && afterStart > minimum ? afterStart : minimum;
    };
    const restoreInitialDate = (input) => {
        if (!(input instanceof HTMLInputElement)) return;
        const initial = input.dataset.initialDate ?? '';
        if (initial === '') return;
        if (/[zZ]|[+-]\d{2}:\d{2}$/.test(initial)) {
            const instant = new Date(initial);
            if (!Number.isNaN(instant.valueOf())) input.value = formatDateTimeInTimezone(instant, plannerTimezone());
            return;
        }
        input.value = initial;
    };

    restoreInitialDate(startsAtInput);
    restoreInitialDate(endsAtInput);
    syncDateMinimums();

    let previousStartValue = startsAtInput instanceof HTMLInputElement ? startsAtInput.value : '';
    const preserveRentalDuration = () => {
        if (!(startsAtInput instanceof HTMLInputElement) || !(endsAtInput instanceof HTMLInputElement)) return;
        const nextStart = startsAtInput.value;

        if (previousStartValue !== '' && nextStart !== '' && endsAtInput.value !== '') {
            const previousStart = new Date(`${previousStartValue}:00Z`);
            const selectedEnd = new Date(`${endsAtInput.value}:00Z`);
            const durationMinutes = (selectedEnd.valueOf() - previousStart.valueOf()) / 60_000;
            if (Number.isInteger(durationMinutes) && durationMinutes > 0) {
                endsAtInput.value = addLocalMinutes(nextStart, durationMinutes);
            }
        }

        previousStartValue = nextStart;
    };

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

            syncDateMinimums();
            if (!(startsAtInput instanceof HTMLInputElement) || !(endsAtInput instanceof HTMLInputElement) || startsAtInput.value === '') {
                showPlannerError(planner.dataset.errorTimesRequired ?? '');
                return false;
            }
            if (startsAtInput.value < startsAtInput.min) {
                showPlannerError(planner.dataset.errorTimesInvalid ?? '');
                startsAtInput.focus();
                return false;
            }
            if (endsAtInput.value !== '' && endsAtInput.value < endsAtInput.min) {
                showPlannerError(planner.dataset.errorTimesInvalid ?? '');
                endsAtInput.focus();
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
    };

    occasionOptions.forEach((button) => {
        button.addEventListener('click', () => {
            selectOccasion(button);
            clearPlannerError();
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
                }
                if (cityInput instanceof HTMLInputElement) cityInput.value = citySelect.value;
                if (bookingCityInput instanceof HTMLInputElement) bookingCityInput.value = citySelect.value;
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

    [startsAtInput, endsAtInput].forEach((input) => {
        if (!(input instanceof HTMLInputElement)) return;
        input.addEventListener('input', () => {
            if (input === startsAtInput) preserveRentalDuration();
            syncDateMinimums();
            clearPlannerError();
        });
        input.addEventListener('change', () => {
            if (input === startsAtInput) preserveRentalDuration();
            syncDateMinimums();
            clearPlannerError();
        });
    });

    form?.addEventListener('submit', (event) => {
        syncDateMinimums();
        if (!(startsAtInput instanceof HTMLInputElement) || !(endsAtInput instanceof HTMLInputElement)) return;
        if (startsAtInput.value < startsAtInput.min || (endsAtInput.value !== '' && endsAtInput.value < endsAtInput.min)) {
            event.preventDefault();
            activeStep = 0;
            syncPlanner();
            showPlannerError(planner.dataset.errorTimesInvalid ?? '');
            (startsAtInput.value < startsAtInput.min ? startsAtInput : endsAtInput).focus();
        }
    });

    const recipientPhone = planner.querySelector('[data-planner-recipient-phone]');
    const recipientPhoneError = planner.querySelector('[data-planner-recipient-phone-error]');
    if (recipientPhone instanceof HTMLInputElement && recipientPhoneError instanceof HTMLElement) {
        recipientPhone.addEventListener('invalid', (event) => {
            event.preventDefault();
            recipientPhone.setAttribute('aria-invalid', 'true');
            recipientPhoneError.classList.remove('hidden');
            recipientPhone.focus();
        });
        recipientPhone.addEventListener('input', () => {
            recipientPhone.setAttribute('aria-invalid', 'false');
            recipientPhoneError.classList.add('hidden');
            clearPlannerError();
        });
    }

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
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'Accept-Language': document.documentElement.lang || 'en', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
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

document.querySelectorAll('[data-occasion-packages]').forEach((section) => {
    const buttons = [...section.querySelectorAll('[data-package-filter]')];
    const cards = [...section.querySelectorAll('[data-package-occasion]')];
    const count = section.querySelector('[data-package-count]');
    const empty = section.querySelector('[data-package-empty]');

    const selectOccasion = (selected) => {
        let visible = 0;
        cards.forEach((card) => {
            const matches = selected === '*' || card.dataset.packageOccasion === selected;
            card.hidden = !matches || visible >= 10;
            if (!card.hidden) visible += 1;
        });
        buttons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.packageFilter === selected)));
        if (count) count.textContent = (count.dataset.countLabel ?? ':count').replace(':count', String(visible));
        if (empty) empty.hidden = visible > 0;
    };

    buttons.forEach((button) => button.addEventListener('click', () => selectOccasion(button.dataset.packageFilter)));
    const selected = buttons.find((button) => button.getAttribute('aria-pressed') === 'true');
    if (selected) selectOccasion(selected.dataset.packageFilter);
});

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
