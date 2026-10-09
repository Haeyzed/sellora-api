<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Actions\CreateAttribute;
use App\Tenant\Catalog\Actions\DeleteAttribute;
use App\Tenant\Catalog\Actions\UpdateAttribute;
use App\Tenant\Catalog\Exceptions\AttributeInUseException;
use App\Tenant\Catalog\Http\Requests\ListAttributesRequest;
use App\Tenant\Catalog\Http\Requests\ManageAttributeRequest;
use App\Tenant\Catalog\Http\Requests\StoreAttributeRequest;
use App\Tenant\Catalog\Http\Requests\UpdateAttributeRequest;
use App\Tenant\Catalog\Http\Requests\ViewAttributeRequest;
use App\Tenant\Catalog\Http\Resources\AttributeResource;
use App\Tenant\Catalog\Models\Attribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The attributes the store's variants differ by, such as Size and Colour, for staff managing the catalog.
 */
final class AttributeController extends Controller
{
    /**
     * List attributes.
     *
     * Needs the catalog.view permission. In the order they were added, each with its values.
     */
    public function index(ListAttributesRequest $request): AnonymousResourceCollection
    {
        $attributes = Attribute::query()
            ->with('values')
            ->orderBy('position')
            ->orderBy('id')
            ->cursorPaginate($request->perPage());

        return AttributeResource::collection($attributes);
    }

    /**
     * Add an attribute.
     *
     * Needs the catalog.manage permission. The name, and each value's label,
     * must include the store's default language. Values can also be added
     * later.
     */
    public function store(StoreAttributeRequest $request, CreateAttribute $createAttribute): JsonResponse
    {
        return (new AttributeResource($createAttribute->handle($request->name(), $request->valueLabels())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show an attribute.
     *
     * Needs the catalog.view permission.
     */
    public function show(ViewAttributeRequest $request, Attribute $attribute): AttributeResource
    {
        return new AttributeResource($attribute->load('values'));
    }

    /**
     * Rename an attribute.
     *
     * Needs the catalog.manage permission. Only the languages sent change.
     */
    public function update(UpdateAttributeRequest $request, Attribute $attribute, UpdateAttribute $updateAttribute): AttributeResource
    {
        return new AttributeResource($updateAttribute->handle($attribute, $request->name())->load('values'));
    }

    /**
     * Delete an attribute.
     *
     * Needs the catalog.manage permission. Deletes its values too, for good.
     * Refused while a product varies by it or a variant, even one in the
     * trash, has one of its values.
     *
     * @throws AttributeInUseException
     */
    public function destroy(ManageAttributeRequest $request, Attribute $attribute, DeleteAttribute $deleteAttribute): Response
    {
        $deleteAttribute->handle($attribute);

        return response()->noContent();
    }
}
