<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Domain\Enums;

enum PromoRejectionReason: string
{
    case PromoExpired = 'PROMO_EXPIRED';
    case NotValidForVendor = 'NOT_VALID_FOR_VENDOR';
    case SingleUseRedeemed = 'SINGLE_USE_REDEEMED';
    case MinOrderNotMet = 'MIN_ORDER_NOT_MET';
    case InvalidCode = 'INVALID_CODE';
    case UsageCapReached = 'USAGE_CAP_REACHED';

    public function labelEn(): string
    {
        return match ($this) {
            self::PromoExpired => 'This promo code has expired.',
            self::NotValidForVendor => 'This promo code is not valid for this vendor.',
            self::SingleUseRedeemed => 'This promo code has already been used.',
            self::MinOrderNotMet => 'Your order total does not meet the minimum required for this code.',
            self::InvalidCode => 'This promo code is invalid.',
            self::UsageCapReached => 'This promo code has reached its usage limit.',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::PromoExpired => 'انتهت صلاحية رمز الخصم.',
            self::NotValidForVendor => 'رمز الخصم غير صالح لهذا البائع.',
            self::SingleUseRedeemed => 'تم استخدام رمز الخصم من قبل.',
            self::MinOrderNotMet => 'إجمالي طلبك لا يلبي الحد الأدنى المطلوب لهذا الرمز.',
            self::InvalidCode => 'رمز الخصم غير صالح.',
            self::UsageCapReached => 'وصل رمز الخصم إلى حد الاستخدام.',
        };
    }
}
