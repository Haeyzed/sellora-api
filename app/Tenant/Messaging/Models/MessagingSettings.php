<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Models;

use App\Tenant\Messaging\Exceptions\MissingMessagingSettingsException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Who the store's emails come from, where replies go, and which staff alerts are emailed. One row, created with the store (MessagingSettingsDefaults), never on read.
 *
 * @property int $id Always 1.
 * @property string|null $sender_name The name emails come from; null for the store's own name.
 * @property string|null $reply_to_email Where replies go; null for the store's contact email.
 * @property bool $has_new_order_alert_emails Whether staff who fulfil orders are emailed about each new order (it is always in their alert list).
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class MessagingSettings extends Model implements AuditableContract
{
    use Auditable;

    public $incrementing = false;

    protected $table = 'messaging_settings';

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id', 'updated_at'];

    /**
     * The store's messaging settings.
     *
     * @throws MissingMessagingSettingsException When the store has none: a bug, never filled in with defaults.
     */
    public static function current(): self
    {
        return self::query()->find(1) ?? throw new MissingMessagingSettingsException;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_new_order_alert_emails' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
