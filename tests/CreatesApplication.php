<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Tests\Support\StoreDatabaseTemplate;

/**
 * How Laravel's parallel runner boots the application in the main process, before any worker starts (Illuminate\Testing\Concerns\RunsInParallel).
 *
 * The runner is the only user of this trait (tests boot through TestCase),
 * and it boots here once per process slot before starting the workers, and
 * again after they finish. The first boot builds the store template, once
 * for the whole run, so no test ever waits for it and every worker copies
 * the same one (section 15); later boots find it built.
 *
 * Laravel's own "set up process" callbacks aren't used for this: they run
 * only when $_SERVER['LARAVEL_PARALLEL_TESTING'] is set, which Pest sets in
 * the workers but not in the main process.
 */
trait CreatesApplication
{
    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        StoreDatabaseTemplate::prepareForParallelRun();

        return $app;
    }
}
