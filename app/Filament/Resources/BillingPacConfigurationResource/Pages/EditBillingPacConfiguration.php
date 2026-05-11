<?php

namespace App\Filament\Resources\BillingPacConfigurationResource\Pages;

use App\Filament\Resources\BillingPacConfigurationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditBillingPacConfiguration extends EditRecord
{
    protected static string $resource = BillingPacConfigurationResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->forceFill($data);
        $record->save();

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('test_sw_connection')
                ->label('Probar conexión')
                ->icon('heroicon-o-bolt')
                ->color('info')
                ->visible(fn (): bool => BillingPacConfigurationResource::isSuperAdminUser())
                ->action(function (): void {
                    BillingPacConfigurationResource::notifySwConnection($this->record);
                    $this->record->refresh();
                }),
        ];
    }

    public function getTitle(): string
    {
        return 'PAC por empresa';
    }
}
