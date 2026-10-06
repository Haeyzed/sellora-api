<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Enums;

/**
 * What members of Sellora's team can be allowed to do through platform roles.
 *
 * Super admins can do everything without them. Managing the platform team
 * itself (inviting admins, roles, two-factor resets) is for super admins only,
 * so it has no permission here.
 */
enum PlatformPermission: string
{
    /** Draft, edit and publish the terms of service, privacy policy and other legal documents merchants accept. */
    case LegalDocumentsManage = 'legal_documents.manage';

    /** See every store, its status, plan, grants and limit overrides. */
    case StoresView = 'stores.view';

    /** Suspend and reactivate stores, and retry setting up a store whose setup failed. */
    case StoresManage = 'stores.manage';

    /** Give stores modules and integrations outside their plan, and change their usage limits. */
    case StoresGrant = 'stores.grant';
}
