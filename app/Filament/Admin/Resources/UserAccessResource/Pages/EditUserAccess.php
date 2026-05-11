<?php

namespace App\Filament\Admin\Resources\UserAccessResource\Pages;

use App\Filament\Admin\Resources\UserAccessResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserAccess extends EditRecord
{
    protected static string $resource = UserAccessResource::class;

    public function getTitle(): string
    {
        return 'Editar permisos';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
