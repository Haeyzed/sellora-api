<?php

declare(strict_types=1);

namespace App\Shared\Http;

/**
 * A list paged by cursor, next and previous only with no total, for large or fast-growing lists.
 *
 * Clients pass the cursor returned by the previous page. The controller pages
 * it with `cursorPaginate($request->perPage())`.
 */
abstract class CursorListRequest extends PaginatedListRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'cursor' => ['sometimes', 'string', 'max:512'],
        ];
    }
}
