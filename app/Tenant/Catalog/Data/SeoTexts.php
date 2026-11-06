<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Data;

/**
 * A product's, category's or brand's search engine title and description as staff send them; null leaves one as it is.
 */
final readonly class SeoTexts
{
    /**
     * @param  array<string, string|null>|null  $title  By language; a language set to null loses its text.
     * @param  array<string, string|null>|null  $description  By language; a language set to null loses its text.
     * @param  list<string>  $removes  The fields sent as null ("seo_title", "seo_description"), removed in every language.
     */
    public function __construct(
        public ?array $title = null,
        public ?array $description = null,
        public array $removes = [],
    ) {}
}
