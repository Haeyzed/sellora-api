<?php

declare(strict_types=1);

namespace App\Shared\Media;

use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Where the current store's storefront images are served from: the scheme and host of their addresses, so content may embed the store's own images and nothing from another site.
 *
 * On a local disk, images are served on the store's own domain through the
 * tenancy package's asset route (StoreMediaUrlGenerator); on a cloud disk,
 * from the disk's own address, such as its CDN. Asked during a store's
 * request, where its domain is known.
 */
final class StorefrontMediaHost
{
    /**
     * The scheme, such as "https", and host, such as "cdn.example.com", images are served from.
     *
     * @return array{scheme: string, host: string}
     */
    public function current(): array
    {
        $disk = StorefrontImage::disk();
        $example = config("filesystems.disks.{$disk}.driver") === 'local' && tenancy()->initialized
            ? route('stancl.tenancy.asset', ['path' => 'image.webp'])
            : Storage::disk($disk)->url('image.webp');

        $scheme = parse_url($example, PHP_URL_SCHEME);
        $host = parse_url($example, PHP_URL_HOST);

        if (! is_string($scheme) || ! is_string($host)) {
            throw new LogicException("Storefront images on the disk \"{$disk}\" have no absolute address to allow them by.");
        }

        return ['scheme' => strtolower($scheme), 'host' => strtolower($host)];
    }
}
