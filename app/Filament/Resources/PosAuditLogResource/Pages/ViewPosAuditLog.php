<?php

namespace App\Filament\Resources\PosAuditLogResource\Pages;

use App\Filament\Resources\PosAuditLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPosAuditLog extends ViewRecord
{
    protected static string $resource = PosAuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
