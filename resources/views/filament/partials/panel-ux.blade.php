<script>
document.addEventListener('DOMContentLoaded', () => {
    const labels = @js(['actions' => __('shared::panel.actions')]);
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

});
</script>
