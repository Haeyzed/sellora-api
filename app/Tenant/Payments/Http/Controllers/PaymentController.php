<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\RecordManualPayment;
use App\Tenant\Payments\Actions\VerifyPayment;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Exceptions\PaymentNotAwaitingReceiptException;
use App\Tenant\Payments\Exceptions\PaymentReceiptInvalidException;
use App\Tenant\Payments\Http\Requests\ConfirmPaymentRequest;
use App\Tenant\Payments\Http\Requests\ListPaymentsRequest;
use App\Tenant\Payments\Http\Requests\RecordManualPaymentRequest;
use App\Tenant\Payments\Http\Requests\ViewPaymentsRequest;
use App\Tenant\Payments\Http\Resources\PaymentResource;
use App\Tenant\Payments\Models\Payment;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Staff looking after the store's payments: what came in, recording money paid by hand, and asking a gateway again.
 */
final class PaymentController extends Controller
{
    private const array WITH = ['order', 'refunds'];

    /**
     * List payments.
     *
     * Needs the payments.view permission. Newest first. Narrow by status (such
     * as "needs_review" or "unresolved", to find what needs looking at),
     * method or mode.
     */
    public function index(ListPaymentsRequest $request): AnonymousResourceCollection
    {
        $query = Payment::query()->with(self::WITH);
        $request->applyFilters($query);

        return PaymentResource::collection($query->orderByDesc('id')->cursorPaginate($request->perPage()));
    }

    /**
     * List an order's payments.
     *
     * Needs the payments.view permission. Oldest first.
     */
    public function forOrder(ViewPaymentsRequest $request, Order $order): AnonymousResourceCollection
    {
        return PaymentResource::collection(Payment::query()->with(self::WITH)->where('order_id', $order->id)->orderBy('id')->get());
    }

    /**
     * Show a payment.
     *
     * Needs the payments.view permission. With its history.
     */
    public function show(ViewPaymentsRequest $request, Payment $payment): PaymentResource
    {
        return new PaymentResource($payment->load([...self::WITH, 'events']));
    }

    /**
     * Record a bank transfer received.
     *
     * Needs the payments.confirm permission. Once per payment, never edited:
     * a mistake is corrected with a refund. Less than expected opens a payment
     * for the rest, by the same deadline; more pays the order and is owed back.
     *
     * @throws PaymentNotAwaitingReceiptException
     * @throws PaymentReceiptInvalidException
     */
    public function receipt(RecordManualPaymentRequest $request, Payment $payment, RecordManualPayment $recordManualPayment): PaymentResource
    {
        return $this->record($request, $payment, $recordManualPayment, PaymentMethod::BankTransfer);
    }

    /**
     * Record cash collected on delivery.
     *
     * Needs the payments.confirm permission. Once per payment, never edited:
     * a mistake is corrected with a refund. Less than expected opens a payment
     * for the rest; more pays the order and is owed back.
     *
     * @throws PaymentNotAwaitingReceiptException
     * @throws PaymentReceiptInvalidException
     */
    public function collection(RecordManualPaymentRequest $request, Payment $payment, RecordManualPayment $recordManualPayment): PaymentResource
    {
        return $this->record($request, $payment, $recordManualPayment, PaymentMethod::CashOnDelivery);
    }

    /**
     * Ask the gateway again about a payment.
     *
     * Needs the payments.confirm permission. The gateway's own answer is the
     * only thing that moves a gateway payment, such as an unresolved one; a
     * gateway that doesn't answer changes nothing. A payment made by hand is
     * returned as it is.
     */
    public function check(ConfirmPaymentRequest $request, Payment $payment, VerifyPayment $verifyPayment): PaymentResource
    {
        return new PaymentResource($verifyPayment->handle($payment)->refresh()->load(self::WITH));
    }

    /**
     * @throws PaymentNotAwaitingReceiptException
     * @throws PaymentReceiptInvalidException
     */
    private function record(RecordManualPaymentRequest $request, Payment $payment, RecordManualPayment $recordManualPayment, PaymentMethod $method): PaymentResource
    {
        $recorded = $recordManualPayment->handle($request->actor(), $payment, $request->received($payment), $request->reference(), $method);

        return new PaymentResource($recorded->refresh()->load(self::WITH));
    }
}
