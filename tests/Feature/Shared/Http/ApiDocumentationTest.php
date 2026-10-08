<?php

declare(strict_types=1);

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;

/*
 * The API docs' "Try it" sends each request where that API is served: the
 * platform and registration APIs to the central domain, the store API to
 * the store subdomain the reader fills in.
 */

/**
 * @return list<array<string, mixed>>
 */
function documentedServers(string $api): array
{
    $document = app(Generator::class)(Scramble::getGeneratorConfig($api));

    return $document['servers'];
}

it('sends the central APIs to the central domain and the store API to a store subdomain the reader chooses', function (): void {
    $central = 'http://'.config()->array('platform.central_domains')[0];

    expect(documentedServers('platform'))->toBe([['url' => "{$central}/api/v1/platform", 'description' => 'Central']])
        ->and(documentedServers('registration'))->toBe([['url' => "{$central}/api/v1", 'description' => 'Central']]);

    $store = documentedServers('default');
    expect($store[0]['url'])->toBe('http://{store}.'.config('platform.domain').'/api/v1')
        ->and($store[0]['variables']['store']['default'])->toBe('your-store');
});
