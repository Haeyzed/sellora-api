<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Services;

/**
 * The proof images handed over with a delivery: a photo, a signature, or both.
 */
final readonly class ProofOfDelivery
{
    /**
     * @param  list<ProofOfDeliveryFile>  $files
     */
    public function __construct(public array $files = []) {}

    public function isEmpty(): bool
    {
        return $this->files === [];
    }

    /**
     * How much space the files take together, in bytes.
     */
    public function size(): int
    {
        return array_sum(array_map(static fn (ProofOfDeliveryFile $file): int => $file->size, $this->files));
    }

    /**
     * Deletes the temporary files that weren't kept.
     */
    public function discard(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file->path)) {
                unlink($file->path);
            }
        }
    }
}
