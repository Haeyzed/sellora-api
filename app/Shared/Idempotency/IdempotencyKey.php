<?php

declare(strict_types=1);

namespace App\Shared\Idempotency;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A record of one request sent with an Idempotency-Key, so a retry of the same request returns the first answer instead of running twice.
 *
 * Stored in the database of the context the request ran in: the store's own
 * database for store requests, the central database for platform requests.
 * Responses are encrypted, because they can contain personal data.
 *
 * @property int $id
 * @property string $scope
 * @property string $route
 * @property string $key
 * @property string $request_hash
 * @property IdempotencyStatus $status
 * @property int|null $response_status
 * @property string|null $response_content_type
 * @property string|null $response_body
 * @property Carbon $locked_until
 * @property Carbon $expires_at
 * @property Carbon $created_at
 */
final class IdempotencyKey extends Model
{
    public const null UPDATED_AT = null;

    protected $fillable = [
        'scope',
        'route',
        'key',
        'request_hash',
        'status',
        'response_status',
        'response_content_type',
        'response_body',
        'locked_until',
        'expires_at',
    ];

    /**
     * Whether the original request finished and its response can be replayed.
     */
    public function isCompleted(): bool
    {
        return $this->status === IdempotencyStatus::Completed;
    }

    /**
     * Whether the original request may still be running, so a duplicate must wait.
     */
    public function isLocked(): bool
    {
        return $this->locked_until->isFuture();
    }

    /**
     * Whether the replay window has passed, so the key no longer protects anything.
     */
    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'status' => IdempotencyStatus::class,
            'response_status' => 'integer',
            'response_body' => 'encrypted',
            'locked_until' => 'datetime',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
