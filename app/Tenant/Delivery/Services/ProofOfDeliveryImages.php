<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Services;

use App\Shared\Media\PrivateImages;
use App\Tenant\Delivery\Enums\ProofOfDeliveryKind;
use Illuminate\Http\UploadedFile;

/**
 * The rules proof of delivery images follow, and their re-encoding without metadata (Shared PrivateImages, config media.proof_of_delivery).
 */
final readonly class ProofOfDeliveryImages
{
    private const string KIND = 'proof_of_delivery';

    public function __construct(private PrivateImages $privateImages) {}

    /**
     * The private disk proof of delivery is kept on (config media.proof_of_delivery.disk).
     */
    public static function disk(): string
    {
        return PrivateImages::disk(self::KIND);
    }

    /**
     * @return list<mixed>
     */
    public static function rules(): array
    {
        return PrivateImages::rules(self::KIND);
    }

    /**
     * The image re-encoded into a temporary file, in the format it came in; null when it can't be decoded.
     */
    public function encode(ProofOfDeliveryKind $kind, UploadedFile $upload): ?ProofOfDeliveryFile
    {
        $image = $this->privateImages->encode($upload, 'proof-of-delivery');

        return $image === null ? null : new ProofOfDeliveryFile($kind, $image->path, $image->extension, $image->size);
    }
}
