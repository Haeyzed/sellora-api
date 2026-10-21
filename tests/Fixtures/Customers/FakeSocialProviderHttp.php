<?php

declare(strict_types=1);

namespace Tests\Fixtures\Customers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * A sign-in provider's servers for tests: answers the token exchange and then who the person is (or refuses the code), and remembers what it was asked.
 */
final class FakeSocialProviderHttp
{
    /** @var list<array{request: RequestInterface}> */
    private array $history = [];

    public readonly Client $client;

    /**
     * @param  array<string, mixed>  $person  What the provider says about the person, such as Google's userinfo.
     * @param  array<string, mixed>  $tokenResponse  Extra fields in the token response, such as Apple's id_token.
     */
    public function __construct(array $person, bool $refuses = false, array $tokenResponse = [])
    {
        $answers = $refuses
            ? [new Response(400, ['Content-Type' => 'application/json'], (string) json_encode(['error' => 'invalid_grant']))]
            : [
                new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['access_token' => 'provider-access-token', 'token_type' => 'Bearer', 'expires_in' => 3600, ...$tokenResponse])),
                new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($person)),
            ];

        $stack = HandlerStack::create(new MockHandler($answers));
        $stack->push(Middleware::history($this->history));
        $this->client = new Client(['handler' => $stack]);
    }

    /**
     * The form fields of the token exchange.
     *
     * @return array<string, string>
     */
    public function tokenRequest(): array
    {
        $request = $this->history[0]['request'] ?? null;
        parse_str($request === null ? '' : (string) $request->getBody(), $fields);

        /** @var array<string, string> $fields */
        return $fields;
    }
}
