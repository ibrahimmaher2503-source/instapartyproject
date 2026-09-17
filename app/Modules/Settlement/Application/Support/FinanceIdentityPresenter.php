<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Support;

use App\Modules\Settlement\Domain\Models\LedgerTransactionGroup;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * Converts persisted finance references into safe, localized display values.
 *
 * Financial tables keep numeric foreign keys for joins and reconciliation. They
 * must never be used as user-facing identity values.
 */
final class FinanceIdentityPresenter
{
    public static function reference(?string $type, int|string|null $id, ?Model $resolved = null): string
    {
        $identity = self::data($type, $id, $resolved);

        if ($identity === null) {
            return self::legacy();
        }

        $parts = [$identity['type']];

        if ($identity['name'] !== null) {
            $parts[] = $identity['name'];
        }

        $parts[] = $identity['public_id'];

        return implode(' · ', $parts);
    }

    /**
     * @return array{type: string, name: string|null, public_id: string}|null
     */
    public static function data(?string $type, int|string|null $id, ?Model $resolved = null): ?array
    {
        $key = self::key($type);

        if ($key === null || ! is_numeric($id) || (int) $id < 1) {
            return null;
        }

        $target = $resolved ?? self::resolve($key, (int) $id);

        if ($target === null) {
            return null;
        }

        $publicId = $target->getAttribute('public_id');

        if (! is_string($publicId) || trim($publicId) === '') {
            return null;
        }

        return [
            'type' => self::label($key),
            'name' => self::name($key, $target),
            'public_id' => $publicId,
        ];
    }

    public static function typeLabel(?string $type): string
    {
        $key = self::key($type);

        return $key === null ? self::legacy() : self::label($key);
    }

    private static function legacy(): string
    {
        return __('settlement.identity.legacy_unknown');
    }

    private static function label(string $key): string
    {
        return __('settlement.identity.types.'.$key);
    }

    private static function key(?string $type): ?string
    {
        $value = strtolower(trim((string) $type));
        $basename = strtolower(class_basename($value));

        return match (true) {
            $value === 'customer', $value === 'user', $basename === 'user' => 'customer',
            $value === 'vendor', $value === 'vendor_profile', $basename === 'vendorprofile' => 'vendor',
            $value === 'wallet' || $basename === 'wallet' => 'wallet',
            in_array($value, ['wallet_ledger', 'walletledgerentry'], true)
                || $basename === 'walletledgerentry' => 'wallet_ledger',
            in_array($value, ['ledger_transaction_group', 'ledger_transaction_groups'], true)
                || $basename === 'ledgertransactiongroup' => 'ledger_transaction_groups',
            in_array($value, ['booking', 'bookings'], true) || $basename === 'booking' => 'booking',
            in_array($value, ['booking_item', 'booking_items'], true) || $basename === 'bookingitem' => 'booking_item',
            in_array($value, ['payment', 'payments'], true) || $basename === 'payment' => 'payment',
            in_array($value, ['refund', 'refunds'], true) || $basename === 'refund' => 'refund',
            in_array($value, ['withdrawal', 'withdrawals'], true) || $basename === 'withdrawal' => 'withdrawal',
            in_array($value, ['commission', 'commissions'], true) || $basename === 'commission' => 'commission',
            default => null,
        };
    }

    private static function resolve(string $key, int $id): ?Model
    {
        $classes = [
            'wallet' => Wallet::class,
            'wallet_ledger' => WalletLedgerEntry::class,
            'ledger_transaction_groups' => LedgerTransactionGroup::class,
            'customer' => 'App\\Modules\\Identity\\Domain\\Models\\User',
            'vendor' => 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile',
            'booking' => 'App\\Modules\\Booking\\Domain\\Models\\Booking',
            'booking_item' => 'App\\Modules\\Booking\\Domain\\Models\\BookingItem',
            'payment' => 'App\\Modules\\Payments\\Domain\\Models\\Payment',
            'refund' => 'App\\Modules\\Payments\\Domain\\Models\\Refund',
            'withdrawal' => 'App\\Modules\\Settlement\\Domain\\Models\\Withdrawal',
            'commission' => 'App\\Modules\\Settlement\\Domain\\Models\\Commission',
        ];
        $class = $classes[$key] ?? null;

        if ($class === null || ! class_exists($class) || ! is_a($class, Model::class, true)) {
            return null;
        }

        /** @var Model $model */
        $model = app($class);

        return $model->newQuery()->find($id);
    }

    private static function name(string $key, Model $target): ?string
    {
        $attribute = $key === 'vendor' ? 'business_name' : 'name';
        $value = $target->getAttribute($attribute);

        if (is_array($value)) {
            $locale = app('translator')->getLocale();
            $value = $value[$locale] ?? $value['en'] ?? $value['ar'] ?? null;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
