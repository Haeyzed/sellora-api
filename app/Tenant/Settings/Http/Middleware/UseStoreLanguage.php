<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Http\Middleware;

use App\Tenant\Settings\StoreLocales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers in the language the client asks for in its Accept-Language header, when the store publishes in it, or in the store's default language.
 *
 * Translated texts, amounts and messages then follow that language, and the
 * Content-Language response header says which one was used.
 */
final readonly class UseStoreLanguage
{
    public function __construct(private StoreLocales $storeLocales) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->storeLocales->preferredBy($request);
        App::setLocale($locale);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }
}
