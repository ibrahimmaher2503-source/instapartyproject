@if (session()->has('impersonator_id'))
    @livewire(\App\Modules\Shared\Filament\Vendor\Widgets\ImpersonationBannerWidget::class)
@endif
