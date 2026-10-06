<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The common parent of every business rule error, such as "this order can no longer be cancelled".
 *
 * Each error has a stable code that API clients can rely on, and a message
 * translated from lang/<locale>/errors.php. Business errors are expected
 * outcomes, not bugs, so they are never sent to error tracking.
 */
abstract class DomainException extends RuntimeException implements ShouldntReport
{
    /**
     * @param  array<string, string|int|float>  $messageParameters  Values substituted into the translated message.
     */
    public function __construct(private readonly array $messageParameters = [], ?Throwable $previous = null)
    {
        parent::__construct($this->errorCode(), 0, $previous);
    }

    /**
     * The stable, machine-readable code clients use to recognise this error, such as "order_cannot_be_cancelled".
     */
    abstract public function errorCode(): string;

    /**
     * The HTTP status the API responds with. Business rule violations are 422 unless a subclass says otherwise.
     */
    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }

    /**
     * The human-readable message, in the request's language.
     */
    public function translatedMessage(): string
    {
        $message = __('errors.'.$this->errorCode(), $this->messageParameters);

        return is_string($message) ? $message : $this->errorCode();
    }

    /**
     * Errors tied to specific input fields, in the same shape as validation errors.
     *
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return [];
    }
}
