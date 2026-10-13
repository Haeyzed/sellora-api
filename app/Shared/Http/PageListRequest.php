<?php

declare(strict_types=1);

namespace App\Shared\Http;

/**
 * A list paged by number, with a total and a last page, for small and medium lists that dashboards show as tables.
 *
 * The controller pages it with `paginate($request->perPage())`.
 */
abstract class PageListRequest extends PaginatedListRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
