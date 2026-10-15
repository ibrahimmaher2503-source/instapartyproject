<?php

declare(strict_types=1);

namespace App\Modules\Support\Http\Controllers\Web;

use App\Modules\Support\Application\Actions\SubmitSupportTicketAction;
use App\Modules\Support\Application\DTOs\SubmitSupportTicketDTO;
use App\Modules\Support\Http\Requests\SubmitSupportTicketRequest;
use App\Modules\Support\Infrastructure\Repositories\EloquentFaqRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class CustomerHelpController
{
    public function __construct(private readonly EloquentFaqRepository $faqs) {}

    public function __invoke(): View
    {
        return view('storefront.support.help', [
            'categories' => $this->faqs->getAllActiveCategories(),
        ]);
    }

    public function store(SubmitSupportTicketRequest $request, SubmitSupportTicketAction $action): RedirectResponse
    {
        $user = $request->user();
        $ticket = $action->execute(new SubmitSupportTicketDTO(
            subject: $request->string('subject')->toString(),
            body: $request->string('body')->toString(),
            bookingPublicId: null,
            userId: $user?->id,
            email: $request->validated('email') ?: $user?->email,
        ));

        return redirect()->route('storefront.support.help', ['locale' => $request->route('locale')])
            ->with('supportTicketReference', $ticket->public_id);
    }
}
