<?php

namespace App\Filament\Admin\Resources\UserAccessRosource\Pages;

use App\Filament\Admin\Resources\UserAccessRosource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserAccess extends EditRecord
{
    protected static string $resource = UserAccessRosource::class;

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
