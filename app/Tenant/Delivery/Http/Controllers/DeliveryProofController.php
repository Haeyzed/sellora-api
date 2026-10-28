<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Delivery\Enums\ProofOfDeliveryKind;
use App\Tenant\Delivery\Http\Requests\ViewDeliveryRequest;
use App\Tenant\Delivery\Models\Delivery;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Staff looking at a delivery's proof: personal data, streamed from the store's private disk after the permission check, never from a public URL.
 */
final class DeliveryProofController extends Controller
{
    /**
     * Show a delivery's proof.
     *
     * Needs the delivery.view permission. `photo` is the photo of the goods
     * handed over, `signature` the recipient's signature, each as the JPEG,
     * PNG or WebP image it was kept as; never cached. Not found when the
     * delivery has none of that kind, such as one not delivered, or one whose
     * files were purged, 180 days after it was delivered.
     *
     * @throws NotFoundHttpException
     */
    public function show(ViewDeliveryRequest $request, Delivery $delivery, ProofOfDeliveryKind $kind, FilesystemFactory $filesystems): StreamedResponse
    {
        $media = $delivery->proof($kind) ?? throw new NotFoundHttpException;

        return $filesystems->disk($media->disk)->response($media->getPathRelativeToRoot(), $media->file_name, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
