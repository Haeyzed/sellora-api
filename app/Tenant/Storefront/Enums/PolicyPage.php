<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Enums;

/**
 * The policies a storefront links to, such as from checkout and its footer, each served by one of the store's pages. Core writes no policy text: the merchant does.
 */
enum PolicyPage: string
{
    case Privacy = 'privacy';

    case Terms = 'terms';

    case Refunds = 'refunds';

    case Shipping = 'shipping';

    /**
     * The storefront settings column naming the page.
     */
    public function column(): string
    {
        return $this->value.'_page_id';
    }
}
