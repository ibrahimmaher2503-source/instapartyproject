<x-filament-panels::page>
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">
            {{ __('catalog.vendor_categories.intro_heading') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('catalog.vendor_categories.intro_body') }}
        </p>

        <a
            href="{{ \App\Modules\Identity\Filament\Vendor\Pages\VendorApprovalStatusPage::getUrl() }}"
            class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
        >
            {{ __('catalog.vendor_categories.manage_link') }}
            <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4 rtl:rotate-180" />
        </a>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
