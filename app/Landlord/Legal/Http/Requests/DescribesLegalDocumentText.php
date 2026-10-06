<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The version label, title and text every legal document version has.
 *
 * @mixin FormRequest
 */
trait DescribesLegalDocumentText
{
    /**
     * @return array<string, list<string>>
     */
    protected function textRules(): array
    {
        return [
            /** A label unique for the document, such as "2026-10". */
            'version' => ['required', 'string', 'max:32'],
            'title' => ['required', 'string', 'max:255'],
            /** The full text, in Markdown. */
            'body' => ['required', 'string', 'max:500000'],
        ];
    }

    public function version(): string
    {
        return trim($this->string('version')->value());
    }

    public function title(): string
    {
        return trim($this->string('title')->value());
    }

    public function body(): string
    {
        return $this->string('body')->value();
    }
}
