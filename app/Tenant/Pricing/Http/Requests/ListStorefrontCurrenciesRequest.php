<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Requests;

use App\Shared\Http\PageListRequest;

/**
 * A page of the currencies customers can buy in. Anyone may ask.
 */
final class ListStorefrontCurrenciesRequest extends PageListRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function page(): int
    {
        $page = $this->validated('page');

        return is_numeric($page) ? (int) $page : 1;
    }
}
