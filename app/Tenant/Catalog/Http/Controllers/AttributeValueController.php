<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Actions\AddAttributeValue;
use App\Tenant\Catalog\Actions\DeleteAttributeValue;
use App\Tenant\Catalog\Actions\UpdateAttributeValue;
use App\Tenant\Catalog\Exceptions\AttributeValueInUseException;
use App\Tenant\Catalog\Http\Requests\ManageAttributeRequest;
use App\Tenant\Catalog\Http\Requests\StoreAttributeValueRequest;
use App\Tenant\Catalog\Http\Requests\UpdateAttributeValueRequest;
use App\Tenant\Catalog\Http\Resources\AttributeValueResource;
use App\Tenant\Catalog\Models\Attribute;
use App\Tenant\Catalog\Models\AttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * An attribute's values, such as S, M and L for Size.
 */
final class AttributeValueController extends Controller
{
    /**
     * Add a value.
     *
     * Needs the catalog.manage permission. The label must include the store's
     * default language. The value goes after the attribute's other values.
     */
    public function store(StoreAttributeValueRequest $request, Attribute $attribute, AddAttributeValue $addAttributeValue): JsonResponse
    {
        return (new AttributeValueResource($addAttributeValue->handle($attribute, $request->label())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Change a value's label.
     *
     * Needs the catalog.manage permission. Only the languages sent change;
     * every variant with the value shows the new label.
     */
    public function update(UpdateAttributeValueRequest $request, Attribute $attribute, AttributeValue $value, UpdateAttributeValue $updateAttributeValue): AttributeValueResource
    {
        return new AttributeValueResource($updateAttributeValue->handle($value, $request->label()));
    }

    /**
     * Delete a value.
     *
     * Needs the catalog.manage permission. Refused while a variant, even one in the trash, has it.
     *
     * @throws AttributeValueInUseException
     */
    public function destroy(ManageAttributeRequest $request, Attribute $attribute, AttributeValue $value, DeleteAttributeValue $deleteAttributeValue): Response
    {
        $deleteAttributeValue->handle($value);

        return response()->noContent();
    }
}
