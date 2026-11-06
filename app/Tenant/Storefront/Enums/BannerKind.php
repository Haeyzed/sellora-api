<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Enums;

/**
 * What a banner is: an image placed somewhere on the storefront, or an announcement in the bar along the top.
 */
enum BannerKind: string
{
    case Image = 'image';
    case Announcement = 'announcement';
}
