<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Users\Pages;

use App\Application\User\Data\CreateUserData;
use App\Application\User\Services\CreateUserService;
use App\Domain\User\Enums\RoleName;
use App\Filament\Agency\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);
        $agency = $actor->agency()->firstOrFail();

        return app(CreateUserService::class)->execute($actor, $agency, new CreateUserData(
            (string) $data['name'], (string) $data['email'], isset($data['phone']) ? (string) $data['phone'] : null,
            (string) $data['temporary_password'], RoleName::Agent,
        ));
    }
}
