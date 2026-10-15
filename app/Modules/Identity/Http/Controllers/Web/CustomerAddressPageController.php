<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Geography\Domain\Contracts\GeographyRepository;
use App\Modules\Identity\Application\Actions\AddCustomerAddressAction;
use App\Modules\Identity\Application\Actions\DeleteCustomerAddressAction;
use App\Modules\Identity\Application\Actions\SetDefaultCustomerAddressAction;
use App\Modules\Identity\Application\Actions\UpdateCustomerAddressAction;
use App\Modules\Identity\Domain\Models\CustomerAddress;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Http\Requests\AddCustomerAddressRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CustomerAddressPageController
{
    public function index(Request $request): View
    {
        $geography = app(GeographyRepository::class);
        $addresses = $this->user($request)->customerAddresses()
            ->with('city')
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->paginate(10);
        $cities = $geography->activeGovernorates()
            ->load(['cities' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('id')])
            ->flatMap(fn ($governorate) => $governorate->cities)
            ->values();

        return view('storefront.account.addresses', compact('addresses', 'cities'));
    }

    public function store(AddCustomerAddressRequest $request, AddCustomerAddressAction $action): RedirectResponse
    {
        $action->execute($this->user($request), $request->validated());

        return $this->redirectToAddresses();
    }

    public function update(
        Request $request,
        string $addressPublicId,
        UpdateCustomerAddressAction $action,
        GeographyRepository $geography,
    ): RedirectResponse {
        $address = $this->ownedAddress($request, $addressPublicId);
        $data = $request->validate([
            'city_id' => ['sometimes', 'required', 'string', 'exists:cities,public_id'],
            'label' => ['sometimes', 'required', 'string', 'max:60'],
            'address_line' => ['sometimes', 'required', 'string', 'max:255'],
            'building' => ['sometimes', 'nullable', 'string', 'max:50'],
            'floor' => ['sometimes', 'nullable', 'string', 'max:20'],
            'apartment' => ['sometimes', 'nullable', 'string', 'max:20'],
            'landmark' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'recipient_name' => ['sometimes', 'required', 'string', 'max:120'],
            'recipient_phone_e164' => ['sometimes', 'required', 'string', 'max:20', 'regex:/^\\+[1-9]\\d{1,14}$/'],
        ]);

        if (isset($data['city_id'])) {
            $city = $geography->findCityByPublicId($data['city_id']);
            abort_unless($city !== null, 404);
            $data['city_id'] = $city->getKey();
        }

        $action->execute($address, $data);

        return $this->redirectToAddresses();
    }

    public function setDefault(
        Request $request,
        string $addressPublicId,
        SetDefaultCustomerAddressAction $action,
    ): RedirectResponse {
        $action->execute($this->ownedAddress($request, $addressPublicId));

        return $this->redirectToAddresses();
    }

    public function destroy(
        Request $request,
        string $addressPublicId,
        DeleteCustomerAddressAction $action,
    ): RedirectResponse {
        $action->execute($this->ownedAddress($request, $addressPublicId));

        return $this->redirectToAddresses();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function ownedAddress(Request $request, string $addressPublicId): CustomerAddress
    {
        return $this->user($request)->customerAddresses()
            ->where('public_id', $addressPublicId)
            ->firstOrFail();
    }

    private function redirectToAddresses(): RedirectResponse
    {
        return redirect()->route('storefront.account.addresses', ['locale' => app()->getLocale()]);
    }
}
