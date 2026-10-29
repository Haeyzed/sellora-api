<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Tenant\Orders\Concerns\ActsAsGuestOrderHolder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A guest asking to return units of their order, with its access token in the X-Order-Token header (never the URL). Without it the order is not found (404).
 */
final class GuestReturnRequest extends FormRequest
{
    use ActsAsGuestOrderHolder;
    use ValidatesReturnRequest;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->returnRules();
    }
}
