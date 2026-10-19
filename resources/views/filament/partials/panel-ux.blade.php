<script>
document.addEventListener('DOMContentLoaded', () => {
    const labels = @js(['favorites' => __('shared::panel.favorites'), 'recent' => __('shared::panel.recent'), 'favorite' => __('shared::panel.favorite'), 'actions' => __('shared::panel.actions')]);
    const syncTables = () => {
        document.querySelectorAll('.fi-panel-admin .fi-ta-table').forEach(table => {
            const headings = [...table.querySelectorAll('thead tr:last-child th')].map(th => th.innerText.trim());
            table.querySelectorAll('tbody tr.fi-ta-row').forEach(row => {
                [...row.children].forEach((cell, index) => {
                    if (cell.tagName !== 'TD' || cell.colSpan > 1) return;
                    cell.dataset.mobileLabel = headings[index] || (cell.querySelector('.fi-ta-actions') ? labels.actions : '');
                });
            });
        });
    };
    syncTables();
    document.addEventListener('livewire:navigated', syncTables);
    let scheduled = false;
    new MutationObserver(() => {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(() => { syncTables(); scheduled = false; });
    }).observe(document.body, { childList: true, subtree: true });

    document.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.isComposing && event.target.matches('.fi-panel-admin form input:not([type="submit"]):not([type="button"])')) {
            // Search/select controls keep Enter; ordinary edit fields require the visible save action.
            if (!event.target.closest('.fi-ta') && !event.target.hasAttribute('role') && !event.target.closest('[role="combobox"]')) event.preventDefault();
        }
    });

    const nav = document.querySelector('.fi-panel-admin .fi-sidebar-nav');
    if (!nav || nav.querySelector('.ip-navigation-shortcuts')) return;
    const key = 'instaparty-admin-navigation-{{ auth()->id() }}';
    let saved = { favorites: [], recent: [] };
    try { saved = { ...saved, ...JSON.parse(localStorage.getItem(key) || '{}') }; } catch {}
    if (!Array.isArray(saved.favorites)) saved.favorites = [];
    if (!Array.isArray(saved.recent)) saved.recent = [];
    const links = [...nav.querySelectorAll('.fi-sidebar-item > a[href]')];
    const allowed = new Map(links.map(link => [new URL(link.href).pathname, link]));
    const current = location.pathname;
    if (allowed.has(current)) saved.recent = [current, ...saved.recent.filter(path => path !== current)].slice(0, 5);
    const shortcuts = document.createElement('div');
    shortcuts.className = 'ip-navigation-shortcuts';
    nav.prepend(shortcuts);
    const draw = () => {
        shortcuts.replaceChildren();
        ['favorites', 'recent'].forEach(type => {
            const paths = saved[type].filter(path => allowed.has(path));
            if (!paths.length) return;
            const heading = document.createElement('h2');
            heading.textContent = labels[type];
            shortcuts.append(heading);
            paths.forEach(path => {
                const link = allowed.get(path).cloneNode(true);
                link.removeAttribute('aria-current');
                link.className = 'ip-navigation-shortcut';
                shortcuts.append(link);
            });
        });
        try { localStorage.setItem(key, JSON.stringify(saved)); } catch {}
    };
    links.forEach(link => {
        const path = new URL(link.href).pathname;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'ip-navigation-favorite';
        button.textContent = '☆';
        button.setAttribute('aria-label', labels.favorite.replace(':page', link.innerText.trim()));
        button.setAttribute('aria-pressed', String(saved.favorites.includes(path)));
        button.addEventListener('click', () => {
            saved.favorites = saved.favorites.includes(path) ? saved.favorites.filter(value => value !== path) : [...saved.favorites, path];
            button.setAttribute('aria-pressed', String(saved.favorites.includes(path)));
            button.textContent = saved.favorites.includes(path) ? '★' : '☆';
            draw();
        });
        if (saved.favorites.includes(path)) button.textContent = '★';
        link.parentElement.append(button);
    });
    draw();
});
</script>
