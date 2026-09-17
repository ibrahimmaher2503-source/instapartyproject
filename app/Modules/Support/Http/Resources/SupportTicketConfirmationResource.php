<?php

declare(strict_types=1);

namespace App\Modules\Support\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketConfirmationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', 'en');

        $messages = [
            'en' => 'Your support request has been received. We will get back to you shortly.',
            'ar' => 'تم استلام طلب الدعم الخاص بك. سنتواصل معك قريباً.',
        ];

        return [
            'public_id' => $this->public_id,
            'reference' => $this->reference(),
            'status' => $this->status->value,
            'message' => $messages[$locale] ?? $messages['en'],
        ];
    }

    private function reference(): string
    {
        return sprintf('TK-%s-%05d', $this->created_at->format('Y'), $this->id);
    }
}
