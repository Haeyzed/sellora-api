<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Policies;

use App\Landlord\Identity\Enums\PlatformPermission;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Legal\Models\LegalDocument;

/**
 * Who on Sellora's team may see, draft and publish legal documents. Everyone can read the versions in force without signing in.
 */
final class LegalDocumentPolicy
{
    public function viewAny(PlatformAdmin $actor): bool
    {
        return $actor->can(PlatformPermission::LegalDocumentsManage->value);
    }

    public function view(PlatformAdmin $actor, LegalDocument $legalDocument): bool
    {
        return $actor->can(PlatformPermission::LegalDocumentsManage->value);
    }

    public function create(PlatformAdmin $actor): bool
    {
        return $actor->can(PlatformPermission::LegalDocumentsManage->value);
    }

    public function update(PlatformAdmin $actor, LegalDocument $legalDocument): bool
    {
        return $actor->can(PlatformPermission::LegalDocumentsManage->value);
    }
}
