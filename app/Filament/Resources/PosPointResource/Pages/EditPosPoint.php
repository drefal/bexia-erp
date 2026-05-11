<?php

namespace App\Filament\Resources\PosPointResource\Pages;

use App\Filament\Resources\PosPointResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPosPoint extends EditRecord
{
    protected static string $resource = PosPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open_pos')
                ->label('Abrir sesión')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => url('/pos/' . $this->record->id . '/open'))
                ->openUrlInNewTab(),

            Actions\DeleteAction::make(),
        ];
    }
}
