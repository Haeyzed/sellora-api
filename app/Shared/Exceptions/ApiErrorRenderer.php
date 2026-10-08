<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Turns every error into the platform's single JSON error shape, so API clients can handle failures the same way everywhere.
 *
 * The shape is {"message": "...", "code": "...", "errors": {...}}: a translated
 * message for people, a stable code for programs, and field errors for
 * validation. Internal details (stack traces, SQL, file paths) are only
 * included when debugging is switched on, which is never the case in production.
 */
final class ApiErrorRenderer
{
    /**
     * Builds the error response for an exception.
     */
    public function render(Throwable $exception): Response
    {
        return match (true) {
            $exception instanceof HttpResponseException => $exception->getResponse(),
            $exception instanceof DomainException => $this->respond(
                $exception->errorCode(),
                $exception->translatedMessage(),
                $exception->status(),
                $exception->fieldErrors(),
            ),
            $exception instanceof ValidationException => $this->respondWithCode(
                'validation_failed',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $exception->errors(),
            ),
            $exception instanceof AuthenticationException => $this->respondWithCode('unauthenticated', Response::HTTP_UNAUTHORIZED),
            $exception instanceof TenantCouldNotBeIdentifiedException => $this->respondWithCode('store_not_found', Response::HTTP_NOT_FOUND),
            $exception instanceof HttpExceptionInterface => $this->respondToHttpException($exception),
            default => $this->respondToUnexpectedError($exception),
        };
    }

    private function respondToHttpException(HttpExceptionInterface $exception): JsonResponse
    {
        $status = $exception->getStatusCode();
        $response = $this->respondWithCode($this->codeForStatus($status), $status);
        $response->headers->add($exception->getHeaders());

        return $response;
    }

    private function respondToUnexpectedError(Throwable $exception): JsonResponse
    {
        $response = $this->respondWithCode('server_error', Response::HTTP_INTERNAL_SERVER_ERROR);

        if (! config()->boolean('app.debug')) {
            return $response;
        }

        $response->setData([
            ...(array) $response->getData(true),
            'debug' => [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ],
        ]);

        return $response;
    }

    private function codeForStatus(int $status): string
    {
        return match ($status) {
            Response::HTTP_BAD_REQUEST => 'bad_request',
            Response::HTTP_UNAUTHORIZED => 'unauthenticated',
            Response::HTTP_FORBIDDEN => 'forbidden',
            Response::HTTP_NOT_FOUND => 'not_found',
            Response::HTTP_METHOD_NOT_ALLOWED => 'method_not_allowed',
            Response::HTTP_CONFLICT => 'conflict',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'validation_failed',
            Response::HTTP_TOO_MANY_REQUESTS => 'too_many_requests',
            Response::HTTP_SERVICE_UNAVAILABLE => 'service_unavailable',
            default => $status >= Response::HTTP_INTERNAL_SERVER_ERROR ? 'server_error' : 'http_error',
        };
    }

    /**
     * @param  array<string, array<int, string>>  $fieldErrors
     */
    private function respondWithCode(string $code, int $status, array $fieldErrors = []): JsonResponse
    {
        $message = __('errors.'.$code);

        return $this->respond($code, is_string($message) ? $message : $code, $status, $fieldErrors);
    }

    /**
     * @param  array<string, array<int, string>>  $fieldErrors
     */
    private function respond(string $code, string $message, int $status, array $fieldErrors): JsonResponse
    {
        return new JsonResponse([
            'message' => $message,
            'code' => $code,
            'errors' => $fieldErrors === [] ? new stdClass : $fieldErrors,
        ], $status);
    }
}
