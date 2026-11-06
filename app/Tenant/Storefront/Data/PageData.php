<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Data;

/**
 * A page's texts and slug as staff send them; null leaves a field as it is (or, for a new page, empty).
 */
final readonly class PageData
{
    /**
     * @param  array<string, string|null>|null  $title  By language; a language set to null loses its text.
     * @param  array<string, string|null>|null  $body  By language: HTML, cleaned before it is saved.
     * @param  array<string, string|null>|null  $seoTitle  By language.
     * @param  array<string, string|null>|null  $seoDescription  By language.
     * @param  list<string>  $removes  The translated fields sent as null, removed in every language.
     */
    public function __construct(
        public ?array $title = null,
        public ?array $body = null,
        public ?array $seoTitle = null,
        public ?array $seoDescription = null,
        public ?string $slug = null,
        public array $removes = [],
    ) {}
}
