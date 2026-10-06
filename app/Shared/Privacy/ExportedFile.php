<?php

declare(strict_types=1);

namespace App\Shared\Privacy;

use Closure;

/**
 * One uploaded file to put in a store export.
 */
final readonly class ExportedFile
{
    /**
     * @param  string  $path  Where it goes inside the export's "files" folder, such as "products/42/front.jpg".
     * @param  Closure(): resource  $openStream  Opens the file for reading only when it is written, so large files are never held in memory.
     */
    public function __construct(
        public string $path,
        public Closure $openStream,
    ) {}
}
