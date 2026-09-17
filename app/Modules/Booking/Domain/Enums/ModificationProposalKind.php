<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Enums;

use Filament\Support\Contracts\HasLabel;

enum ModificationProposalKind: string implements HasLabel
{
    case AddItem = 'add_item';
    case RemoveItem = 'remove_item';
    case ChangeQuantity = 'change_quantity';
    case ChangePrice = 'change_price';
    case ChangeSlot = 'change_slot';
    case AddSurcharge = 'add_surcharge';
    case AddNote = 'add_note';

    public function label(): string
    {
        return $this->getLabel();
    }

    public function getLabel(): string
    {
        return __("booking.proposal_kind.{$this->value}");
    }
}
