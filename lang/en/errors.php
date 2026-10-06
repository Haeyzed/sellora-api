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

    'feature_unavailable' => 'Your plan does not include this feature.',
    'feature_locked' => 'Your plan no longer includes this feature. Its existing data is read-only.',
    'feature_suspended' => 'This store is suspended, so this feature is unavailable.',
    'feature_disabled' => 'This feature is switched off for this store.',
    'usage_limit_reached' => 'Your plan allows up to :limit of these. Upgrade your plan to add more.',
    'subscription_already_exists' => 'This store already has a subscription. Change its plan instead.',
    'plan_not_available' => 'The ":plan" plan is not available.',

];
