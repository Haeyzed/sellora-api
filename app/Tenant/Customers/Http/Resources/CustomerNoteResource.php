<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Resources;

use App\Tenant\Customers\Models\CustomerNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A staff note about a customer, as staff see it.
 *
 * @property CustomerNote $resource
 */
final class CustomerNoteResource extends JsonResource
{
    public function __construct(CustomerNote $note)
    {
        parent::__construct($note);
    }

    /**
     * @return array{id: string, body: string, author: array{id: string, name: string}|null, created_at: string}
     */
    public function toArray(Request $request): array
    {
        $note = $this->resource;
        $author = $note->author;

        return [
            'id' => $note->public_id,
            'body' => $note->body,
            /** The staff member who wrote it; null if their account no longer exists. */
            'author' => $author === null ? null : ['id' => $author->public_id, 'name' => $author->name],
            'created_at' => $note->created_at->toIso8601String(),
        ];
    }
}
