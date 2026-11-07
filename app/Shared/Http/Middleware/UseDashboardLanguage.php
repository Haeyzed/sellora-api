<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Translation\Translator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers staff and platform admins in the language their dashboard asks for in its Accept-Language header, among every language the application is translated into.
 *
 * Only messages follow it: errors, validation and the texts of our own
 * (permission labels, for example). The store's content (product names,
 * promotion titles, consent wording) is read in the store's language as
 * before, because only the translator's language is set, never the
 * application's (config app.locale), which content is read in. A regional
 * language such as fr-CA falls back to fr; anything else to the default.
 */
final readonly class UseDashboardLanguage
{
    /**
     * The request attribute holding the language chosen, so the store language can't replace it for messages.
     */
    public const string ATTRIBUTE = 'dashboard_language';

    public function __construct(private Translator $translator) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->preferredBy($request);
        $request->attributes->set(self::ATTRIBUTE, $locale);
        $this->translator->setLocale($locale);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    /**
     * The first language the client asks for that the application has, by itself or by its base language, or the default.
     */
    private function preferredBy(Request $request): string
    {
        $available = self::availableLocales();

        foreach ($request->getLanguages() as $requested) {
            $exact = strtolower(str_replace('_', '-', $requested));

            foreach ([$exact, explode('-', $exact)[0]] as $candidate) {
                if (in_array($candidate, $available, true)) {
                    return $candidate;
                }
            }
        }

        // Not app.locale: the store language may already have set that to the store's content language.
        return config()->string('app.fallback_locale');
    }

    /**
     * Every language the application is translated into: the folders of lang/.
     *
     * @return list<string>
     */
    public static function availableLocales(): array
    {
        return array_map(static fn (string $folder): string => strtolower(basename($folder)), glob(lang_path('*'), GLOB_ONLYDIR) ?: []);
    }
}
