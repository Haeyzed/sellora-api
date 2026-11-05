<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\FindRefundDestinations;
use App\Tenant\Returns\Enums\ReturnStatus;
use App\Tenant\Returns\Events\ReturnRefundDestinationChosen;
use App\Tenant\Returns\Exceptions\RefundDestinationChoiceClosedException;
use App\Tenant\Returns\Exceptions\RefundDestinationNotOfferedException;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnRefundChoice;
use App\Tenant\Returns\Services\ReturnProgress;
use App\Tenant\Returns\Services\ReturnRefundAttempts;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Records where the customer wants a return's refund to go (section 13): chosen by the signed-in customer whose order it is, or recorded by staff at the customer's request, with a note saying how they asked. Staff never choose for the customer.
 *
 * Only a destination on offer for the order now can be chosen: a module's
 * only for the order's own customer while the module is Enabled. The choice
 * is open until a refund attempt starts, and reopens only once the last
 * attempt definitely got nothing back (refused, or failed in full, such as a
 * destination that clearly refused); never while one may be giving money
 * back. The next attempt follows the latest choice. Read under the return's
 * lock, so a choice and a refund never cross. Staff recordings go in the
 * store's activity log.
 */
final readonly class ChooseRefundDestination
{
    public function __construct(
        private ReturnProgress $returnProgress,
        private ReturnRefundAttempts $attempts,
        private FindRefundDestinations $findRefundDestinations,
    ) {}

    /**
     * @param  Customer|StaffMember  $by  The customer choosing, or staff recording their choice.
     * @param  string|null  $note  How the customer asked; required when staff record it.
     *
     * @throws RefundDestinationChoiceClosedException
     * @throws RefundDestinationNotOfferedException
     * @throws InvalidArgumentException When staff record a choice without a note.
     */
    public function handle(OrderReturn $return, string $destination, Customer|StaffMember $by, ?string $note = null): ReturnRefundChoice
    {
        $note = $note === null ? null : trim($note);

        if ($by instanceof StaffMember && ($note === null || $note === '')) {
            throw new InvalidArgumentException('Staff recording a refund destination must say how the customer asked.');
        }

        return DB::transaction(function () use ($return, $destination, $by, $note): ReturnRefundChoice {
            $locked = $this->returnProgress->lock($return);
            $this->ensureChoiceIsOpen($locked);
            $order = Order::query()->with('customer')->findOrFail($locked->order_id);

            if (! $this->findRefundDestinations->isOffered($destination, $order, $order->customer)) {
                throw new RefundDestinationNotOfferedException;
            }

            $account = AccountReference::to($by);
            $choice = new ReturnRefundChoice;
            $choice->forceFill([
                'return_id' => $locked->id,
                'destination' => $destination,
                'chosen_by_type' => $account->type,
                'chosen_by_id' => $account->publicId,
                'note' => $by instanceof StaffMember ? $note : null,
            ])->save();

            if ($by instanceof StaffMember) {
                $this->logRecording($by, $locked->setRelation('order', $order), $destination);
            }

            ReturnRefundDestinationChosen::dispatch($locked, $choice);

            return $choice;
        });
    }

    /**
     * @throws RefundDestinationChoiceClosedException When the return is finished, or an attempt may be giving money back.
     */
    private function ensureChoiceIsOpen(OrderReturn $locked): void
    {
        $this->attempts->settle($locked);

        if (in_array($locked->status, [ReturnStatus::Rejected, ReturnStatus::Completed, ReturnStatus::Cancelled], true)) {
            throw new RefundDestinationChoiceClosedException;
        }

        $last = $locked->refunds()->reorder('attempt', 'desc')->first();

        if ($last !== null && ! $last->status->allowsNewAttempt()) {
            throw new RefundDestinationChoiceClosedException;
        }
    }

    /**
     * The note stays on the choice: it may hold personal data, and the activity log is kept longer.
     */
    private function logRecording(StaffMember $staffMember, OrderReturn $return, string $destination): void
    {
        activity('returns')
            ->causedBy($staffMember)
            ->performedOn($return)
            ->event('refund_destination_recorded')
            ->withProperties(['destination' => $destination])
            ->log('Recorded where the customer asked the refund of return '.$return->displayNumber().' to go');
    }
}
