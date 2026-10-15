<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers\Web;

use App\Modules\Catalog\Domain\Contracts\PublishedServiceInquiryTargetReader;
use App\Modules\Communication\Application\Actions\CreateVendorInquiryAction;
use App\Modules\Communication\Application\Actions\SendVendorInquiryMessageAction;
use App\Modules\Communication\Domain\Models\VendorInquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CustomerVendorInquiryController
{
    public function create(string $servicePublicId, PublishedServiceInquiryTargetReader $serviceTargets): View
    {
        $target = $serviceTargets->find($servicePublicId);
        abort_if($target === null, 404);

        return view('communication::storefront.inquiries.create', [
            'target' => $target,
            'locale' => app()->getLocale(),
        ]);
    }

    public function store(
        Request $request,
        string $servicePublicId,
        CreateVendorInquiryAction $createInquiry,
    ): RedirectResponse {
        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $customer = $request->user();
        $inquiry = $createInquiry->execute(
            $servicePublicId,
            (int) $customer->getAuthIdentifier(),
            (string) $customer->name,
            (string) $validated['body'],
        );

        return redirect()->route('storefront.inquiries.show', [
            'locale' => app()->getLocale(),
            'inquiryPublicId' => $inquiry->public_id,
        ]);
    }

    public function index(Request $request): View
    {
        $inquiries = VendorInquiry::query()
            ->forCustomer((int) $request->user()->getAuthIdentifier())
            ->withCount('messages')
            ->latest('updated_at')
            ->paginate(15);

        return view('communication::storefront.inquiries.index', [
            'inquiries' => $inquiries,
            'locale' => app()->getLocale(),
        ]);
    }

    public function show(Request $request, string $inquiryPublicId): View
    {
        $inquiry = VendorInquiry::query()
            ->forCustomer((int) $request->user()->getAuthIdentifier())
            ->where('public_id', $inquiryPublicId)
            ->with('messages')
            ->firstOrFail();

        return view('communication::storefront.inquiries.show', [
            'inquiry' => $inquiry,
            'locale' => app()->getLocale(),
        ]);
    }

    public function reply(
        Request $request,
        string $inquiryPublicId,
        SendVendorInquiryMessageAction $sendMessage,
    ): RedirectResponse {
        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $sendMessage->asCustomer(
            $inquiryPublicId,
            (int) $request->user()->getAuthIdentifier(),
            (string) $validated['body'],
        );

        return redirect()->route('storefront.inquiries.show', [
            'locale' => app()->getLocale(),
            'inquiryPublicId' => $inquiryPublicId,
        ]);
    }
}
