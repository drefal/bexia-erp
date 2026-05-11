<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        $tenantId = Filament::getTenant()?->getKey();
        $recordId = $this->record?->getKey();

        $accessUrl = ($tenantId && $recordId)
            ? url("/admin/{$tenantId}/user-accesses/{$recordId}/edit")
            : null;

        return [
            Actions\Action::make('manageAccess')
                ->label('Editar permisos')
                ->icon('heroicon-o-shield-check')
                ->color('gray')
                ->url(fn () => $accessUrl)
                ->visible(function () use ($accessUrl) {
                    $user = auth()->user();

                    if (! $user || ! filled($accessUrl)) {
                        return false;
                    }

                    return (bool) $user->is_system_admin || $user->can('users.update');
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
