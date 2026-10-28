<?php

declare(strict_types=1);

namespace App\Shared\Media;

/**
 * An uploaded image re-encoded into a temporary file without its metadata, ready to be kept.
 */
final readonly class EncodedImage
{
    public function __construct(
        public string $path,
        public string $extension,
        public int $size,
    ) {}
}
