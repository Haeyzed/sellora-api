<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderContactCorrection;
use App\Tenant\Orders\Services\OrderActivity;
use App\Tenant\Orders\Services\OrderEventLog;
use App\Tenant\Orders\Services\OrderProgress;
use Illuminate\Support\Facades\DB;

/**
 * Staff correcting an order's contact details, such as a mistyped email or phone (section 13): a new version, kept beside what the order was placed with, which never changes.
 *
 * A corrected email is where the store writes to from now on; it never links
 * the order to an account and never changes whose first order it was. The
 * history and the activity log say which details changed, never their values.
 */
final readonly class CorrectOrderContact
{
    public function __construct(
        private OrderProgress $orderProgress,
        private OrderEventLog $orderEventLog,
        private OrderActivity $orderActivity,
    ) {}

    public function handle(StaffMember $actor, Order $order, string $email, ?string $name, ?string $phone): Order
    {
        return DB::transaction(function () use ($actor, $order, $email, $name, $phone): Order {
            $locked = $this->orderProgress->lock($order);
            $current = $locked->currentContact();
            $email = mb_strtolower(trim($email));
            $changed = array_keys(array_filter(['email' => $email !== $current->email, 'name' => $name !== $current->name, 'phone' => $phone !== $current->phone]));

            if ($changed === []) {
                return $locked;
            }

            (new OrderContactCorrection)->forceFill([
                'order_id' => $locked->id,
                'email' => $email,
                'name' => $name,
                'phone' => $phone,
                'created_by_type' => $actor->getMorphClass(),
                'created_by_id' => $actor->public_id,
            ])->save();

            $this->orderEventLog->record($locked, OrderEventType::ContactCorrected, ['fields' => $changed], AccountReference::to($actor));
            $this->orderActivity->record($actor, $locked, 'order_contact_corrected', 'Corrected the contact details of', $changed);

            return $locked->unsetRelation('contactCorrections');
        });
    }
}
