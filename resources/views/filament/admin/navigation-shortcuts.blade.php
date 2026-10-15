<div
    class="ip-admin-nav-shortcuts"
    data-admin-nav-shortcuts
    data-storage-key="instaparty.admin.nav.{{ $userId }}.v1"
    data-add-label="{{ __('admin.nav.shortcuts.add_favorite', ['page' => ':page']) }}"
    data-remove-label="{{ __('admin.nav.shortcuts.remove_favorite', ['page' => ':page']) }}"
>
    <section aria-labelledby="ip-admin-favorites-heading">
        <h2 id="ip-admin-favorites-heading">{{ __('admin.nav.shortcuts.favorites') }}</h2>
        <ul data-favorite-items aria-live="polite"></ul>
        <p data-favorite-empty>{{ __('admin.nav.shortcuts.favorite_empty') }}</p>
    </section>
    <section aria-labelledby="ip-admin-recent-heading">
        <h2 id="ip-admin-recent-heading">{{ __('admin.nav.shortcuts.recent') }}</h2>
        <ul data-recent-items aria-live="polite"></ul>
        <p data-recent-empty>{{ __('admin.nav.shortcuts.recent_empty') }}</p>
        <button type="button" data-clear-recent>{{ __('admin.nav.shortcuts.clear_recent') }}</button>
    </section>
</div>

@once
    <style>
        .ip-admin-nav-shortcuts { display: grid; gap: .75rem; padding: 0 .625rem 1rem; color: rgb(var(--gray-700)); }
        .ip-admin-nav-shortcuts section + section { padding-top: .625rem; border-top: 1px solid rgb(var(--gray-200)); }
        .ip-admin-nav-shortcuts h2 { margin: 0 0 .25rem; color: rgb(var(--gray-500)); font-size: .6875rem; font-weight: 750; letter-spacing: .04em; text-transform: uppercase; }
        .ip-admin-nav-shortcuts ul { display: grid; gap: .125rem; margin: 0; padding: 0; list-style: none; }
        .ip-admin-nav-shortcuts p { margin: .25rem 0 0; color: rgb(var(--gray-500)); font-size: .75rem; }
        .ip-admin-shortcut-link { display: block; overflow: hidden; padding: .375rem .5rem; border-radius: .5rem; color: inherit; font-size: .8125rem; text-overflow: ellipsis; white-space: nowrap; }
        .ip-admin-shortcut-link:hover { background: rgb(var(--gray-100)); }
        .ip-admin-nav-shortcuts [data-clear-recent] { min-height: 2rem; margin-top: .25rem; color: rgb(var(--primary-700)); font-size: .75rem; }
        .ip-admin-shortcut-link:focus-visible, .ip-admin-nav-favorite:focus-visible, .ip-admin-nav-shortcuts [data-clear-recent]:focus-visible { outline: 2px solid rgb(var(--primary-600)); outline-offset: 2px; }
        .fi-sidebar-item:has(> .ip-admin-nav-favorite) { position: relative; }
        .fi-sidebar-item > .fi-sidebar-item-button { padding-inline-end: 2.5rem !important; }
        .ip-admin-nav-favorite { position: absolute; inset-inline-end: .25rem; top: 50%; display: grid; width: 2rem; height: 2rem; place-items: center; transform: translateY(-50%); border-radius: .375rem; color: rgb(var(--gray-500)); font-size: 1.125rem; }
        .ip-admin-nav-favorite:hover, .ip-admin-nav-favorite[aria-pressed="true"] { color: rgb(var(--warning-700)); background: rgb(var(--warning-50)); }
        .dark .ip-admin-nav-shortcuts { color: rgb(var(--gray-200)); }
        .dark .ip-admin-nav-shortcuts section + section { border-color: rgb(var(--gray-700)); }
        .dark .ip-admin-nav-shortcut-link:hover { background: rgb(var(--gray-800)); }
        .dark .ip-admin-nav-favorite:hover, .dark .ip-admin-nav-favorite[aria-pressed="true"] { color: rgb(var(--warning-200)); background: rgb(var(--warning-900) / .35); }
        @media (min-width: 1024px) {
            .fi-panel-admin .fi-sidebar:not(.fi-sidebar-open) .ip-admin-nav-shortcuts,
            .fi-panel-admin .fi-sidebar:not(.fi-sidebar-open) .ip-admin-nav-favorite { display: none; }
        }
    </style>
    <script>
        (() => {
            const start = () => {
                const root = document.querySelector('[data-admin-nav-shortcuts]');
                const sidebar = document.querySelector('.fi-panel-admin .fi-sidebar-nav');

                if (!root || !sidebar || root.dataset.initialized) return;
                root.dataset.initialized = 'true';

                const storageKey = root.dataset.storageKey;
                const addLabel = root.dataset.addLabel;
                const removeLabel = root.dataset.removeLabel;
                const favoriteList = root.querySelector('[data-favorite-items]');
                const recentList = root.querySelector('[data-recent-items]');
                const favoriteEmpty = root.querySelector('[data-favorite-empty]');
                const recentEmpty = root.querySelector('[data-recent-empty]');
                let state = { favorites: [], recent: [] };

                try {
                    const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
                    state.favorites = Array.isArray(saved.favorites) ? saved.favorites : [];
                    state.recent = Array.isArray(saved.recent) ? saved.recent : [];
                } catch {}

                const normalizePath = (href) => {
                    try {
                        const url = new URL(href, window.location.origin);

                        if (url.origin !== window.location.origin || (url.pathname !== '/admin' && !url.pathname.startsWith('/admin/'))) return null;

                        return url.pathname;
                    } catch {
                        return null;
                    }
                };

                const getLinks = () => [...sidebar.querySelectorAll('a.fi-sidebar-item-button[href]')]
                    .map((anchor) => ({
                        href: normalizePath(anchor.href),
                        label: anchor.querySelector('.fi-sidebar-item-label')?.textContent?.trim() || '',
                        item: anchor.closest('.fi-sidebar-item'),
                    }))
                    .filter((entry) => entry.href && entry.label && entry.item);

                const persist = () => {
                    try {
                        localStorage.setItem(storageKey, JSON.stringify(state));
                    } catch {}
                };

                const renderList = (list, empty, paths, available) => {
                    list.replaceChildren();
                    const entries = [...new Set(paths)].map((path) => available.get(path)).filter(Boolean);
                    empty.hidden = entries.length > 0;

                    for (const entry of entries.slice(0, 5)) {
                        const item = document.createElement('li');
                        const link = document.createElement('a');
                        link.className = 'ip-admin-shortcut-link';
                        link.href = entry.href;
                        link.textContent = entry.label;
                        item.append(link);
                        list.append(item);
                    }
                };

                const render = () => {
                    const links = getLinks();
                    const available = new Map(links.map((entry) => [entry.href, entry]));
                    state.favorites = [...new Set(state.favorites.map(normalizePath).filter((path) => path && available.has(path)))].slice(0, 5);
                    state.recent = [...new Set(state.recent.map(normalizePath).filter((path) => path && available.has(path)))].slice(0, 5);

                    for (const entry of links) {
                        let button = entry.item.querySelector(':scope > .ip-admin-nav-favorite');

                        if (!button) {
                            button = document.createElement('button');
                            button.type = 'button';
                            button.className = 'ip-admin-nav-favorite';
                            button.dataset.adminNavFavorite = '';
                            button.addEventListener('click', (event) => {
                                event.preventDefault();
                                event.stopPropagation();
                                const path = button.dataset.href;
                                state.favorites = state.favorites.includes(path)
                                    ? state.favorites.filter((favorite) => favorite !== path)
                                    : [path, ...state.favorites].slice(0, 5);
                                persist();
                                render();
                            });
                            entry.item.append(button);
                        }

                        const isFavorite = state.favorites.includes(entry.href);
                        button.dataset.href = entry.href;
                        button.setAttribute('aria-pressed', String(isFavorite));
                        button.setAttribute('aria-label', (isFavorite ? removeLabel : addLabel).replace(':page', entry.label));
                        button.textContent = isFavorite ? '★' : '☆';
                    }

                    renderList(favoriteList, favoriteEmpty, state.favorites, available);
                    renderList(recentList, recentEmpty, state.recent, available);
                    persist();
                };

                const addRecent = (href) => {
                    const path = normalizePath(href);
                    if (!path || !getLinks().some((entry) => entry.href === path)) return;

                    state.recent = [path, ...state.recent.filter((recent) => recent !== path)].slice(0, 5);
                    persist();
                };

                sidebar.addEventListener('click', (event) => {
                    const anchor = event.target.closest('a.fi-sidebar-item-button[href]');
                    if (anchor) addRecent(anchor.href);
                }, true);
                root.addEventListener('click', (event) => {
                    const shortcut = event.target.closest('a.ip-admin-shortcut-link[href]');
                    if (shortcut) addRecent(shortcut.href);

                    const clear = event.target.closest('[data-clear-recent]');
                    if (!clear) return;
                    state.recent = [];
                    persist();
                    render();
                });

                const currentPage = getLinks().find((entry) => entry.href === normalizePath(window.location.href));
                if (currentPage) addRecent(currentPage.href);
                render();
            };

            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
            else start();
        })();
    </script>
@endonce
