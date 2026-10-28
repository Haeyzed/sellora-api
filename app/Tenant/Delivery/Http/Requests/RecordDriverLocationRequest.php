<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A driver's app sending where they are, for one of their own deliveries under way.
 */
final class RecordDriverLocationRequest extends FormRequest
{
    use ActsAsDriver;

    public function authorize(): bool
    {
        $this->ownDelivery();

        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** Degrees, -90 to 90 (WGS 84); kept to 6 decimal places. */
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            /** Degrees, -180 to 180 (WGS 84); kept to 6 decimal places. */
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            /** How far off the point may be, in metres, as the phone reports it. */
            'accuracy_meters' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }

    public function latitude(): string
    {
        return (string) $this->input('latitude');
    }

    public function longitude(): string
    {
        return (string) $this->input('longitude');
    }

    public function accuracyMeters(): ?int
    {
        return $this->filled('accuracy_meters') ? $this->integer('accuracy_meters') : null;
    }
}
