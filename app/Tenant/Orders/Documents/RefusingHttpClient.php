<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Documents;

use InvalidArgumentException;
use Mpdf\Http\ClientInterface;
use Psr\Http\Message\RequestInterface;

/**
 * mPDF's HTTP client for tax documents: refuses every request. A second guard behind OfflineAssetFetcher, which never asks it.
 */
final readonly class RefusingHttpClient implements ClientInterface
{
    /**
     * @throws InvalidArgumentException Always.
     */
    public function sendRequest(RequestInterface $request): never
    {
        throw new InvalidArgumentException('Tax documents make no network requests.');
    }
}
