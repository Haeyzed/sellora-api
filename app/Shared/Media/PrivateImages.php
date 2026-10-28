<?php

declare(strict_types=1);

namespace App\Shared\Media;

use ErrorException;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * The rules private images follow, and their re-encoding, for each kind configured in config/media.php (such as proof of delivery or return photos).
 *
 * JPEG, PNG or WebP, never SVG, within the kind's file size and dimensions;
 * dimensions are read from the file's header, so an image built to decode
 * into something enormous is refused before it is decoded. Each accepted
 * image is decoded and encoded again, which drops everything but the pixels:
 * camera, GPS position, time taken, comments. A photo turned by its camera is
 * turned upright first, since that turn is metadata too.
 */
final class PrivateImages
{
    private const int QUALITY = 85;

    /**
     * The private disk a kind is kept on (config media.{kind}.disk).
     */
    public static function disk(string $kind): string
    {
        return config()->string("media.{$kind}.disk");
    }

    /**
     * @return list<mixed>
     */
    public static function rules(string $kind): array
    {
        return [
            File::image()
                ->types(config()->array("media.{$kind}.types"))
                ->max(config()->integer("media.{$kind}.max_kilobytes")),
            Rule::dimensions()
                ->maxWidth(config()->integer("media.{$kind}.max_width"))
                ->maxHeight(config()->integer("media.{$kind}.max_height")),
        ];
    }

    /**
     * The image re-encoded into a temporary file, in the format it came in; null when it can't be decoded.
     *
     * @param  string  $prefix  Starts the temporary file's name, such as "return-photo".
     */
    public function encode(UploadedFile $upload, string $prefix): ?EncodedImage
    {
        $source = (string) $upload->getRealPath();
        $type = (getimagesize($source) ?: [2 => null])[2];

        try {
            $image = imagecreatefromstring((string) file_get_contents($source));
        } catch (ErrorException) {
            return null;
        }

        if ($image === false) {
            return null;
        }

        if ($type === IMAGETYPE_JPEG) {
            $image = $this->upright($image, $this->orientation($source));
        }

        $extension = match ($type) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => 'jpg',
        };
        $target = sys_get_temp_dir().'/'.$prefix.'-'.bin2hex(random_bytes(16)).'.'.$extension;

        $written = match ($extension) {
            'png' => imagealphablending($image, false) && imagesavealpha($image, true) && imagepng($image, $target),
            'webp' => imagewebp($image, $target, self::QUALITY),
            default => imagejpeg($image, $target, self::QUALITY),
        };

        clearstatcache(true, $target);

        if (! $written) {
            if (is_file($target)) {
                unlink($target);
            }

            return null;
        }

        return new EncodedImage($target, $extension, (int) filesize($target));
    }

    /**
     * The EXIF orientation of a JPEG, 1 (upright) when it has none.
     */
    private function orientation(string $path): int
    {
        try {
            $exif = exif_read_data($path);
        } catch (ErrorException) {
            return 1;
        }

        return is_array($exif) && is_int($exif['Orientation'] ?? null) ? $exif['Orientation'] : 1;
    }

    /**
     * Turns and mirrors the image as its EXIF orientation says it should be shown (imagerotate turns anticlockwise).
     */
    private function upright(GdImage $image, int $orientation): GdImage
    {
        $turned = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, 270, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => $image,
        };

        if ($turned === false) {
            return $image;
        }

        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($turned, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 4) {
            imageflip($turned, IMG_FLIP_VERTICAL);
        }

        return $turned;
    }
}
