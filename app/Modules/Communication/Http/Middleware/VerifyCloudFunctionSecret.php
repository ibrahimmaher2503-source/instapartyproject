<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the internal chat-mirror endpoint. The Firestore onMessageCreated Cloud
 * Function sends the shared secret in X-Cloud-Function-Secret. Constant-time compared
 * against config('services.cloud_function.secret').
 */
class VerifyCloudFunctionSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.cloud_function.secret', '');
        $provided = (string) $request->header('X-Cloud-Function-Secret', '');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            abort(401, 'Invalid cloud function secret.');
        }

        return $next($request);
    }
}
