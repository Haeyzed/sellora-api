<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Idempotency\IdempotencyKey;
use App\Shared\Idempotency\IdempotencyStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| For ProcessDatabaseTest.php
|--------------------------------------------------------------------------
*/

/**
 * Runs the work with a scratch database name, dropping whatever was created afterwards.
 */
function withScratchProcessDatabase(Closure $work): void
{
    $connection = config()->string('database.default');
    $base = config()->string("database.connections.{$connection}.database");
    $token = 'probe'.Str::lower(Str::random(8));
    $database = "{$base}_test_{$token}";

    try {
        $work($connection, $base, $token, $database);
    } finally {
        config(["database.connections.{$connection}.database" => $base]);
        DB::purge($connection);
        DB::purge('scratch');
        DB::connection($connection)->statement('drop database if exists "'.$database.'" with (force)');
    }
}

/*
|--------------------------------------------------------------------------
| For QueueWorkersTest.php
|--------------------------------------------------------------------------
*/

/**
 * The queues named in a command's "--queue=" option in a file of the project.
 *
 * @return list<string>
 */
function queuesListenedIn(string $path, string $command): array
{
    preg_match('/'.preg_quote($command, '/').' --queue=([a-z0-9_,-]+)/', (string) file_get_contents(base_path($path)), $matches);

    return isset($matches[1]) ? explode(',', $matches[1]) : [];
}

/*
|--------------------------------------------------------------------------
| For StoreIsolationTest.php
|--------------------------------------------------------------------------
*/

function seedExpiredIdempotencyKey(Tenant $store, string $key): void
{
    $store->run(static function () use ($key): void {
        IdempotencyKey::query()->create([
            'scope' => 'ip:test',
            'route' => 'POST sample',
            'key' => $key,
            'request_hash' => str_repeat('a', 64),
            'status' => IdempotencyStatus::Completed,
            'locked_until' => now()->subWeek(),
            'expires_at' => now()->subWeek(),
        ]);
    });
}

/*
|--------------------------------------------------------------------------
| For BrandInEmailsTest.php
|--------------------------------------------------------------------------
*/

/**
 * A notification's email text (subject and every line) in every language the application has, one after another.
 */
function brandedEmailText(Illuminate\Notifications\Notification $notification): string
{
    $text = '';
    $previousLocale = app()->getLocale();

    try {
        foreach (glob(lang_path('*'), GLOB_ONLYDIR) ?: [] as $folder) {
            app()->setLocale(basename($folder));
            /** @var Illuminate\Notifications\Messages\MailMessage $mail */
            $mail = $notification->toMail(new Illuminate\Notifications\AnonymousNotifiable);
            $text .= implode("\n", [(string) $mail->subject, ...$mail->introLines, ...$mail->outroLines])."\n";
        }
    } finally {
        app()->setLocale($previousLocale);
    }

    return $text;
}

/*
|--------------------------------------------------------------------------
| For DocsExamplesTest.php
|--------------------------------------------------------------------------
*/

/**
 * The hand-written guides: the README and every Markdown file under docs/.
 *
 * @return list<string>
 */
function guideFiles(): array
{
    $files = [base_path('README.md')];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('docs'), FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() === 'md') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

function guideName(string $file): string
{
    return str_replace('\\', '/', mb_substr($file, mb_strlen(base_path()) + 1));
}

/**
 * Every cURL example in a guide, as [method, path]. The address is resolved
 * through the guide's own "$Base = "$Other/..."" lines, an *Origin variable
 * being the domain; variables inside the path become "X". Null for the path
 * when its base variable isn't defined in the guide.
 *
 * @return list<array{0: string, 1: string|null, 2: string}>
 */
function curlExamplesIn(string $file): array
{
    $text = (string) file_get_contents($file);
    preg_match_all('/\$(\w+)\s*=\s*["\']\$\{?(\w+)\}?(\/[^"\']*)["\']/', $text, $definitions, PREG_SET_ORDER);
    $bases = [];

    foreach ($definitions as [, $name, $parent, $suffix]) {
        $bases[$name] = [$parent, $suffix];
    }

    $resolve = static function (string $name, int $depth = 0) use (&$resolve, $bases): ?string {
        if (str_ends_with($name, 'Origin')) {
            return '';
        }

        if (! isset($bases[$name]) || $depth > 5) {
            return null;
        }

        $parent = $resolve($bases[$name][0], $depth + 1);

        return $parent === null ? null : $parent.$bases[$name][1];
    };

    preg_match_all('/--request\s+(GET|POST|PUT|PATCH|DELETE)\s+"\$\{?(\w+)\}?(\/[^"?]*)?[^"]*"/', $text, $matches, PREG_SET_ORDER);
    $examples = [];

    foreach ($matches as $match) {
        $prefix = $resolve($match[2]);
        $path = $prefix === null ? null : rtrim((string) preg_replace('/\$\{?\w+\}?/', 'X', $prefix.($match[3] ?? '')), '/');
        $examples[] = [$match[1], $path, '$'.$match[2].($match[3] ?? '')];
    }

    return $examples;
}

/**
 * The application's routes as [method, uri, regex matching a path with any value in each parameter], without HEAD.
 *
 * @return list<array{0: string, 1: string, 2: string}>
 */
function routesForGuides(): array
{
    $routes = [];

    foreach (app('router')->getRoutes() as $route) {
        $uri = '/'.ltrim($route->uri(), '/');
        $regex = '#^'.preg_replace('#\\\\\{[^}]+\\\\\}#', '[^/]+', preg_quote($uri, '#')).'$#';

        foreach ($route->methods() as $method) {
            if ($method !== 'HEAD') {
                $routes[] = [$method, $uri, $regex];
            }
        }
    }

    return $routes;
}

/**
 * Where the guides go wrong about the code, one line each: a cURL example with no route, an API route with no example,
 * an artisan command, a backticked class name or a relative link that doesn't exist.
 *
 * @return array{examples: list<string>, routes: list<string>, commands: list<string>, classes: list<string>, links: list<string>}
 */
function guideProblems(): array
{
    $problems = ['examples' => [], 'routes' => [], 'commands' => [], 'classes' => [], 'links' => []];
    $routes = routesForGuides();
    $covered = [];
    $commands = array_keys(Illuminate\Support\Facades\Artisan::all());
    $names = knownCodeNames();

    foreach (guideFiles() as $file) {
        $guide = guideName($file);
        $text = (string) file_get_contents($file);

        foreach (curlExamplesIn($file) as [$method, $path, $written]) {
            $route = $path === null ? null : collect($routes)->first(static fn (array $route): bool => $route[0] === $method && preg_match($route[2], $path) === 1);

            if ($route === null) {
                $problems['examples'][] = "{$guide}: {$method} {$written}";
            } else {
                $covered["{$route[0]} {$route[1]}"] = true;
            }
        }

        preg_match_all('/\bartisan\s+([a-z][a-z0-9-]*(?::[a-z0-9-]+)*)/', $text, $commandMatches);

        foreach (array_unique($commandMatches[1]) as $command) {
            if (! in_array($command, $commands, true)) {
                $problems['commands'][] = "{$guide}: {$command}";
            }
        }

        // The roadmap names what isn't built yet, so its names aren't checked against the code.
        preg_match_all('/`([A-Z][a-z]+(?:[A-Z][a-z0-9]*)+)`/', $guide === 'docs/roadmap.md' ? '' : $text, $classMatches);

        foreach (array_unique($classMatches[1]) as $name) {
            if (! isset($names[$name])) {
                $problems['classes'][] = "{$guide}: {$name}";
            }
        }

        preg_match_all('/\]\((?!https?:|mailto:|#)([^)\s#]+)/', $text, $linkMatches);

        foreach (array_unique($linkMatches[1]) as $link) {
            if (! file_exists(dirname($file).DIRECTORY_SEPARATOR.$link)) {
                $problems['links'][] = "{$guide}: {$link}";
            }
        }
    }

    foreach ($routes as [$method, $uri]) {
        if (str_starts_with($uri, '/api/') && ! isset($covered["{$method} {$uri}"])) {
            $problems['routes'][] = "{$method} {$uri}";
        }
    }

    return array_map(static fn (array $list): array => array_values(array_unique($list)), $problems);
}

/**
 * Names a guide may put in backticks as code: every class, enum, interface or trait file in the code, every enum case,
 * and the modules named in CLAUDE.md's catalogue or in a heading of docs/roadmap.md, which may be on the roadmap only.
 *
 * @return array<string, true>
 */
function knownCodeNames(): array
{
    $names = [];

    foreach (['app', 'database', 'integrations', 'modules'] as $folder) {
        if (! is_dir(base_path($folder))) {
            continue;
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($folder), FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $names[$file->getBasename('.php')] = true;
            preg_match_all('/^\s*case\s+(\w+)\b/m', (string) file_get_contents($file->getPathname()), $cases);

            foreach ($cases[1] as $case) {
                $names[$case] = true;
            }
        }
    }

    preg_match_all('/^\|\s*`(\w+)`/m', (string) file_get_contents(base_path('CLAUDE.md')), $modules);

    foreach ($modules[1] as $module) {
        $names[$module] = true;
    }

    $roadmap = base_path('docs/roadmap.md');
    preg_match_all('/^##\s.*$/m', is_file($roadmap) ? (string) file_get_contents($roadmap) : '', $headings);
    preg_match_all('/`(\w+)`/', implode("\n", $headings[0]), $roadmapModules);

    foreach ($roadmapModules[1] as $module) {
        $names[$module] = true;
    }

    return $names;
}

/*
|--------------------------------------------------------------------------
| For DatabaseCommentsTest.php
|--------------------------------------------------------------------------
*/

/**
 * What lacks a comment in a database, as "table" or "table.column": every table, and every column that is money
 * (ending in _amount), a status, a public ID or a hash (ending in _hash), which nobody can read correctly without one.
 *
 * @return list<string>
 */
function uncommentedSchema(Illuminate\Database\Connection $connection): array
{
    $missing = [];

    foreach (app(App\Shared\Database\TableDescriptions::class)->of($connection) as $table => $description) {
        if (($description['comment'] ?? '') === '') {
            $missing[] = $table;
        }

        foreach ($description['columns'] as $column => $details) {
            $mustBeDescribed = str_ends_with($column, '_amount') || str_ends_with($column, '_hash') || in_array($column, ['status', 'public_id'], true);

            if ($mustBeDescribed && ($details['comment'] ?? '') === '') {
                $missing[] = "{$table}.{$column}";
            }
        }
    }

    return $missing;
}
