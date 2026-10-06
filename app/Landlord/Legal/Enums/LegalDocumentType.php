<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Enums;

/**
 * The kinds of legal document Sellora publishes for merchants. Merchants accept the current version of each when they register.
 */
enum LegalDocumentType: string
{
    case TermsOfService = 'terms_of_service';
    case PrivacyPolicy = 'privacy_policy';
}
