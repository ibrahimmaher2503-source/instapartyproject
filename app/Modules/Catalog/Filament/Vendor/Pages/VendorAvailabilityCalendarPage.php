<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Pages;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceAvailabilityBlock;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use App\Modules\Shared\Application\Services\Concerns\RequiresApprovedVendor;
use App\Modules\Shared\Application\Services\VendorOperationalAccess;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;

class VendorAvailabilityCalendarPage extends Page
{
    use RequiresApprovedVendor;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'services';

    protected static string $view = 'vendor-portal.pages.vendor-availability-calendar';

    #[Locked]
    public string $month;

    public string $servicePublicId = '';

    public static function getNavigationLabel(): string
    {
        return __('catalog::vendor_calendar.title');
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function mount(): void
    {
        $this->month = CarbonImmutable::now($this->timezone())->format('Y-m');
        $this->servicePublicId = (string) ($this->servicesQuery()->value('public_id') ?? '');
    }

    public function previousMonth(): void
    {
        $this->month = CarbonImmutable::parse($this->month.'-01')->subMonth()->format('Y-m');
    }

    public function hydrate(): void
    {
        abort_unless(VendorOperationalAccess::allowed(), 403);
    }

    public function nextMonth(): void
    {
        $this->month = CarbonImmutable::parse($this->month.'-01')->addMonth()->format('Y-m');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previousMonth')->label(__('catalog::vendor_calendar.previous'))->action('previousMonth'),
            Action::make('nextMonth')->label(__('catalog::vendor_calendar.next'))->action('nextMonth'),
        ];
    }

    protected function getViewData(): array
    {
        $services = $this->servicesQuery()->get();
        $service = $this->servicePublicId === '' ? null : $this->servicesQuery()->where('public_id', $this->servicePublicId)->firstOrFail();
        $start = CarbonImmutable::parse($this->month.'-01', $this->timezone());
        $gridStart = $start->startOfWeek(CarbonImmutable::SUNDAY);
        $gridEnd = $start->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);
        $intervals = collect();

        if ($service !== null) {
            $blocks = ServiceAvailabilityBlock::query()->where('service_id', $service->id)
                ->where('starts_at', '<', $gridEnd->addDay()->startOfDay()->utc())
                ->where('ends_at', '>', $gridStart->utc())->get();
            $reservations = ServiceInventoryReservation::query()->where('service_id', $service->id)->blocksInventory()
                ->where('reserved_starts_at', '<', $gridEnd->addDay()->startOfDay()->utc())
                ->where('reserved_ends_at', '>', $gridStart->utc())->get();
            $intervals = $blocks->map(fn ($block): array => [
                'starts' => $block->starts_at->toImmutable()->setTimezone($this->timezone()),
                'ends' => $block->ends_at->toImmutable()->setTimezone($this->timezone()),
                'label' => __('catalog::vendor_calendar.blocked'),
            ])->concat($reservations->map(fn ($reservation): array => [
                'starts' => $reservation->reserved_starts_at->toImmutable()->setTimezone($this->timezone()),
                'ends' => $reservation->reserved_ends_at->toImmutable()->setTimezone($this->timezone()),
                'label' => __('catalog::vendor_calendar.reserved'),
            ]));
        }

        $days = [];
        for ($date = $gridStart; $date->lte($gridEnd); $date = $date->addDay()) {
            $days[] = [
                'date' => $date,
                'current' => $date->format('Y-m') === $this->month,
                'intervals' => $intervals->filter(fn (array $interval): bool => $interval['starts']->lt($date->addDay())
                    && $interval['ends']->gt($date))->values(),
            ];
        }

        return [
            'services' => $services, 'service' => $service, 'days' => $days,
            'monthLabel' => $start->locale(app()->getLocale())->translatedFormat('F Y'),
            'timezone' => $this->timezone(),
            'weekdays' => collect(range(0, 6))->map(fn (int $offset): string => $gridStart->addDays($offset)->locale(app()->getLocale())->translatedFormat('D')),
        ];
    }

    private function servicesQuery(): Builder
    {
        abort_unless(VendorOperationalAccess::allowed(), 403);

        return Service::query()->where('vendor_profile_id', auth()->user()->vendorProfile->id)->where('product_type', ProductType::Rental)->orderBy('id');
    }

    private function timezone(): string
    {
        return auth()->user()->timezone ?? 'Africa/Cairo';
    }
}
