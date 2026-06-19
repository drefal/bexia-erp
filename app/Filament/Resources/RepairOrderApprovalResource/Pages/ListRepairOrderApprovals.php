<?php

namespace App\Filament\Resources\RepairOrderApprovalResource\Pages;

use App\Filament\Resources\RepairOrderApprovalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRepairOrderApprovals extends ListRecords
{
    protected static string $resource = RepairOrderApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear aprobación')
                ->visible(fn (): bool => RepairOrderApprovalResource::canCreate()),
        ];
    }
}
