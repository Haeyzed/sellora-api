<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Documents;

use Mpdf\AssetFetcher;
use Mpdf\Exception\AssetFetchingException;

/**
 * Loads nothing for a tax document: no remote URL, no file path.
 *
 * mPDF loads every image, stylesheet and SVG reference a document names
 * through its asset fetcher; this one refuses them all, so a document can
 * never make a network request or read a server file. The images a document
 * shows (the store's logo) are handed to mPDF directly, as image variables.
 * mPDF's own loaders type against its AssetFetcher class, so this extends it,
 * overriding everything that would load.
 */
final class OfflineAssetFetcher extends AssetFetcher
{
    public function __construct() {}

    /**
     * @param  string  $path
     * @param  string|null  $originalSrc
     *
     * @throws AssetFetchingException Always.
     */
    public function fetchDataFromPath($path, $originalSrc = null): string
    {
        throw new AssetFetchingException('Tax documents load nothing from outside the document.');
    }

    /**
     * @param  string  $path
     * @param  string|null  $originalSrc
     */
    public function fetchLocalContent($path, $originalSrc): string
    {
        return '';
    }

    /**
     * @param  string  $path
     */
    public function fetchRemoteContent($path): string
    {
        return '';
    }
}
