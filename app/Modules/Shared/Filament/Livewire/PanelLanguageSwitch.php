<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Livewire;

use BezhanSalleh\FilamentLanguageSwitch\Http\Livewire\FilamentLanguageSwitch;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use Livewire\Attributes\On;

class PanelLanguageSwitch extends FilamentLanguageSwitch
{
    #[On('language-switched')]
    public function changeLocale($locale): void
    {
        abort_unless(in_array($locale, LanguageSwitch::make()->getLocales(), true), 422);
        LanguageSwitch::trigger($locale);
        $previous = url()->previous();
        if (parse_url($previous, PHP_URL_HOST) !== request()->getHost()) {
            $previous = url('/admin');
        }

        // Replace the explicit locale query so it cannot override the new selection.
        $this->redirect(url()->query($previous, ['lang' => $locale]));
    }
}
