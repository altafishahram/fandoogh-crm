<?php

declare(strict_types=1);

namespace App\Filament\Shared\Pages;

use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

final class Profile extends EditProfile
{
    private bool $completedRequiredPasswordChange = false;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        $passwordChanged = $record instanceof User && array_key_exists('password', $data) && filled($data['password']);
        if ($passwordChanged) {
            $this->completedRequiredPasswordChange = $record->must_change_password;
            $data['must_change_password'] = false;
        }

        $updated = parent::handleRecordUpdate($record, $data);
        if ($passwordChanged && $updated instanceof User) {
            $updated->tokens()->delete();
            if (request()->hasSession()) {
                request()->session()->regenerate();
            }
        }

        return $updated;
    }

    protected function getRedirectUrl(): ?string
    {
        if (! $this->completedRequiredPasswordChange) {
            return parent::getRedirectUrl();
        }

        return Filament::getCurrentPanel()?->getUrl();
    }
}
