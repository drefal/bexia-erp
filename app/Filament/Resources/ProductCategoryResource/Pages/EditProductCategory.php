<?php

namespace App\Filament\Resources\ProductCategoryResource\Pages;

use App\Filament\Resources\ProductCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProductCategory extends EditRecord
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('archive_category')
                ->label('Archivar')
                ->icon('heroicon-o-archive-box')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Archivar categoría')
                ->modalDescription('Se archivará esta categoría y sus subcategorías. No se eliminarán productos ligados ni historial.')
                ->action(function (): void {
                    ProductCategoryResource::archiveCategoryRecord($this->record);

                    \Filament\Notifications\Notification::make()
                        ->title('Categoría archivada')
                        ->success()
                        ->send();

                    $this->redirect(ProductCategoryResource::getUrl('index'));
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->forceFill($data);
        $record->save();

        return $record;
    }
}
