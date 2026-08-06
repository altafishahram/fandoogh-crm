<?php

declare(strict_types=1);

namespace App\Filament\Control\Resources\Users\Pages;

use App\Application\User\Services\UpdateUserService;
use App\Filament\Control\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User && $record instanceof User, 401);

        return app(UpdateUserService::class)->execute($actor, $record, [
            'name' => (string) $data['name'], 'email' => (string) $data['email'],
            'phone' => isset($data['phone']) ? (string) $data['phone'] : null,
        ]);
    }
}
