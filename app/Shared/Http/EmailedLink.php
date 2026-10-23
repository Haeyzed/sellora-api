<?php

declare(strict_types=1);

namespace App\Shared\Http;

use LogicException;

/**
 * Builds a link emailed into a frontend app from its URL template in config, with every secret (a one-time token, and the email it is for) in the URL fragment.
 *
 * Browsers never send the fragment to any server, so the secret stays out of
 * server and proxy logs, browser history sync and Referer headers; the
 * frontend reads it and sends it to the API in the request body. A template
 * that would put a secret anywhere before "#" is refused rather than sent.
 */
final class EmailedLink
{
    /**
     * @param  array<string, string>  $secrets  Placeholders that must sit in the fragment, such as {token} and {email}, with their values; each is URL-encoded.
     * @param  array<string, string>  $placeholders  Any others, such as {domain}, put in as they are.
     */
    public static function build(string $template, array $secrets, array $placeholders = []): string
    {
        $fragmentStart = strpos($template, '#');

        foreach (array_keys($secrets) as $placeholder) {
            $position = strpos($template, $placeholder);

            if ($position === false || $fragmentStart === false || $position < $fragmentStart) {
                throw new LogicException("The emailed link template must put {$placeholder} in the URL fragment (after \"#\"), never in its path or query string.");
            }
        }

        return strtr($template, [...array_map(rawurlencode(...), $secrets), ...$placeholders]);
    }
}
