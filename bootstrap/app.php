<?php

use App\Modules\Booking\Domain\Exceptions\BookingNotEligibleForFulfillmentException;
use App\Modules\Booking\Domain\Exceptions\FulfillmentIssueAlreadyOpenException;
use App\Modules\Booking\Domain\Exceptions\LaneNotSupportedForTypeException;
use App\Modules\Booking\Domain\Exceptions\PaymentNotCapturedException;
use App\Modules\Booking\Domain\Exceptions\RefundInProgressException;
use App\Modules\Identity\Http\Middleware\SetLocaleMiddleware;
use App\Modules\Shared\Http\Middleware\AddSecurityHeaders;
use App\Modules\Shared\Http\Middleware\SetRequestTraceIdMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->prepend(SetRequestTraceIdMiddleware::class);
        $middleware->append(AddSecurityHeaders::class);

        // Resolve locale from Accept-Language header (or ?lang= param) on all API routes.
        $middleware->appendToGroup('api', SetLocaleMiddleware::class);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'locale' => SetLocaleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($response->getStatusCode() < 500 || $request->expectsJson() || str_starts_with($request->path(), 'api/')) {
                return $response;
            }

            $referenceId = (string) (Context::get('trace_id') ?: Str::uuid());
            Context::add('trace_id', $referenceId);

            return response()->view('errors.500', compact('referenceId'), $response->getStatusCode())
                ->header('X-Trace-Id', $referenceId);
        });

        // API routes have no login page — always return 401 JSON.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (str_starts_with($request->path(), 'api/')) {
                return response()->json([
                    'data' => null,
                    'meta' => null,
                    'errors' => [['code' => 'unauthenticated', 'message' => $e->getMessage()]],
                ], 401);
            }

            return null;
        });

        $exceptions->render(function (PaymentNotCapturedException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'data' => null,
                'meta' => null,
                'errors' => [['code' => PaymentNotCapturedException::CODE, 'message' => $e->getMessage()]],
            ], 422);
        });

        $exceptions->render(function (BookingNotEligibleForFulfillmentException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'data' => null,
                'meta' => null,
                'errors' => [['code' => $e->errorCode, 'message' => $e->getMessage()]],
            ], 422);
        });

        $exceptions->render(function (RefundInProgressException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'data' => null,
                'meta' => null,
                'errors' => [['code' => RefundInProgressException::CODE, 'message' => $e->getMessage()]],
            ], 422);
        });

        $exceptions->render(function (LaneNotSupportedForTypeException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'data' => null,
                'meta' => null,
                'errors' => [['code' => LaneNotSupportedForTypeException::CODE, 'message' => $e->getMessage()]],
            ], 422);
        });

        $exceptions->render(function (FulfillmentIssueAlreadyOpenException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'data' => null,
                'meta' => null,
                'errors' => [['code' => FulfillmentIssueAlreadyOpenException::CODE, 'message' => $e->getMessage()]],
            ], 409);
        });

        $unexpectedApiResponse = static function (Request $request, int $status = 500) {
            $traceId = (string) (Context::get('trace_id') ?: Str::uuid());
            Context::add('trace_id', $traceId);

            return response()->json([
                'data' => null,
                'meta' => ['reference_id' => $traceId],
                'errors' => [['code' => 'unexpected_error', 'message' => __('shared::shared.errors.unexpected_failure')]],
            ], $status)->header('X-Trace-Id', $traceId);
        };

        // Keep known HTTP messages for client errors; redact server failure details.
        $exceptions->render(function (HttpException $e, Request $request) use ($unexpectedApiResponse) {
            if (! str_starts_with($request->path(), 'api/')) {
                return null;
            }

            $status = $e->getStatusCode();
            if ($status >= 500) {
                return $unexpectedApiResponse($request, $status);
            }

            $message = $e instanceof NotFoundHttpException && $e->getPrevious() instanceof ModelNotFoundException
                ? __('shared::shared.errors.not_found')
                : ($e->getMessage() ?: (Response::$statusTexts[$status] ?? 'Error'));

            return response()->json([
                'data' => null,
                'meta' => null,
                'errors' => [['code' => 'http_error', 'message' => $message]],
            ], $status);
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($unexpectedApiResponse) {
            if (! str_starts_with($request->path(), 'api/')) {
                return null;
            }

            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || method_exists($e, 'render')) {
                return null;
            }

            return $unexpectedApiResponse($request);
        });
    })->create();
