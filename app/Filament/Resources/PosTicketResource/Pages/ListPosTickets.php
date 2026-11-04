<?php

namespace App\Filament\Resources\PosTicketResource\Pages;

use App\Filament\Resources\PosTicketResource;
use Filament\Resources\Pages\ListRecords;

class ListPosTickets extends ListRecords
{
    protected static string $resource = PosTicketResource::class;

    // BEXIA_POS_MOV_FILTERS_FIX4B
    protected static string $view =
        'filament.pos-tickets.list-pos-tickets';



    // BEXIA_POS_MOV_FIX7D
    // Inicializa los filtros Livewire, no solamente los defaults PHP.
    public function mount(): void
    {
        parent::mount();

        $today = now('America/Mexico_City')->toDateString();

        $filters = is_array($this->tableFilters ?? null)
            ? $this->tableFilters
            : [];

        $filters['movement_datetime_v8'] = [
            'from' => $today . 'T00:00',
            'until' => $today . 'T23:59',
        ];

        $this->tableFilters = $filters;
    }

public function getTitle(): string
    {
        // BEXIA_V582_P3_XLSM_A8B_LIST_TITLE
        return 'Movimientos PDV';
    }
}
