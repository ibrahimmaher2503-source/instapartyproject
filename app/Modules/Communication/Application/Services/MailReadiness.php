<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Services;

use App\Modules\Communication\Domain\Contracts\EmailTransportReadiness;

final class MailReadiness implements EmailTransportReadiness
{
    public function isConfigured(): bool
    {
        return self::configured();
    }

    public static function configured(): bool
    {
        $sender = config('mail.from.address');
        if (! is_string($sender) || ! filter_var($sender, FILTER_VALIDATE_EMAIL)
            || str_ends_with($sender, '@example.com')) {
            return false;
        }
        $mailer = (string) config('mail.default');

        // shortcut: composite mailers are blocked; add recursive checks when an approved setup requires real-only fallbacks.
        return match (config('mail.mailers.'.$mailer.'.transport')) {
            'smtp' => filled(config('mail.mailers.'.$mailer.'.url'))
                || (filled(config('mail.mailers.'.$mailer.'.host'))
                    && ! in_array(config('mail.mailers.'.$mailer.'.host'), ['127.0.0.1', 'localhost'], true)
                    && (int) config('mail.mailers.'.$mailer.'.port') > 0),
            'ses', 'ses-v2' => filled(config('services.ses.key')) && filled(config('services.ses.secret')),
            'postmark' => filled(config('services.postmark.key')),
            'resend' => filled(config('services.resend.key')),
            'sendmail' => filled(config('mail.mailers.'.$mailer.'.path')),
            default => false,
        };
    }
}
