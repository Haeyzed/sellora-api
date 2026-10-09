<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a product isn't ready for customers: it needs a name in the store's default language and at least one priced variant outside the trash.
 */
final class ProductCannotBePublishedException extends DomainException
{
    /**
     * @param  list<string>  $reasons  What is missing: "default_name", "priced_variant".
     */
    public function __construct(private readonly array $reasons)
    {
        parent::__construct();
    }

    public function errorCode(): string
    {
        return 'product_cannot_be_published';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }

    public function fieldErrors(): array
    {
        $errors = [];

        foreach ($this->reasons as $reason) {
            $message = __("errors.product_cannot_be_published_because.{$reason}");
            $errors[$reason === 'default_name' ? 'name' : 'variants'] = [is_string($message) ? $message : $reason];
        }

        return $errors;
    }
}
