<div
    x-data="{ visible: false }"
    x-on:trust-badge-save-unknown.window="visible = true"
    x-on:trust-badge-save-confirmed.window="visible = false"
    x-cloak
    x-show="visible"
    role="alert"
    class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200"
>
    {{ __('trust-safety::trust-safety.badges.save_confirmation_unknown') }}
</div>

@script
<script>
    $wire.$hook('commit', ({ commit, succeed, fail }) => {
        const isBadgeCreation = (commit.calls ?? []).some(({ method }) => ['create', 'createAnother'].includes(method))

        if (! isBadgeCreation) {
            return
        }

        fail(() => window.dispatchEvent(new CustomEvent('trust-badge-save-unknown')))
    })
</script>
@endscript
