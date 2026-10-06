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

];
