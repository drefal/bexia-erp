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


    public function getTitle(): string
    {
        // BEXIA_V582_P3_XLSM_A8B_LIST_TITLE
        return 'Movimientos PDV';
    }
}
