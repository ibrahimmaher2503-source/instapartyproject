<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Concerns;

use App\Modules\Catalog\Domain\Models\Service;
use Brick\Math\Exception\MathException;
use Brick\Money\Money;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

trait HasServicePriceInput
{
    protected static function servicePriceField(): TextInput
    {
        // The form displays major units; DTOs and persistence still receive integer minor units.
        return TextInput::make('base_price_minor')
            ->label(__('catalog.base_price'))
            ->required()->inputMode('decimal')->extraInputAttributes(['dir' => 'ltr'])
            ->suffix(fn (?Service $record): string => $record?->base_price_currency ?? 'EGP')
            ->helperText(fn (?Service $record): string => __('catalog.price_major_helper', ['currency' => $record?->base_price_currency ?? 'EGP']))
            ->rules([fn (?Service $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                if ((! is_string($value) && ! is_int($value)) || ! preg_match('/\A[0-9]+(?:\.[0-9]{1,2})?\z/', (string) $value)) {
                    $fail(__('catalog.price_major_invalid'));

                    return;
                }

                try {
                    Money::of((string) $value, $record?->base_price_currency ?? 'EGP')->getMinorAmount()->toInt();
                } catch (MathException) {
                    $fail(__('catalog.price_major_invalid'));
                }
            }])
            ->formatStateUsing(fn (int|string|null $state, ?Service $record): ?string => $state === null
                ? null : (string) Money::ofMinor($state, $record?->base_price_currency ?? 'EGP')->getAmount())
            ->dehydrateStateUsing(fn (string|int $state, ?Service $record): int => Money::of((string) $state, $record?->base_price_currency ?? 'EGP')->getMinorAmount()->toInt());
    }

    protected static function servicePriceColumn(): TextColumn
    {
        return TextColumn::make('base_price_minor')
            ->label(__('catalog.base_price'))
            ->formatStateUsing(fn (Service $record): string => $record->base_price->formatTo(app()->getLocale() === 'ar' ? 'ar_EG' : 'en_GB'));
    }
}
