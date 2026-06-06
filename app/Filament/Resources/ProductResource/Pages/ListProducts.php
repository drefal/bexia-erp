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

            \Filament\Actions\Action::make('export_products_pdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->action(fn () => \App\Support\ProductCatalogExportService::downloadProductsPdf()),

            \Filament\Actions\Action::make('download_products_template')
                ->label('Plantilla carga masiva')
                ->icon('heroicon-o-table-cells')
                ->color('warning')
                ->action(fn () => \App\Support\ProductCatalogExportService::downloadTemplateXlsx()),

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
