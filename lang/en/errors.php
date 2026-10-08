<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API Error Messages
|--------------------------------------------------------------------------
|
| One human-readable message per machine-readable error code. Every API
| error response uses a code from this file, so clients can rely on the
| code and show the translated message.
|
*/

return [

    'bad_request' => 'The request could not be understood.',
    'unauthenticated' => 'You need to sign in to do this.',
    'forbidden' => 'You are not allowed to do this.',
    'not_found' => 'We could not find what you were looking for.',
    'method_not_allowed' => 'This action is not supported here.',
    'conflict' => 'This request conflicts with the current state. Refresh and try again.',
    'validation_failed' => 'Some of the information provided is not valid.',
    'too_many_requests' => 'Too many requests. Please wait a moment and try again.',
    'server_error' => 'Something went wrong on our side. Please try again.',
    'service_unavailable' => 'The service is temporarily unavailable. Please try again shortly.',
    'http_error' => 'The request could not be completed.',

    'idempotency_key_missing' => 'A valid Idempotency-Key header is required for this request.',
    'idempotency_key_reused' => 'This Idempotency-Key was already used for a different request.',
    'idempotent_request_in_progress' => 'A request with this Idempotency-Key is still being processed. Try again shortly.',

    'invalid_credentials' => 'These sign-in details are incorrect.',
    'sign_in_temporarily_locked' => 'Too many incorrect attempts. Try again in :minutes minutes.',
    'account_deactivated' => 'This account has been deactivated.',
    'password_reset_invalid' => 'This password reset link is invalid or has expired. Request a new one.',
    'current_password_incorrect' => 'The current password is incorrect.',
    'too_many_incorrect_attempts' => 'Too many incorrect attempts. Try again in :minutes minutes.',
    'two_factor_code_invalid' => 'This code is incorrect, expired or already used.',
    'two_factor_challenge_invalid' => 'This sign-in attempt has expired. Sign in with your password again.',
    'two_factor_already_enabled' => 'Two-factor authentication is already on. Turn it off first to move it to a new device.',
    'two_factor_not_enabled' => 'Two-factor authentication is not on for this account.',
    'two_factor_setup_not_started' => 'Start two-factor setup before confirming it.',
    'two_factor_setup_required' => 'Set up two-factor authentication to continue.',
    'permissions_exceed_your_own' => 'You can only grant permissions you have yourself, and only manage staff whose permissions you also have.',
    'cannot_manage_own_account' => 'You cannot change your own roles or deactivate yourself.',
    'store_owner_protected' => 'The store owner cannot be changed or deactivated here.',
    'role_protected' => 'The Owner role cannot be changed or deleted.',
    'role_not_assignable' => 'The Owner role cannot be given this way.',
    'role_in_use' => 'This role is still given to staff members or pending invitations. Remove it from them first.',
    'role_name_taken' => 'A role with this name already exists.',
    'staff_member_already_exists' => 'This person is already a member of the team.',
    'staff_invitation_already_pending' => 'This person already has a pending invitation. Resend it instead.',
    'staff_invitation_not_pending' => 'This invitation was already accepted or cancelled.',
    'staff_invitation_invalid' => 'This invitation link is invalid, has expired or was already used. Ask for a new one.',
    'ownership_transfer_already_pending' => 'An ownership transfer is already waiting to be accepted. Cancel it before starting another.',
    'ownership_transfer_not_pending' => 'This ownership transfer was already accepted, cancelled or has expired.',
    'ownership_transfer_not_found' => 'There is no pending ownership transfer for you.',
    'ownership_transfer_recipient_invalid' => 'The store can only be handed to another active member of the team.',
    'terms_of_service_not_accepted' => 'Please accept the current terms of service.',

    'store_unavailable' => 'This store is not available right now. Please try again later.',
    'store_registration_closed' => 'New stores cannot be registered right now. Please try again later.',
    'subdomain_taken' => 'This store address is already taken. Choose another.',
    'hosting_region_unavailable' => 'Stores cannot be hosted in this region right now. Choose another region.',
    'stores_per_email_limit_reached' => 'This email already has :limit stores, the most one person can register.',
    'store_registration_code_invalid' => 'This code is incorrect or no longer valid. Request a new code if needed.',
    'store_registration_expired' => 'This code has expired. Request a new one.',
    'store_registration_already_verified' => 'This store has already been registered.',
    'verification_code_recently_sent' => 'A code was sent less than a minute ago. Please wait before requesting another.',
    'legal_documents_not_accepted' => 'Please accept the current terms of service and privacy policy.',
    'legal_document_already_published' => 'A published document cannot change. Create a new version instead.',
    'legal_document_version_taken' => 'This document already has a version with this label.',

    'platform_admin_already_exists' => 'This person already has a platform admin account.',
    'platform_admin_invitation_already_pending' => 'This person already has a pending invitation. Resend it instead.',
    'platform_admin_invitation_not_pending' => 'This invitation was already accepted or cancelled.',
    'platform_admin_invitation_invalid' => 'This invitation link is invalid, has expired or was already used. Ask for a new one.',
    'last_super_admin' => 'At least one active super admin must remain.',
    'platform_role_protected' => 'The Super Admin role cannot be changed or deleted.',
    'platform_role_in_use' => 'This role is still given to platform admins or pending invitations. Remove it from them first.',
    'store_status_conflict' => 'This cannot be done while the store is in its current status.',
    'store_export_in_progress' => 'An export of this store is already being prepared. Wait for it to finish.',
    'country_not_found' => 'We don\'t support that country.',
    'own_two_factor_required' => 'Set up two-factor authentication for yourself before requiring it for your staff.',
    'pricing_settings_locked' => 'The base currency and tax mode can\'t change once anything in the store has a price, because that would change what customers pay.',
    'store_export_not_found' => 'This export doesn\'t exist, or you didn\'t request it.',
    'store_export_not_ready' => 'This export can\'t be downloaded: it isn\'t ready yet, it failed, or it has expired.',
    'feature_already_granted' => 'This store already has this feature granted. Revoke the grant first to change it.',
    'feature_grant_not_active' => 'This grant has already ended.',

    'feature_unavailable' => 'Your plan does not include this feature.',
    'feature_locked' => 'Your plan no longer includes this feature. Its existing data is read-only.',
    'feature_suspended' => 'This store is suspended, so this feature is unavailable.',
    'feature_disabled' => 'This feature is switched off for this store.',
    'usage_limit_reached' => 'Your plan allows up to :limit of these. Upgrade your plan to add more.',
    'subscription_already_exists' => 'This store already has a subscription. Change its plan instead.',
    'plan_not_available' => 'The ":plan" plan is not available.',

    'brand_slug_taken' => 'Another brand already uses this slug. Choose another.',
    'brand_restore_conflict' => 'Another brand now uses this brand\'s slug. Change one of the slugs, then restore it.',
    'category_slug_taken' => 'Another category already uses this slug. Choose another.',
    'category_restore_conflict' => 'Another category now uses this category\'s slug. Change one of the slugs, then restore it.',
    'category_has_subcategories' => 'This category still has subcategories. Move them or move them to the trash first.',
    'category_parent_in_trash' => 'This category\'s parent is in the trash. Restore the parent first, or move this category.',
    'category_too_deep' => 'Categories can be nested at most :max levels deep.',
    'category_loop' => 'A category can\'t move under itself or one of its own subcategories.',
    'category_order_mismatch' => 'List every subcategory of the parent exactly once.',
    'product_slug_taken' => 'Another product already uses this slug. Choose another.',
    'variant_sku_taken' => 'Another product variant already uses this SKU. Choose another.',

];
