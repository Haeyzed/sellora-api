<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Services;

use App\Shared\Media\StorefrontMediaHost;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Cleans a page's HTML down to what is safe to show shoppers: text formatting, headings, lists, quotes, links and the store's own images.
 *
 * Scripts, styles, frames, forms and event handlers never survive. Links
 * may go to https addresses, relative paths, mailto: and tel: only (the
 * menu links' rule); images only from where the store's own images are
 * served, never from another site. Pages are cleaned when saved and again
 * when served, so tightening these rules also cleans pages saved before;
 * VERSION changes with them, so cached pages are cleaned again.
 */
final readonly class PageHtml
{
    /** Changes whenever these rules change, so pages cached under the old rules are cleaned again. */
    public const int VERSION = 1;

    /** The longest page body cleaned, in characters, per language (the requests allow no more). */
    public const int MAX_LENGTH = 100_000;

    /** Elements kept with the attributes listed. */
    private const array ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'sub' => [], 'sup' => [], 'small' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'code' => [], 'pre' => [],
        'figure' => [], 'figcaption' => [], 'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'width', 'height'],
    ];

    /** Wrappers removed with their content kept, so text inside them is not lost. */
    private const array UNWRAPPED = ['div', 'span', 'section', 'article', 'header', 'footer', 'main', 'aside', 'h1', 'font', 'center'];

    public function __construct(private StorefrontMediaHost $storefrontMediaHost) {}

    /**
     * The HTML with everything not allowed removed.
     */
    public function clean(string $html): string
    {
        return (new HtmlSanitizer($this->config()))->sanitize($html);
    }

    private function config(): HtmlSanitizerConfig
    {
        ['scheme' => $mediaScheme, 'host' => $mediaHost] = $this->storefrontMediaHost->current();

        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(array_values(array_unique(['https', $mediaScheme])))
            ->allowMediaHosts([$mediaHost])
            ->allowRelativeMedias(false)
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(self::MAX_LENGTH);

        foreach (self::ALLOWED as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        foreach (self::UNWRAPPED as $element) {
            $config = $config->blockElement($element);
        }

        return $config;
    }
}
