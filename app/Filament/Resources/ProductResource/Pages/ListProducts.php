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
                ->label('Carga masiva')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->modalWidth('5xl')
                ->modalHeading('Carga masiva de productos')
                ->modalDescription('Primero valida el archivo dentro de este modal. Si la validación queda limpia, se habilita la sección inferior para aplicar la importación.')
                ->modalSubmitActionLabel('Cerrar')
                ->modalSubmitAction(fn (\Filament\Actions\StaticAction $action): \Filament\Actions\StaticAction => $action
                    ->label('Cerrar')
                    ->color('gray')
                )
                ->form([
                    \Filament\Forms\Components\Section::make('1. Archivo')
                        ->description('Sube el Excel/CSV y después presiona “Validar archivo”. La validación ya no se ejecuta automáticamente al subir el archivo.')
                        ->schema([
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
                                ->helperText('Si cambias el archivo después de validar, vuelve a presionar Validar archivo antes de aplicar.'),
                        ]),

                    \Filament\Forms\Components\Hidden::make('validation_ok')
                        ->default(false)
                        ->dehydrated(true),

                    \Filament\Forms\Components\Hidden::make('validation_hash')
                        ->default('')
                        ->dehydrated(true),

                    \Filament\Forms\Components\Hidden::make('validation_html')
                        ->default('<div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">Sube un archivo y presiona Validar archivo.</div>')
                        ->dehydrated(false),

                    \Filament\Forms\Components\Section::make('2. Validación')
                        ->description('Bexia revisará estructura, productos existentes, duplicados y simulación de aplicación sin guardar cambios.')
                        ->schema([
                            \Filament\Forms\Components\Actions::make([
                                \Filament\Forms\Components\Actions\Action::make('validate_file_inside_modal')
                                    ->label('Validar archivo')
                                    ->icon('heroicon-o-shield-check')
                                    ->color('warning')
                                    ->action(function (\Filament\Forms\Get $get, \Filament\Forms\Set $set): void {
                                        $set('validation_ok', false);
                                        $set('validation_hash', '');
                                        $set('confirm_apply', false);

                                        $file = $get('file');

                                        if (blank($file)) {
                                            $set('validation_html', '<div class="rounded-lg border border-danger-300 bg-danger-50 p-3 text-sm text-danger-700"><div class="font-semibold">Falta archivo</div><div class="mt-1">Sube un archivo antes de validar.</div></div>');

                                            return;
                                        }

                                        $validation = \App\Support\ProductCatalogImportService::validateForModalFromFilamentUpload($file);

                                        $set('validation_ok', (bool) ($validation['ok'] ?? false));
                                        $set('validation_hash', (string) ($validation['hash'] ?? ''));
                                        $set('validation_html', (string) ($validation['html'] ?? ''));
                                        $set('confirm_apply', false);

                                        if ((bool) ($validation['ok'] ?? false)) {
                                            \Filament\Notifications\Notification::make()
                                                ->title('Validación limpia')
                                                ->body('El archivo no tiene errores. Puedes aplicar la importación en la sección inferior.')
                                                ->success()
                                                ->send();
                                        } else {
                                            \Filament\Notifications\Notification::make()
                                                ->title('Validación con errores')
                                                ->body('Revisa el resumen del modal. No se aplicará la importación hasta corregir el archivo.')
                                                ->danger()
                                                ->send();
                                        }
                                    }),
                            ]),

                            \Filament\Forms\Components\Placeholder::make('validation_summary')
                                ->label('Resultado')
                                ->content(fn (\Filament\Forms\Get $get): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                                    (string) ($get('validation_html') ?: '<div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">Sube un archivo y presiona Validar archivo.</div>')
                                )),
                        ]),

                    \Filament\Forms\Components\Section::make('3. Aplicación')
                        ->description('Esta sección solo debe usarse cuando la validación esté limpia. La importación se ejecuta en modo todo-o-nada.')
                        ->schema([
                            \Filament\Forms\Components\Placeholder::make('apply_ready_message')
                                ->label('Estado')
                                ->visible(fn (\Filament\Forms\Get $get): bool => (bool) $get('validation_ok'))
                                ->content(fn (): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                                    '<div class="rounded-lg border border-success-300 bg-success-50 p-3 text-sm text-success-800"><div class="font-semibold">Archivo listo para importar</div><div class="mt-1">Marca la confirmación y presiona Aplicar importación.</div></div>'
                                )),

                            \Filament\Forms\Components\Placeholder::make('apply_blocked_message')
                                ->label('Estado')
                                ->visible(fn (\Filament\Forms\Get $get): bool => ! (bool) $get('validation_ok'))
                                ->content(fn (): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                                    '<div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700"><div class="font-semibold">Importación bloqueada</div><div class="mt-1">Primero valida el archivo. Si hay errores, corrige el Excel y vuelve a validarlo.</div></div>'
                                )),

                            \Filament\Forms\Components\Toggle::make('confirm_apply')
                                ->label('Confirmo aplicar este archivo validado')
                                ->default(false)
                                ->live()
                                ->visible(fn (\Filament\Forms\Get $get): bool => (bool) $get('validation_ok'))
                                ->helperText('Bexia volverá a validar antes de aplicar. Si el archivo cambió, se bloqueará la importación.'),

                            \Filament\Forms\Components\Actions::make([
                                \Filament\Forms\Components\Actions\Action::make('apply_import_inside_modal')
                                    ->label('Aplicar importación')
                                    ->icon('heroicon-o-arrow-up-tray')
                                    ->color('primary')
                                    ->visible(fn (\Filament\Forms\Get $get): bool => (bool) $get('validation_ok'))
                                    ->disabled(fn (\Filament\Forms\Get $get): bool => ! (bool) $get('confirm_apply'))
                                    ->action(function (\Filament\Forms\Get $get): void {
                                        \App\Support\ProductCatalogImportService::importValidatedModalUpload(
                                            $get('file'),
                                            (string) ($get('validation_hash') ?? ''),
                                            (bool) ($get('confirm_apply') ?? false),
                                        );
                                    }),
                            ]),
                        ]),
                ])
                ->action(fn (): null => null),



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
