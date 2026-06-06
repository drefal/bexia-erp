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

            \Filament\Actions\Action::make('import_products')
                ->label('Importar productos')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->modalHeading('Importar productos desde plantilla')
                ->modalDescription('Sube el Excel/CSV con el mismo formato de la plantilla. Por seguridad, primero déjalo en modo validación. No se modifican existencias.')
                ->modalSubmitActionLabel('Procesar archivo')
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

                    \Filament\Forms\Components\Toggle::make('apply')
                        ->label('Aplicar cambios')
                        ->default(false)
                        ->helperText('Apagado: solo valida y descarga log. Encendido: crea/actualiza productos.'),
                ])
                ->action(fn (array $data) => \App\Support\ProductCatalogImportService::importFromFilamentUpload(
                    $data['file'] ?? null,
                    (bool) ($data['apply'] ?? false),
                )),

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
