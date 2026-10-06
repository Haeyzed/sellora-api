<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Enums;

/**
 * What members of Sellora's team can be allowed to do. Super admins can do everything without them.
 */
enum PlatformPermission: string
{
    /** Draft, edit and publish the terms of service, privacy policy and other legal documents merchants accept. */
    case LegalDocumentsManage = 'legal_documents.manage';
}
