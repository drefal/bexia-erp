<?php

namespace App\Filament\Resources\UserAccessResource\Pages;

use App\Filament\Resources\UserAccessResource;
use Filament\Resources\Pages\ListRecords;

class ListUserAccess extends ListRecords
{
    protected static string $resource = UserAccessResource::class;

    public function getTitle(): string
    {
        return 'Permisos de usuario';
    }

    public function getBreadcrumbs(): array
    {
        return [
            UserAccessResource::getUrl('index') => 'Usuarios',
            null => 'Listado',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
