<?php

declare(strict_types=1);

use Dedoc\Scramble\CacheableGenerator;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\GeneratorResult;
use Dedoc\Scramble\Scramble;

/*
|--------------------------------------------------------------------------
| For ApiDocumentationTest.php
|--------------------------------------------------------------------------
*/

/**
 * One API's documentation, generated once per test process: the routes and their docs never change during a run, and generating them is slow.
 */
function generatedApiDocumentation(string $api): GeneratorResult
{
    /** @var array<string, GeneratorResult> $generated */
    static $generated = [];

    return $generated[$api] ??= app(Generator::class)->generate(Scramble::getGeneratorConfig($api));
}

/**
 * @return array<string, mixed>
 */
function documentedApi(string $api): array
{
    return generatedApiDocumentation($api)->spec();
}

/**
 * Lets the docs routes serve the documentation generated for this process, through Scramble's own cache, so a test of who may see the docs doesn't generate them again.
 */
function serveGeneratedApiDocumentation(): void
{
    config(['scramble.cache.store' => 'array', 'scramble.cache.key' => 'api-documentation-test']);

    foreach (['default', 'platform', 'registration'] as $api) {
        CacheableGenerator::store(Scramble::getGeneratorConfig($api), generatedApiDocumentation($api));
    }
}

/**
 * @return list<array<string, mixed>>
 */
function documentedServers(string $api): array
{
    return documentedApi($api)['servers'];
}

/*
|--------------------------------------------------------------------------
| For tests of emailed one-time links
|--------------------------------------------------------------------------
*/

/**
 * What an emailed link carries in its URL fragment, such as its token: failing the test if the link has any query string at all, so no secret can travel in one.
 *
 * @return array<string, string>
 */
function emailedLinkFragment(string $url): array
{
    expect(parse_url($url, PHP_URL_QUERY))->toBeNull("The emailed link {$url} has a query string; its secrets belong after \"#\".");
    parse_str((string) parse_url($url, PHP_URL_FRAGMENT), $fragment);

    /** @var array<string, string> $fragment */
    return $fragment;
}

/**
 * Every config value that is a URL template with this placeholder, by its dotted config key, so a new emailed link is checked without anyone listing it.
 *
 * @param  array<mixed>|null  $config
 * @return array<string, string>
 */
function configTemplatesWith(string $placeholder, ?array $config = null, string $prefix = ''): array
{
    $found = [];

    foreach ($config ?? config()->all() as $key => $value) {
        if (is_array($value)) {
            $found = [...$found, ...configTemplatesWith($placeholder, $value, "{$prefix}{$key}.")];
        } elseif (is_string($value) && str_contains($value, $placeholder)) {
            $found["{$prefix}{$key}"] = $value;
        }
    }

    return $found;
}

/*
|--------------------------------------------------------------------------
| For CorsTest.php
|--------------------------------------------------------------------------
*/

/**
 * Sends a browser CORS preflight for a GET request from the given origin.
 */
function preflightFrom(string $origin): Illuminate\Testing\TestResponse
{
    return test()->withHeaders([
        'Origin' => $origin,
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/v1/products');
}

/**
 * Loads config/cors.php as it would be with these environment variables.
 *
 * @param  array<string, string|null>  $environment  Null unsets the variable.
 * @return array<string, mixed>
 */
function corsConfigWith(array $environment): array
{
    return configFileWith('cors', $environment);
}

/**
 * Loads one config file as it would be with these environment variables.
 *
 * @param  array<string, string|null>  $environment  Null unsets the variable.
 * @return array<string, mixed>
 */
function configFileWith(string $file, array $environment): array
{
    $previous = [];

    foreach ($environment as $name => $value) {
        $previous[$name] = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)];
        if ($value === null) {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);
        } else {
            $_SERVER[$name] = $_ENV[$name] = $value;
            putenv("{$name}={$value}");
        }
    }

    try {
        return require base_path("config/{$file}.php");
    } finally {
        foreach ($previous as $name => [$server, $env, $process]) {
            if ($server === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $server;
            }

            if ($env === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $env;
            }

            putenv($process === false ? $name : "{$name}={$process}");
        }
    }
}
