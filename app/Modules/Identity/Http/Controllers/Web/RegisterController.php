<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Identity\Application\Actions\RegisterCustomerAction;
use App\Modules\Identity\Http\Requests\RegisterCustomerRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class RegisterController
{
    public function show(): View
    {
        return view('storefront.auth.register');
    }

    /**
     * Reuses the API's RegisterCustomerRequest unchanged — same rules, same DTO, same
     * Action (which assigns the `customer` role). Laravel renders validation failures
     * as a redirect-back for web and JSON for the API, so one FormRequest serves both.
     *
     * Outside debug mode, registration does not start a session. It mirrors the API contract,
     * where register returns no token and phone verification is what authenticates
     * (VerifyPhoneAction is the step that mints one). Logging in here would also bounce
     * the user straight off the guest-only verify page.
     */
    public function register(RegisterCustomerRequest $request, RegisterCustomerAction $action): RedirectResponse
    {
        $user = $action->execute($request->toDTO());

        if (config('app.debug')) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
            $request->session()->forget('auth.verify_phone');

            return redirect()->intended(route('storefront.home'));
        }

        // Kept in the session rather than the URL so the phone number is not logged
        // in access logs or leaked via Referer.
        $request->session()->put('auth.verify_phone', $user->phone_e164);

        return redirect()->route('storefront.auth.verify');
    }
}
