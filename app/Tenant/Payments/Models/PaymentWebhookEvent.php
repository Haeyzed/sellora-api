<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Models;

use App\Tenant\Payments\Enums\GatewayWebhookKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A webhook a gateway sent, recorded once its signature checked out, once per gateway event ID. It only says which payment or refund to go and ask about.
 *
 * What it said is kept encrypted, and emptied after the retention period;
 * the event ID stays a while longer, so a late resend is still recognised.
 *
 * @property int $id
 * @property int $connection_id
 * @property string $gateway
 * @property string $event_id
 * @property GatewayWebhookKind $kind
 * @property string|null $reference Ours, of the payment or refund it is about.
 * @property string|null $payload Decrypted; null once emptied.
 * @property CarbonImmutable $received_at
 * @property CarbonImmutable|null $processed_at
 * @property-read PaymentGatewayConnection $connection
 */
final class PaymentWebhookEvent extends Model
{
    public $timestamps = false;

    /** Times are saved with their offset, so a time in any timezone means the same moment. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @var list<string>
     */
    protected $hidden = ['payload'];

    /**
     * @return BelongsTo<PaymentGatewayConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(PaymentGatewayConnection::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => GatewayWebhookKind::class,
            'payload' => 'encrypted',
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
