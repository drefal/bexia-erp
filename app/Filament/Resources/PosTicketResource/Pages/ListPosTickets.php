<?php

namespace App\Filament\Resources\PosTicketResource\Pages;

use App\Filament\Resources\PosTicketResource;
use Filament\Resources\Pages\ListRecords;

class ListPosTickets extends ListRecords
{
    protected static string $resource = PosTicketResource::class;

    public function getTitle(): string
    {
        return 'Tickets PDV';
    }
}
