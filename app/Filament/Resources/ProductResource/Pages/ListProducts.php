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
                ->modalWidth('5xl')
                ->modalHeading('Importar productos')
                ->modalDescription('Sube el Excel/CSV. Bexia validará el archivo dentro de este modal. Solo podrás aplicar cambios cuando la validación esté limpia.')
                ->modalSubmitActionLabel('Procesar importación')
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
                        ->live()
                        ->afterStateUpdated(function ($state, \Filament\Forms\Set $set): void {
                            $set('validation_ok', false);
                            $set('validation_hash', '');

                            if (blank($state)) {
                                $set('validation_html', '<div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">Sube un archivo para iniciar la validación.</div>');
                                return;
                            }

                            $validation = \App\Support\ProductCatalogImportService::validateForModalFromFilamentUpload($state);

                            $set('validation_ok', (bool) ($validation['ok'] ?? false));
                            $set('validation_hash', (string) ($validation['hash'] ?? ''));
                            $set('validation_html', (string) ($validation['html'] ?? ''));
                            $set('confirm_apply', false);
                        })
                        ->helperText('Formatos permitidos: .xlsx o .csv. Usa la plantilla descargada desde Bexia.'),

                    \Filament\Forms\Components\Hidden::make('validation_ok')
                        ->default(false)
                        ->dehydrated(true),

                    \Filament\Forms\Components\Hidden::make('validation_hash')
                        ->default('')
                        ->dehydrated(true),

                    \Filament\Forms\Components\Hidden::make('validation_html')
                        ->default('<div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">Sube un archivo para iniciar la validación.</div>')
                        ->dehydrated(false),

                    \Filament\Forms\Components\Placeholder::make('validation_summary')
                        ->label('Resultado de validación')
                        ->content(fn (\Filament\Forms\Get $get): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                            (string) ($get('validation_html') ?: '<div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">Sube un archivo para iniciar la validación.</div>')
                        )),

                    \Filament\Forms\Components\Toggle::make('confirm_apply')
                        ->label('Confirmo aplicar este archivo validado')
                        ->default(false)
                        ->visible(fn (\Filament\Forms\Get $get): bool => (bool) $get('validation_ok'))
                        ->helperText('Solo aparece cuando el archivo no tiene errores de validación.'),
                ])
                ->action(fn (array $data) => \App\Support\ProductCatalogImportService::importValidatedModalUpload(
                    $data['file'] ?? null,
                    (string) ($data['validation_hash'] ?? ''),
                    (bool) ($data['confirm_apply'] ?? false),
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

            // BEXIA_V5729O_TABS_LOTES_SERIES_PRODUCTOS
            // Filtros rápidos superiores para productos/variantes con seguimiento especial.
            'lotes' => Tab::make('Lotes')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('tracking', 'lot')
                ),

            'series' => Tab::make('Series')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('tracking', 'serial')
                ),
        ];
    }

}
