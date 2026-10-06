<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The common parent of every list endpoint's request, so no list can ever be fetched unbounded.
 *
 * Clients may choose a page size up to the configured maximum and pass the
 * cursor returned by the previous page. Subclasses add their own filters
 * with `[...parent::rules(), ...]`.
 */
abstract class PaginatedListRequest extends FormRequest
{
    /**
     * The paging rules every list shares.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config()->integer('api.pagination.max_per_page')],
            'cursor' => ['sometimes', 'string', 'max:512'],
        ];
    }

    /**
     * How many records the client asked for on one page, or the default page size.
     */
    public function perPage(): int
    {
        $requestedPageSize = $this->validated('per_page');

        return is_numeric($requestedPageSize)
            ? (int) $requestedPageSize
            : config()->integer('api.pagination.default_per_page');
    }
}
