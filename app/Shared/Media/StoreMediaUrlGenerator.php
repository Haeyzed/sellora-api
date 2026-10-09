<?php

declare(strict_types=1);

namespace App\Shared\Media;

use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

/**
 * Gives a store's images addresses that really serve them.
 *
 * Tenancy keeps each store's files in its own folder of a local disk, which
 * the disk's own URL doesn't know about. Such files are served on the store's
 * own domain through the tenancy package's asset route, which only ever
 * reads from the current store's public folder. Files on a cloud disk keep
 * the disk's own URL.
 */
final class StoreMediaUrlGenerator extends DefaultUrlGenerator
{
    public function getUrl(): string
    {
        if (config("filesystems.disks.{$this->getDiskName()}.driver") === 'local' && tenancy()->initialized) {
            return $this->versionUrl(route('stancl.tenancy.asset', ['path' => $this->getPathRelativeToRoot()]));
        }

        return parent::getUrl();
    }
}
