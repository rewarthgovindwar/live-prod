<?php

namespace App\Exceptions;

use App\Services\CriticalErrorAlertService;
use App\Services\ErrorReferenceService;
use App\Support\ErrorPageContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [
        AuthenticationException::class,
        AuthorizationException::class,
        ValidationException::class,
        ModelNotFoundException::class,
    ];

    protected $dontFlash = [
        'password',
        'password_confirmation',
        'current_password',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function report(Throwable $e): void
    {
        if ($this->shouldReport($e) && ! $e instanceof ValidationException && ! $this->isMaintenanceException($e)) {
            try {
                $referenceId = app(ErrorReferenceService::class)->log($e);
            } catch (Throwable) {
                // Never let logging failures break reporting
            }

            try {
                if ($this->isServerError($e) && ! $this->isMaintenanceException($e)) {
                    $referenceId = app(ErrorReferenceService::class)->lastReferenceId()
                        ?? app(ErrorReferenceService::class)->generate();
                    app(CriticalErrorAlertService::class)->dispatch($e, $referenceId);
                }
            } catch (Throwable) {
                // Alert pipeline must never break error reporting
            }
        }

        try {
            parent::report($e);
        } catch (Throwable) {
            // Fallback if logging stack itself fails (e.g. permission issues)
        }
    }

    public function render($request, Throwable $e): Response
    {
        if ($e instanceof TokenMismatchException) {
            return $this->handleSessionExpired($request);
        }

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return $this->renderJsonException($request, $e);
        }

        if ($e instanceof ValidationException) {
            return parent::render($request, $e);
        }

        if ($e instanceof AuthenticationException) {
            return $this->unauthenticated($request, $e);
        }

        if ($e instanceof AuthorizationException) {
            return $this->errorPageResponse('errors.403', [], 403);
        }

        if ($e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException) {
            return $this->errorPageResponse('errors.404', ['exception' => $e], 404);
        }

        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 503) {
            return $this->errorPageResponse('errors.503', [], 503);
        }

        if ($this->isServerError($e)) {
            try {
                $referenceId = $this->logAndGetReference($e);
                session()->flash('error_reference_id', $referenceId);

                return $this->errorPageResponse('errors.500', [
                    'exception' => $e,
                    'referenceId' => $referenceId,
                ], 500);
            } catch (Throwable) {
                return $this->fallbackErrorPageResponse(500);
            }
        }

        if ($this->shouldUseInstitutionalFallback($e)) {
            return $this->fallbackErrorPageResponse(
                $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500
            );
        }

        return parent::render($request, $e);
    }

    protected function unauthenticated($request, AuthenticationException $exception): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'Please sign in to continue.',
                'reference_id' => null,
            ], 401);
        }

        return redirect()->guest($exception->redirectTo($request) ?? route('login'));
    }

    protected function handleSessionExpired(Request $request): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'Your session has expired for security reasons. Please sign in again.',
                'reference_id' => null,
                'redirect' => url('/login'),
            ], 419);
        }

        return $this->errorPageResponse('errors.419', [], 419);
    }

    protected function errorPageResponse(string $view, array $data = [], int $status = 500): Response
    {
        return response()->view($view, array_merge(
            ErrorPageContext::resolve($data['exception'] ?? null),
            $data
        ), $status);
    }

    protected function renderJsonException(Request $request, Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Please check the form and correct the highlighted fields.',
                'errors' => $e->errors(),
                'reference_id' => null,
            ], 422);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'Please sign in to continue.',
                'reference_id' => null,
            ], 401);
        }

        if ($e instanceof AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
                'reference_id' => null,
            ], 403);
        }

        if ($e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'We couldn\'t find the page or resource you\'re looking for.',
                'reference_id' => null,
            ], 404);
        }

        if ($e instanceof TooManyRequestsHttpException) {
            $retryAfter = $e->getHeaders()['Retry-After'] ?? null;

            return response()->json([
                'success' => false,
                'message' => 'Too many requests detected. Please wait a moment and try again.',
                'reference_id' => null,
                'retry_after' => $retryAfter ? (int) $retryAfter : null,
            ], 429);
        }

        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 503) {
            return response()->json([
                'success' => false,
                'message' => 'The site is temporarily unavailable for maintenance. Please try again in a few minutes.',
                'reference_id' => null,
            ], 503);
        }

        $statusCode = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

        if ($statusCode >= 500) {
            $referenceId = $this->logAndGetReference($e);

            return response()->json([
                'success' => false,
                'message' => app(ErrorReferenceService::class)->userMessage(500),
                'reference_id' => $referenceId,
            ], $statusCode);
        }

        $message = $e instanceof HttpExceptionInterface && ! config('app.debug')
            ? app(ErrorReferenceService::class)->userMessage($statusCode)
            : ($e->getMessage() ?: app(ErrorReferenceService::class)->userMessage($statusCode));

        return response()->json([
            'success' => false,
            'message' => $message,
            'reference_id' => null,
        ], $statusCode);
    }

    protected function logAndGetReference(Throwable $e): string
    {
        $service = app(ErrorReferenceService::class);

        if ($existing = $service->lastReferenceId()) {
            return $existing;
        }

        try {
            return $service->log($e);
        } catch (Throwable) {
            return $service->generate();
        }
    }

    protected function isServerError(Throwable $e): bool
    {
        if ($this->isMaintenanceException($e)) {
            return false;
        }

        if ($e instanceof \ErrorException) {
            $message = strtolower($e->getMessage());

            return str_contains($message, 'permission denied')
                || str_contains($message, 'failed to open stream')
                || str_contains($message, 'no such file');
        }

        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode() >= 500;
        }

        return ! ($e instanceof ValidationException)
            && ! ($e instanceof AuthenticationException)
            && ! ($e instanceof AuthorizationException)
            && ! ($e instanceof NotFoundHttpException)
            && ! ($e instanceof ModelNotFoundException)
            && ! ($e instanceof TokenMismatchException);
    }

    protected function isMaintenanceException(Throwable $e): bool
    {
        return $e instanceof HttpExceptionInterface && $e->getStatusCode() === 503;
    }

    protected function shouldUseInstitutionalFallback(Throwable $e): bool
    {
        if (config('app.debug')) {
            return false;
        }

        return $e instanceof \ErrorException || $this->isServerError($e);
    }

    protected function fallbackErrorPageResponse(int $status = 500): Response
    {
        $view = match (true) {
            $status === 404 => 'errors.404',
            $status === 403 => 'errors.403',
            $status === 419 => 'errors.419',
            $status === 503 => 'errors.503',
            default => 'errors.500',
        };

        try {
            return $this->errorPageResponse($view, [], $status);
        } catch (Throwable) {
            return response(
                '<!DOCTYPE html><html><head><meta charset="utf-8"><title>DNYANDA ERP</title></head>'
                .'<body style="font-family:sans-serif;text-align:center;padding:3rem">'
                .'<h1>Something unexpected happened</h1>'
                .'<p>Please try again later.</p></body></html>',
                $status
            );
        }
    }

    protected function smartRecoveryRedirect(Request $request, string $url, string $message): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'reference_id' => null,
                'redirect' => $url,
            ], 404);
        }

        return redirect($url)->with('message-danger', $message);
    }

}
