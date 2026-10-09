<?php

declare(strict_types=1);

namespace App\Shared\Media;

use App\Shared\Privacy\ExportedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The original files the store keeps for some kinds of records, as files of a store export. Resized copies are left out: they can be made again.
 */
final class StoredFiles
{
    /**
     * @param  list<string>  $modelTypes  The records' morph names, such as "product".
     * @param  string  $folder  The export folder they go in, such as "catalog".
     * @return iterable<ExportedFile>
     */
    public static function of(array $modelTypes, string $folder): iterable
    {
        foreach (Media::query()->whereIn('model_type', $modelTypes)->orderBy('id')->cursor() as $media) {
            yield new ExportedFile(
                path: "{$folder}/{$media->model_type}/{$media->model_id}/{$media->id}-{$media->file_name}",
                openStream: static fn () => Storage::disk($media->disk)->readStream($media->getPathRelativeToRoot())
                    ?? throw new RuntimeException("The file of media {$media->id} is missing."),
            );
        }
    }
}
