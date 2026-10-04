<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Users\Pages;

use App\Application\User\Services\UpdateAgentPermissionsService;
use App\Application\User\Services\UpdateUserService;
use App\Filament\Agency\Resources\Users\UserResource;
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

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        if ($record instanceof User) {
            $data['permissions'] = $record->getDirectPermissions()->pluck('name')->values()->all();
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User && $record instanceof User, 401);

        $updated = app(UpdateUserService::class)->execute($actor, $record, [
            'name' => (string) $data['name'], 'email' => (string) $data['email'],
            'phone' => isset($data['phone']) ? (string) $data['phone'] : null,
        ]);
        $permissions = is_array($data['permissions'] ?? null)
            ? array_values(array_map('strval', $data['permissions'])) : [];
        $current = $updated->getDirectPermissions()->pluck('name')->sort()->values()->all();
        $requested = collect($permissions)->sort()->values()->all();
        if ($current !== $requested) {
            $updated = app(UpdateAgentPermissionsService::class)->execute($actor, $updated, $permissions);
        }

        return $updated;
    }
}
