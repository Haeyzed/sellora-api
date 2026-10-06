<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Landlord\Legal\Enums\LegalDocumentType;
use App\Landlord\Legal\Models\LegalDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A draft legal document version.
 *
 * @extends Factory<LegalDocument>
 */
final class LegalDocumentFactory extends Factory
{
    protected $model = LegalDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => LegalDocumentType::TermsOfService,
            'version' => fake()->unique()->numerify('v#.#.##'),
            'title' => fake()->sentence(3),
            'body' => fake()->paragraphs(3, asText: true),
        ];
    }

    /**
     * A version published and in force since yesterday.
     */
    public function inForce(): self
    {
        return $this->state(['published_at' => now()->subDay(), 'effective_at' => now()->subDay()]);
    }

    public function ofType(LegalDocumentType $type): self
    {
        return $this->state(['type' => $type]);
    }
}
