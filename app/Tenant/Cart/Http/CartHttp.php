<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Http;

use App\Tenant\Cart\CartToken;
use App\Tenant\Cart\Data\Shopper;
use Illuminate\Http\Request;

/**
 * What every cart request carries besides its body: who the shopper is (sign-in or X-Cart-Token), and the cart version a change was made from (If-Match).
 */
final class CartHttp
{
    /** An ETag as this API sends it ("7"), also accepted weak (W/"7") or bare (7). */
    private const string VERSION_FORMAT = '/^(?:W\/)?"?([1-9]\d{0,8})"?$/';

    public static function shopper(Request $request): Shopper
    {
        return Shopper::fromRequest($request);
    }

    /**
     * The guest cart token sent, signed in or not.
     */
    public static function guestToken(Request $request): ?string
    {
        $token = $request->headers->get(CartToken::HEADER);

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * The cart version a change was made from, or null when If-Match is missing or isn't a version.
     */
    public static function expectedVersion(Request $request): ?int
    {
        $ifMatch = trim((string) $request->headers->get('If-Match', ''));

        return preg_match(self::VERSION_FORMAT, $ifMatch, $matches) === 1 ? (int) $matches[1] : null;
    }
}
