<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Pages;

use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use Brick\Money\Money;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DisputeOversightPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static string $view = 'settlement::filament.pages.dispute-oversight';

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settlement');
    }

    public static function getNavigationLabel(): string
    {
        return __('settlement.dispute_oversight');
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function formatMoney(mixed $minor, mixed $currency): string
    {
        $currency = is_string($currency) ? strtoupper($currency) : 'EGP';
        $currency = preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : 'EGP';

        return Money::ofMinor((int) $minor, $currency)->formatTo(app()->getLocale());
    }

    public function formatDate(mixed $value): string
    {
        return Carbon::parse((string) $value)
            ->locale(app()->getLocale())
            ->translatedFormat('d M Y · H:i');
    }

    public function refundStatusLabel(mixed $status): string
    {
        $status = $status instanceof RefundStatus ? $status : RefundStatus::tryFrom((string) $status);

        return $status?->label() ?? '—';
    }

    public function refundReasonLabel(mixed $reason): string
    {
        $reason = $reason instanceof RefundReasonCode ? $reason : RefundReasonCode::tryFrom((string) $reason);

        if ($reason === null) {
            return '—';
        }

        $key = 'refunds.reason_code.'.$reason->value;
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : '—';
    }

    public function localizedText(mixed $value, string $fallback = '—'): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : $value;
        }

        if (is_string($value)) {
            return trim($value) !== '' ? trim($value) : $fallback;
        }

        if (! is_array($value)) {
            return $fallback;
        }

        $locale = app()->getLocale();
        $text = $value[$locale] ?? $value['en'] ?? $value['ar'] ?? null;

        return is_string($text) && trim($text) !== '' ? trim($text) : $fallback;
    }

    public function getStats(): array
    {
        $pendingRefunds = DB::table('refunds')
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        $pendingWithdrawals = DB::table('withdrawals')
            ->where('status', 'pending')
            ->count();

        $totalDisputedMinor = (int) DB::table('refunds')
            ->whereIn('status', ['pending', 'processing'])
            ->sum('amount_minor');

        return [
            'pending_refunds' => $pendingRefunds,
            'pending_withdrawals' => $pendingWithdrawals,
            'total_disputed' => $totalDisputedMinor,
            'total_disputed_currency' => 'EGP',
        ];
    }

    public function getPendingRefunds(): Collection
    {
        return DB::table('refunds')
            ->join('payments', 'payments.id', '=', 'refunds.payment_id')
            ->whereIn('refunds.status', ['pending', 'processing'])
            ->select([
                'refunds.id',
                'refunds.public_id',
                'refunds.status',
                'refunds.amount_minor',
                'refunds.amount_currency',
                'refunds.reason_code',
                'refunds.reason_notes',
                'refunds.created_at',
                'payments.gateway_ref',
            ])
            ->orderByDesc('refunds.created_at')
            ->limit(50)
            ->get();
    }

    public function getPendingWithdrawals(): Collection
    {
        return DB::table('withdrawals')
            ->join('vendor_profiles', 'vendor_profiles.id', '=', 'withdrawals.vendor_profile_id')
            ->where('withdrawals.status', 'pending')
            ->select([
                'withdrawals.public_id',
                'withdrawals.status',
                'withdrawals.requested_amount_minor',
                'withdrawals.requested_amount_currency',
                'withdrawals.created_at',
                'vendor_profiles.business_name',
            ])
            ->orderByDesc('withdrawals.created_at')
            ->limit(50)
            ->get();
    }
}
