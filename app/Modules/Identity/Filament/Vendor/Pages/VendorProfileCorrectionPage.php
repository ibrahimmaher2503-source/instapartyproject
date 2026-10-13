<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Vendor\Pages;

use App\Modules\Identity\Application\Actions\VendorResubmitAfterChangesAction;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ChangesRequestedState;
use App\Modules\Shared\Application\DTOs\VendorProfileChangeRequestData;
use App\Modules\Shared\Application\DTOs\VendorProfileChangeRequestItemData;
use App\Modules\Shared\Domain\Contracts\VendorProfileChangeRequestReader;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class VendorProfileCorrectionPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'vendor-portal.pages.vendor-profile-correction';

    public ?array $data = [];

    /** This one correction route keeps vendor authentication but skips the panel's email-verification middleware. */
    public static function getRouteMiddleware(Panel $panel): string|array
    {
        $emailVerificationMiddleware = parent::getEmailVerifiedMiddleware($panel);

        return array_values(array_filter(
            Arr::wrap(parent::getRouteMiddleware($panel)),
            fn (string $middleware): bool => $middleware !== $emailVerificationMiddleware,
        ));
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User || ! $user->hasRole('vendor')) {
            return false;
        }

        $profile = VendorProfile::query()->where('user_id', $user->getKey())->first();

        return $profile !== null
            && $profile->approval_status instanceof ChangesRequestedState
            && app(VendorProfileChangeRequestReader::class)->findCurrentOpenForVendorProfile((int) $profile->getKey()) !== null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('identity::vendor_correction.title');
    }

    public function mount(): void
    {
        $profile = $this->currentVendorProfile();
        $request = $this->currentChangeRequest($profile);
        $corrections = [];

        foreach ($request->items as $item) {
            $field = VendorResubmitAfterChangesAction::correctionField($item->fieldPath);

            if ($field !== null && $this->isUniqueRequestField($request, $item)) {
                $corrections[$item->publicId] = $this->currentValue($profile, $field);
            }
        }

        $this->form->fill(['corrections' => $corrections]);
    }

    public function form(Form $form): Form
    {
        $profile = $this->currentVendorProfile();
        $request = $this->currentChangeRequest($profile);
        $components = [];

        foreach ($request->items as $item) {
            $field = VendorResubmitAfterChangesAction::correctionField($item->fieldPath);

            if ($field === null || ! $this->isUniqueRequestField($request, $item)) {
                $components[] = Placeholder::make('unsupported_'.$item->publicId)
                    ->label($item->fieldPath)
                    ->content($this->requestedChange($item));

                continue;
            }

            $component = $field['type'] === 'textarea'
                ? Textarea::make('corrections.'.$item->publicId)->rows(3)
                : TextInput::make('corrections.'.$item->publicId);

            $component
                ->label(__('identity::vendor_correction.field', ['field' => $item->fieldPath]))
                ->helperText(__('identity::vendor_correction.context', [
                    'requested' => $this->requestedChange($item),
                    'current' => $this->currentValue($profile, $field) ?: __('identity::vendor_correction.empty'),
                ]))
                ->required($this->isRequired($profile, $field));

            if (isset($field['max_length'])) {
                $component->maxLength($field['max_length']);
            }

            if ($field['type'] === 'url' && $component instanceof TextInput) {
                $component
                    ->url()
                    ->validationMessages(['url' => __('identity::vendor_correction.invalid_value')]);
            }

            $components[] = $component;
        }

        return $form
            ->schema($components)
            ->statePath('data');
    }

    public function hasUnsupportedFields(): bool
    {
        $profile = $this->currentVendorProfile();
        $request = $this->currentChangeRequest($profile);

        if ($request->items === []) {
            return true;
        }

        foreach ($request->items as $item) {
            if (VendorResubmitAfterChangesAction::correctionField($item->fieldPath) === null
                || ! $this->isUniqueRequestField($request, $item)) {
                return true;
            }
        }

        return false;
    }

    public function canSubmitCorrections(): bool
    {
        return ! $this->hasUnsupportedFields();
    }

    public function save(): void
    {
        abort_unless($this->canSubmitCorrections(), 403);

        $profile = $this->currentVendorProfile();
        $request = $this->currentChangeRequest($profile);
        $formState = $this->form->getState();
        $corrections = [];

        foreach ($request->items as $item) {
            $corrections[$item->fieldPath] = (string) ($formState['corrections'][$item->publicId] ?? '');
        }

        app(VendorResubmitAfterChangesAction::class)->execute(
            $profile,
            array_map(static fn (VendorProfileChangeRequestItemData $item): string => $item->publicId, $request->items),
            [],
            null,
            (string) Str::uuid(),
            $corrections,
        );

        Notification::make()
            ->title(__('identity::vendor_correction.saved'))
            ->success()
            ->send();

        $user = Filament::auth()->user();
        $destination = $user instanceof User && $user->hasVerifiedEmail()
            ? VendorApprovalStatusPage::getUrl(panel: 'vendor')
            : (Filament::getEmailVerificationPromptUrl() ?? url('/vendor-portal/login'));

        $this->redirect($destination);
    }

    private function currentVendorProfile(): VendorProfile
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User && $user->hasRole('vendor'), 403);

        $profile = VendorProfile::query()->where('user_id', $user->getKey())->firstOrFail();
        abort_unless($profile->approval_status instanceof ChangesRequestedState, 403);

        return $profile;
    }

    private function currentChangeRequest(VendorProfile $profile): VendorProfileChangeRequestData
    {
        return app(VendorProfileChangeRequestReader::class)
            ->findCurrentOpenForVendorProfile((int) $profile->getKey()) ?? abort(403);
    }

    /** @param array{attribute: string, locale?: string, type: string, required: bool, required_for?: array<int, string>, max_length?: int} $field */
    private function currentValue(VendorProfile $profile, array $field): string
    {
        if (isset($field['locale'])) {
            return (string) $profile->getTranslation($field['attribute'], $field['locale'], false);
        }

        return (string) ($profile->getAttribute($field['attribute']) ?? '');
    }

    private function requestedChange(VendorProfileChangeRequestItemData $item): string
    {
        $locale = Filament::auth()->user()?->preferred_locale === 'ar' ? 'ar' : 'en';
        $requested = $locale === 'ar' ? $item->requestedChangeAr : $item->requestedChangeEn;

        $fallback = $locale === 'ar' ? $item->requestedChangeEn : $item->requestedChangeAr;

        return filled($requested) ? (string) $requested : (string) $fallback;
    }

    /** @param array{attribute: string, locale?: string, type: string, required: bool, required_for?: array<int, string>, max_length?: int} $field */
    private function isRequired(VendorProfile $profile, array $field): bool
    {
        return $field['required'] || in_array($profile->business_type?->value, $field['required_for'] ?? [], true);
    }

    private function isUniqueRequestField(VendorProfileChangeRequestData $request, VendorProfileChangeRequestItemData $item): bool
    {
        return count(array_filter(
            $request->items,
            static fn (VendorProfileChangeRequestItemData $requestItem): bool => $requestItem->fieldPath === $item->fieldPath,
        )) === 1;
    }
}
