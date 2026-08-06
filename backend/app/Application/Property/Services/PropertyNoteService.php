<?php

declare(strict_types=1);

namespace App\Application\Property\Services;

use App\Models\Property;
use App\Models\PropertyNote;
use App\Models\User;

final class PropertyNoteService
{
    public function create(Property $property, User $actor, string $body): PropertyNote
    {
        return PropertyNote::query()->create([
            'property_id' => $property->getKey(),
            'author_user_id' => $actor->getKey(),
            'body' => trim($body),
        ]);
    }

    public function update(PropertyNote $note, string $body): PropertyNote
    {
        $note->body = trim($body);
        $note->save();

        return $note->refresh();
    }

    public function delete(PropertyNote $note): void
    {
        $note->delete();
    }
}
