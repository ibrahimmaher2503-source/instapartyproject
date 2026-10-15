<section
    class="ip-booking-saved-views"
    data-booking-monitor-saved-views
    data-user-id="{{ $userId }}"
    data-storage-key="instaparty.admin.{{ $pageSlug }}.views.{{ $userId }}.v1"
    data-label-saved="{{ __('booking.filters.view_saved') }}"
    data-label-applied="{{ __('booking.filters.view_applied') }}"
    data-label-deleted="{{ __('booking.filters.view_deleted') }}"
    data-label-unavailable="{{ __('booking.filters.view_unavailable') }}"
    aria-labelledby="ip-booking-saved-views-heading"
>
    <h2 id="ip-booking-saved-views-heading">{{ __('booking.filters.saved_views') }}</h2>
    <form data-saved-view-form>
        <label for="ip-booking-saved-view-name">{{ __('booking.filters.view_name') }}</label>
        <input id="ip-booking-saved-view-name" name="name" type="text" maxlength="60" required>
        <button type="submit">{{ __('booking.filters.save_view') }}</button>
    </form>
    <ul data-saved-view-items aria-live="polite"></ul>
    <p data-saved-view-empty>{{ __('booking.filters.no_saved_views') }}</p>
    <p data-saved-view-status role="status" aria-live="polite"></p>
</section>

@once
    <style>
        .ip-booking-saved-views { display: grid; gap: .5rem; padding: 1rem; border: 1px solid rgb(var(--gray-200)); border-radius: .875rem; background: var(--ip-admin-surface, white); }
        .ip-booking-saved-views h2 { margin: 0; color: rgb(var(--gray-700)); font-size: .9375rem; font-weight: 700; }
        .ip-booking-saved-views form { display: flex; flex-wrap: wrap; align-items: end; gap: .5rem; }
        .ip-booking-saved-views label { display: grid; flex: 1 1 12rem; gap: .25rem; color: rgb(var(--gray-600)); font-size: .75rem; font-weight: 600; }
        .ip-booking-saved-views input { min-height: 2.5rem; padding: .5rem .625rem; border: 1px solid rgb(var(--gray-300)); border-radius: .5rem; background: white; color: rgb(var(--gray-900)); }
        .ip-booking-saved-views button { min-height: 2.5rem; padding: .5rem .75rem; border-radius: .5rem; color: white; background: rgb(var(--primary-600)); font-size: .8125rem; font-weight: 650; }
        .ip-booking-saved-views button:hover { background: rgb(var(--primary-700)); }
        .ip-booking-saved-views :is(input, button):focus-visible { outline: 2px solid rgb(var(--primary-600)); outline-offset: 2px; }
        .ip-booking-saved-views ul { display: flex; flex-wrap: wrap; gap: .375rem; margin: 0; padding: 0; list-style: none; }
        .ip-booking-saved-views li { display: inline-flex; align-items: center; gap: .25rem; border: 1px solid rgb(var(--gray-200)); border-radius: .5rem; }
        .ip-booking-saved-views li button { min-height: 2rem; padding: .375rem .5rem; border-radius: .375rem; color: rgb(var(--primary-700)); background: transparent; }
        .ip-booking-saved-views li button:hover { background: rgb(var(--primary-50)); }
        .ip-booking-saved-views [data-delete-view] { color: rgb(var(--danger-700)); }
        .ip-booking-saved-views p { margin: 0; color: rgb(var(--gray-500)); font-size: .75rem; }
        .dark .ip-booking-saved-views { border-color: rgb(var(--gray-700)); }
        .dark .ip-booking-saved-views h2 { color: rgb(var(--gray-200)); }
        .dark .ip-booking-saved-views input { border-color: rgb(var(--gray-600)); background: rgb(var(--gray-900)); color: rgb(var(--gray-100)); }
        .dark .ip-booking-saved-views li { border-color: rgb(var(--gray-700)); }
        .dark .ip-booking-saved-views li button:hover { background: rgb(var(--primary-900) / .45); }
        .dark .ip-booking-saved-views p { color: rgb(var(--gray-400)); }
    </style>
    <script>
        (() => {
            const start = () => {
                const root = document.querySelector('[data-booking-monitor-saved-views]');
                if (!root || root.dataset.initialized) return;
                root.dataset.initialized = 'true';

                const storageKey = root.dataset.storageKey;
                const form = root.querySelector('[data-saved-view-form]');
                const nameInput = root.querySelector('input[name="name"]');
                const list = root.querySelector('[data-saved-view-items]');
                const empty = root.querySelector('[data-saved-view-empty]');
                const status = root.querySelector('[data-saved-view-status]');
                let views = [];

                try {
                    const saved = JSON.parse(localStorage.getItem(storageKey) || '[]');
                    views = Array.isArray(saved) ? saved.filter((view) => typeof view?.name === 'string' && view.filters && typeof view.filters === 'object') : [];
                } catch {}

                const page = () => {
                    const id = root.closest('[wire\\:id]')?.getAttribute('wire:id');
                    return id ? window.Livewire?.find(id) : null;
                };

                const persist = () => {
                    try {
                        localStorage.setItem(storageKey, JSON.stringify(views));
                    } catch {}
                };

                const render = () => {
                    list.replaceChildren();
                    empty.hidden = views.length > 0;

                    for (const [index, savedView] of views.entries()) {
                        const item = document.createElement('li');
                        const apply = document.createElement('button');
                        apply.type = 'button';
                        apply.textContent = savedView.name;
                        apply.setAttribute('aria-label', `${savedView.name}: {{ __('booking.filters.apply_view') }}`);
                        apply.addEventListener('click', async () => {
                            const livewire = page();
                            if (!livewire) {
                                status.textContent = root.dataset.labelUnavailable;
                                return;
                            }

                            await livewire.set('tableFilters', savedView.filters);
                            status.textContent = root.dataset.labelApplied;
                        });
                        item.append(apply);

                        const remove = document.createElement('button');
                        remove.type = 'button';
                        remove.dataset.deleteView = '';
                        remove.textContent = '×';
                        remove.setAttribute('aria-label', '{{ __('booking.filters.delete_view', ['name' => ':name']) }}'.replace(':name', savedView.name));
                        remove.addEventListener('click', () => {
                            views.splice(index, 1);
                            persist();
                            render();
                            status.textContent = root.dataset.labelDeleted;
                        });
                        item.append(remove);
                        list.append(item);
                    }
                };

                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    const name = nameInput.value.trim();
                    const livewire = page();
                    if (!name || !livewire) {
                        status.textContent = root.dataset.labelUnavailable;
                        return;
                    }

                    const filters = livewire.get('tableFilters') ?? {};
                    const view = { name, filters: JSON.parse(JSON.stringify(filters)) };
                    const existingIndex = views.findIndex((savedView) => savedView.name.toLocaleLowerCase() === name.toLocaleLowerCase());

                    if (existingIndex >= 0) views[existingIndex] = view;
                    else views.unshift(view);

                    views = views.slice(0, 10);
                    persist();
                    render();
                    status.textContent = root.dataset.labelSaved;
                    nameInput.value = '';
                });

                render();
            };

            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
            else start();
        })();
    </script>
@endonce
