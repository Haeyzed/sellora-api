<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Returns\Enums\ReturnResolution;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff changing how a return is settled: "refund", or "exchange" with the variant to send in place of every returned line. Needs the returns.manage permission.
 */
final class ChangeReturnResolutionRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', OrderReturn::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            /** "refund" or "exchange". */
            'resolution' => ['required', 'string', Rule::enum(ReturnResolution::class)],
            /** For an exchange: every line of the return, once each, with the variant to send in its place. Left out for a refund. */
            'lines' => ['sometimes', 'array', 'min:1', 'max:100'],
            /** The order line's ID. */
            'lines.*.line' => ['required', 'string', 'max:26', 'distinct'],
            /** The ID of the variant to send in place of the line's units; it must be for sale and in stock. */
            'lines.*.replacement' => ['required', 'string', 'max:26'],
        ];
    }

    public function resolution(): ReturnResolution
    {
        return ReturnResolution::from($this->string('resolution')->value());
    }

    /**
     * @return array<string, string> Variant public IDs, by order line public ID.
     */
    public function replacements(): array
    {
        $replacements = [];

        foreach ((array) $this->validated('lines', []) as $line) {
            /** @var array{line: string, replacement: string} $line */
            $replacements[$line['line']] = $line['replacement'];
        }

        return $replacements;
    }
}
