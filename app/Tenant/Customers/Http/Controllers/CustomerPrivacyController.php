<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Controllers;

use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Http\Controller;
use App\Tenant\Customers\Actions\EraseCustomer;
use App\Tenant\Customers\Actions\RequestCustomerDataExport;
use App\Tenant\Customers\Exceptions\CustomerEmailNotVerifiedException;
use App\Tenant\Customers\Http\Requests\EraseCustomerRequest;
use App\Tenant\Customers\Http\Resources\CustomerDataExportResource;
use App\Tenant\Customers\Models\Customer;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The signed-in customer's own data-protection requests: downloading everything the store holds about them, and erasing it.
 */
final class CustomerPrivacyController extends Controller
{
    /**
     * List my data exports.
     *
     * Newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return CustomerDataExportResource::collection($this->customer($request)->dataExports()->orderByDesc('id')->get());
    }

    /**
     * Ask for my data.
     *
     * Builds a file of everything the store holds about you, including staff
     * notes, in the background; "list my data exports" shows when it is
     * ready. Needs a confirmed email. Asking again while one is being built
     * returns that one.
     *
     * @throws CustomerEmailNotVerifiedException
     */
    public function store(Request $request, RequestCustomerDataExport $requestCustomerDataExport): JsonResponse
    {
        return (new CustomerDataExportResource($requestCustomerDataExport->handle($this->customer($request))))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    /**
     * Download my data.
     *
     * A JSON file, while the export is ready and hasn't expired. Needs a confirmed email.
     *
     * @throws CustomerEmailNotVerifiedException
     */
    public function download(Request $request, FilesystemFactory $filesystems, string $export): StreamedResponse
    {
        $customer = $this->customer($request);

        if (! $customer->hasVerifiedEmail()) {
            throw new CustomerEmailNotVerifiedException;
        }

        $dataExport = $customer->dataExports()->where('public_id', $export)->firstOrFail();

        if (! $dataExport->isDownloadable()) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $filesystems->disk($dataExport->disk)->download((string) $dataExport->path, 'my-data-'.$dataExport->public_id.'.json', ['Content-Type' => 'application/json']);
    }

    /**
     * Erase my account.
     *
     * Needs the current password. Erases everything the store holds about
     * you and signs you out everywhere; it can't be undone. Past orders stay,
     * without your details.
     *
     * @throws IncorrectCurrentPasswordException
     * @throws TooManyIncorrectAttemptsException
     */
    public function destroy(EraseCustomerRequest $request, EraseCustomer $eraseCustomer): Response
    {
        $eraseCustomer->bySelf($request->customer(), $request->string('current_password')->value());

        return response()->noContent();
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            throw new LogicException('This route must be protected by auth:customer.');
        }

        return $customer;
    }
}
