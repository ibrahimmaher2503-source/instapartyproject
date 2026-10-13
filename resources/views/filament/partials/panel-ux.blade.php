<script>
document.addEventListener('keydown', event => {
    if (event.key !== 'Enter' || event.isComposing || !(event.target instanceof HTMLInputElement)) return;

    const input = event.target;
    const submitAction = input.closest('form')?.getAttribute('wire:submit');
    if (!input.closest('.fi-panel-admin') || !['save', 'create'].includes(submitAction)) return;

    // Keep keyboard interaction for buttons, search, selects and the date picker.
    if (['submit', 'button', 'checkbox', 'radio', 'search'].includes(input.type) || input.closest('.fi-ta, .fi-fo-select, .fi-fo-date-time-picker, [role="combobox"]') || input.hasAttribute('role')) return;

    event.preventDefault();
});
</script>
