<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers\Web;

use App\Modules\Booking\Application\Actions\CustomerDecideAlternativeVendorProposalAction;
use App\Modules\Booking\Application\DTOs\CustomerAlternativeVendorProposalDecisionDTO;
use App\Modules\Booking\Http\Requests\CustomerAlternativeVendorProposalDecisionRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;

final class CustomerAlternativeVendorProposalController
{
    public function decide(
        CustomerAlternativeVendorProposalDecisionRequest $request,
        string $bookingPublicId,
        string $interventionPublicId,
        CustomerDecideAlternativeVendorProposalAction $action,
    ): RedirectResponse {
        $data = $request->validated();
        try {
            $action->execute(new CustomerAlternativeVendorProposalDecisionDTO(
                bookingPublicId: $bookingPublicId,
                interventionPublicId: $interventionPublicId,
                customerId: (int) $request->user()->getAuthIdentifier(),
                decision: $data['decision'],
                offerHash: $data['offer_hash'],
                reason: $data['reason'] ?? null,
            ));
        } catch (DomainException $exception) {
            abort(409, $exception->getMessage());
        }

        return back()->with('vendor_proposal_decision_saved', true);
    }
}
