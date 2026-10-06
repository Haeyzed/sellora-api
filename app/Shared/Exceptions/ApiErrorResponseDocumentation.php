<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApi;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Documents every error response in the API documentation in the shape ApiErrorRenderer really returns: {message, code, errors}.
 *
 * Covers business errors (each DomainException with its own status and code)
 * and the standard 401, 403, 404 and 422 errors, replacing Scramble's
 * defaults, which show Laravel's shape without the "code" field. Responses are
 * inline rather than shared components, so two errors with the same status
 * (a validation error and "invalid_credentials", both 422) are merged and
 * both documented.
 */
final class ApiErrorResponseDocumentation extends ExceptionToResponseExtension
{
    /**
     * @var array<class-string<Throwable>, array{status: int, code: string, description: string}>
     */
    private const array STANDARD_ERRORS = [
        ValidationException::class => ['status' => 422, 'code' => 'validation_failed', 'description' => 'Validation failed'],
        AuthenticationException::class => ['status' => 401, 'code' => 'unauthenticated', 'description' => 'Unauthenticated'],
        AuthorizationException::class => ['status' => 403, 'code' => 'forbidden', 'description' => 'Forbidden'],
        ModelNotFoundException::class => ['status' => 404, 'code' => 'not_found', 'description' => 'Not found'],
        NotFoundHttpException::class => ['status' => 404, 'code' => 'not_found', 'description' => 'Not found'],
    ];

    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && ($type->isInstanceOf(DomainException::class) || $this->standardErrorFor($type) !== null);
    }

    public function toResponse(Type $type): ?Response
    {
        if (! $type instanceof ObjectType) {
            return null;
        }

        $standardError = $this->standardErrorFor($type);

        if ($standardError !== null) {
            return $this->errorResponse($standardError['status'], $standardError['code'], $standardError['description']);
        }

        [$status, $code] = $this->statusAndCodeOfBusinessError($type->name);

        return $this->errorResponse($status, $code, Str::headline(class_basename($type->name)));
    }

    /**
     * @return array{status: int, code: string, description: string}|null
     */
    private function standardErrorFor(ObjectType $type): ?array
    {
        foreach (self::STANDARD_ERRORS as $exceptionClass => $standardError) {
            if ($type->isInstanceOf($exceptionClass)) {
                return $standardError;
            }
        }

        return null;
    }

    /**
     * Reads a business error's status and code without its constructor arguments. Codes that depend on runtime state are left open.
     *
     * @return array{0: int, 1: string|null}
     */
    private function statusAndCodeOfBusinessError(string $exceptionClass): array
    {
        if (! class_exists($exceptionClass) || ! is_subclass_of($exceptionClass, DomainException::class)) {
            return [422, null];
        }

        $reflection = new ReflectionClass($exceptionClass);

        if ($reflection->isAbstract()) {
            return [422, null];
        }

        $exception = $reflection->newInstanceWithoutConstructor();

        try {
            return [$exception->status(), $exception->errorCode()];
        } catch (Throwable) {
            return [$exception->status(), null];
        }
    }

    private function errorResponse(int $status, ?string $code, string $description): Response
    {
        $codeType = (new OpenApi\StringType)->setDescription('Stable, machine-readable error code.');

        if ($code !== null) {
            $codeType->const($code);
        }

        $messageType = (new OpenApi\StringType)->setDescription('Human-readable message, translated into the request\'s language.');

        if ($code !== null && is_string($translatedMessage = __('errors.'.$code)) && $translatedMessage !== 'errors.'.$code) {
            $messageType->example($translatedMessage);
        }

        $errorsType = (new OpenApi\ObjectType)
            ->setDescription('Messages per input field; empty unless specific fields are invalid.')
            ->additionalProperties((new OpenApi\ArrayType)->setItems(new OpenApi\StringType));

        $body = (new OpenApi\ObjectType)
            ->addProperty('message', $messageType)
            ->addProperty('code', $codeType)
            ->addProperty('errors', $errorsType)
            ->setRequired(['message', 'code', 'errors']);

        return Response::make($status)
            ->setDescription($description)
            ->setContent('application/json', Schema::fromType($body));
    }
}
