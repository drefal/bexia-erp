<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Components\Tab;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('export_products_xlsx')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => \App\Support\ProductCatalogExportService::downloadProductsXlsx()),



            \Filament\Actions\Action::make('download_products_template')
                ->label('Plantilla carga masiva')
                ->icon('heroicon-o-table-cells')
                ->color('warning')
                ->action(fn () => \App\Support\ProductCatalogExportService::downloadTemplateXlsx()),


            \Filament\Actions\Action::make('validate_products_import')
                ->label('Validar importación')
                ->icon('heroicon-o-shield-check')
                ->color('primary')
                ->modalHeading('Validar importación de productos')
                ->modalDescription('Primero valida el archivo. Si no hay errores ni referencias internas duplicadas, se habilita el botón Procesar validación limpia.')
                ->modalSubmitActionLabel('Validar archivo')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('file')
                        ->label('Archivo Excel/CSV')
                        ->disk('local')
                        ->directory('imports/productos')
                        ->visibility('private')
                        ->preserveFilenames()
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/octet-stream',
                            'text/csv',
                            'text/plain',
                            'application/csv',
                        ])
                        ->required()
                        ->helperText('Formatos permitidos: .xlsx o .csv. Usa la plantilla descargada desde Bexia.'),
                ])
                ->action(fn (array $data) => \App\Support\ProductCatalogImportService::validateFromFilamentUpload(
                    $data['file'] ?? null,
                )),

            \Filament\Actions\Action::make('apply_validated_products_import')
                ->label('Procesar validación limpia')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->disabled(fn (): bool => ! \App\Support\ProductCatalogImportService::hasCleanValidationSession())
                ->tooltip(fn (): string => \App\Support\ProductCatalogImportService::hasCleanValidationSession()
                    ? 'Importar el último archivo validado sin errores.'
                    : 'Primero valida un archivo sin errores.')
                ->requiresConfirmation()
                ->modalHeading('Procesar productos validados')
                ->modalDescription('Se aplicará el último archivo validado correctamente para esta empresa. Antes de importar, Bexia volverá a validar el archivo.')
                ->modalSubmitActionLabel('Importar productos')
                ->action(fn () => \App\Support\ProductCatalogImportService::importLastValidatedFromSession()),


            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'productos' => Tab::make('Productos')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where(function (Builder $query): void {
                        $query
                            ->where('is_variant', false)
                            ->orWhereNull('is_variant');
                    })
                ),

            'variantes' => Tab::make('Variantes')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('is_variant', true)
                ),

            'todos' => Tab::make('Todos'),
        ];
    }

}
