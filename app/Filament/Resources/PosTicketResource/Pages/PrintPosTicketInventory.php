<?php

namespace App\Filament\Resources\PosTicketResource\Pages;

use App\Filament\Resources\PosTicketResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PrintPosTicketInventory extends ViewRecord
{
    protected static string $resource = PosTicketResource::class;

    protected static string $view = 'filament.pos-tickets.print-inventory-output';

    public function getTitle(): string
    {
        return 'PDF salida - ' . ($this->record->number ?: ('#' . $this->record->id));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_inventory')
                ->label('Volver a salida')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(PosTicketResource::getUrl('inventory', ['record' => $this->record])),

            Actions\Action::make('print')
                ->label('Imprimir / guardar PDF')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->extraAttributes([
                    'onclick' => 'window.print(); return false;',
                ]),
        ];
    }

    protected function getViewData(): array
    {
        $metadata = PosTicketResource::metadataArray($this->record);
        $movementId = (int) ($metadata['stock_movement_id'] ?? 0);

        $movement = null;
        $movementLines = collect();
        $products = collect();

        if ($movementId > 0 && Schema::hasTable('stock_movements')) {
            $movement = DB::table('stock_movements')->where('id', $movementId)->first();
        }

        if ($movementId > 0 && Schema::hasTable('stock_movement_lines')) {
            $movementLines = DB::table('stock_movement_lines')
                ->where('stock_movement_id', $movementId)
                ->orderBy('id')
                ->get();
        }

        $productIds = $movementLines
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($productIds->isNotEmpty() && Schema::hasTable('products')) {
            $products = DB::table('products')
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');
        }

        return [
            'order' => $this->record,
            'metadata' => $metadata,
            'movement' => $movement,
            'movementLines' => $movementLines,
            'products' => $products,
        ];
    }
}
