<?php

declare(strict_types=1);

namespace App\Shared\Idempotency;

use App\Shared\Idempotency\Exceptions\IdempotencyKeyMissingException;
use App\Shared\Idempotency\Exceptions\IdempotencyKeyReusedException;
use App\Shared\Idempotency\Exceptions\IdempotencyRequesterUnknownException;
use App\Shared\Idempotency\Exceptions\IdempotentRequestInProgressException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a money-related request (checkout, payment, refund) safe to retry: the same request is processed once, however many times it arrives.
 *
 * Clients send a unique Idempotency-Key header with each new request and
 * reuse it when retrying. A retry of a finished request gets the original
 * response back (marked with "Idempotent-Replayed: true"). A retry while the
 * original is still running gets 409. Reusing a key for a different request
 * gets 422. Server errors free the key, so the client can retry. Keys are
 * per requester (the guard and account, or the guest's cart), per endpoint
 * and per store (each store has its own table): the same key from someone
 * else never replays another person's response (section 11).
 *
 * Use as route middleware `idempotent`, after the route's auth middleware.
 */
final readonly class EnsureRequestIsIdempotent
{
    private const string KEY_FORMAT = '/^[A-Za-z0-9_\-]{8,128}$/';

    public function __construct(
        private GuestRequester $guestRequester,
        private ReplayPreparers $replayPreparers,
    ) {}

    /**
     * Processes the request once per Idempotency-Key, or replays the stored answer.
     *
     * @param  Closure(Request): Response  $next
     *
     * @throws IdempotencyKeyMissingException When the header is missing or malformed.
     * @throws IdempotencyRequesterUnknownException When nobody known sent it: no sign-in and no guest cart.
     * @throws IdempotencyKeyReusedException When the key was used for a different request.
     * @throws IdempotentRequestInProgressException When the original request is still running.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->keyFrom($request);
        $record = $this->claim($request, $key);

        if ($record->isCompleted()) {
            return $this->replay($request, $record);
        }

        $response = $next($request);

        if ($response->getStatusCode() >= Response::HTTP_INTERNAL_SERVER_ERROR) {
            $record->delete();

            return $response;
        }

        $this->complete($record, $response);

        return $response;
    }

    private function keyFrom(Request $request): string
    {
        $key = (string) $request->headers->get('Idempotency-Key', '');

        if (preg_match(self::KEY_FORMAT, $key) !== 1) {
            throw new IdempotencyKeyMissingException;
        }

        return $key;
    }

    /**
     * Records this request as in progress, or returns the existing record for a repeat.
     */
    private function claim(Request $request, string $key): IdempotencyKey
    {
        $identity = [
            'scope' => $this->requesterScope($request),
            'route' => $this->routeIdentifier($request),
            'key' => $key,
        ];
        $requestHash = $this->requestHash($request);

        $record = IdempotencyKey::query()->createOrFirst($identity, [
            'request_hash' => $requestHash,
            'status' => IdempotencyStatus::Processing,
            'locked_until' => now()->addSeconds(config()->integer('api.idempotency.lease_seconds')),
            'expires_at' => now()->addHours(config()->integer('api.idempotency.replay_window_hours')),
        ]);

        if ($record->wasRecentlyCreated) {
            return $record;
        }

        if ($record->hasExpired()) {
            $record->delete();

            return $this->claim($request, $key);
        }

        return $this->resumeExisting($record, $requestHash);
    }

    /**
     * Decides what to do when the key was seen before: replay it, reject it, or take over a crashed attempt.
     */
    private function resumeExisting(IdempotencyKey $record, string $requestHash): IdempotencyKey
    {
        if (! hash_equals($record->request_hash, $requestHash)) {
            throw new IdempotencyKeyReusedException;
        }

        if ($record->isCompleted()) {
            return $record;
        }

        if ($record->isLocked() || ! $this->takeOverLease($record)) {
            throw new IdempotentRequestInProgressException;
        }

        return $record;
    }

    /**
     * Takes over an attempt whose lease ran out (it crashed or timed out). Only one retry can win the takeover.
     */
    private function takeOverLease(IdempotencyKey $record): bool
    {
        $newLease = now()->addSeconds(config()->integer('api.idempotency.lease_seconds'));

        $takenOver = IdempotencyKey::query()
            ->whereKey($record->id)
            ->where('status', IdempotencyStatus::Processing)
            ->where('locked_until', '<=', now())
            ->update(['locked_until' => $newLease]) === 1;

        if ($takenOver) {
            $record->locked_until = $newLease;
        }

        return $takenOver;
    }

    private function complete(IdempotencyKey $record, Response $response): void
    {
        $record->forceFill([
            'status' => IdempotencyStatus::Completed,
            'response_status' => $response->getStatusCode(),
            'response_content_type' => $response->headers->get('Content-Type'),
            'response_body' => (string) $response->getContent(),
        ])->save();
    }

    /**
     * The stored answer again. Only the body is ever stored, never headers; a route that must hand back something secret (a guest's order token) adds a fresh one here (ReplayPreparers).
     */
    private function replay(Request $request, IdempotencyKey $record): Response
    {
        $response = new Response((string) $record->response_body, (int) $record->response_status, [
            'Content-Type' => $record->response_content_type ?? 'application/json',
            'Idempotent-Replayed' => 'true',
        ]);
        $this->replayPreparers->prepare($request, $response);

        return $response;
    }

    /**
     * Who sent the request: a signed-in account of a specific guard, or a guest by what identifies them (their cart). Never an IP address, which many people share.
     *
     * @throws IdempotencyRequesterUnknownException When the request is from nobody known.
     */
    private function requesterScope(Request $request): string
    {
        $userId = Auth::id();

        if ($userId !== null) {
            return Auth::getDefaultDriver().':'.$userId;
        }

        return $this->guestRequester->scopeOf($request) ?? throw new IdempotencyRequesterUnknownException;
    }

    private function routeIdentifier(Request $request): string
    {
        $route = $request->route();
        $routeName = is_object($route) ? ($route->getName() ?? $route->uri()) : $request->path();

        return $request->method().' '.$routeName;
    }

    /**
     * A fingerprint of what was asked for, so a reused key with a different body or URL is detected.
     */
    private function requestHash(Request $request): string
    {
        $route = $request->route();
        $routeParameters = is_object($route) ? $route->originalParameters() : [];
        $input = $request->all();

        ksort($routeParameters);
        $this->sortRecursively($input);

        return hash('sha256', (string) json_encode([$routeParameters, $input]));
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private function sortRecursively(array &$values): void
    {
        ksort($values);

        foreach ($values as &$nestedValue) {
            if (is_array($nestedValue)) {
                $this->sortRecursively($nestedValue);
            }
        }
    }
}
