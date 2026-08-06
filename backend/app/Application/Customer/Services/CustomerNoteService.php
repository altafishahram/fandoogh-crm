<?php

declare(strict_types=1);

namespace App\Application\Customer\Services;

use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\User;

final class CustomerNoteService
{
    public function create(Customer $customer, User $actor, string $body): CustomerNote
    {
        return CustomerNote::query()->create([
            'customer_id' => $customer->getKey(),
            'author_user_id' => $actor->getKey(),
            'body' => trim($body),
        ]);
    }

    public function update(CustomerNote $note, string $body): CustomerNote
    {
        $note->body = trim($body);
        $note->save();

        return $note->refresh();
    }

    public function delete(CustomerNote $note): void
    {
        $note->delete();
    }
}
