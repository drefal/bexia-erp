<?php

namespace App\Filament\Resources\PosSessionResource\Pages;

use App\Filament\Resources\PosSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ViewPosSession extends ViewRecord
{
    protected static string $resource = PosSessionResource::class;

    protected static string $view = 'filament.resources.pos-session-resource.pages.view-pos-session';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_sales_report')
                ->label('Descargar reporte')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('pos.sessions.download_report') ?? false)
                ->url(fn (): string => url('/pos/sessions/' . $this->record->id . '/sales-report'))
                ->openUrlInNewTab(),

            Actions\Action::make('print_close_ticket')
                ->label('Imprimir ticket cierre')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->visible(fn (): bool => auth()->user()?->can('pos.sessions.print_close_ticket') ?? false)
                ->url(fn (): string => url('/pos/sessions/' . $this->record->id . '/close-ticket/print'))
                ->openUrlInNewTab(),

            Actions\Action::make('open_pos')
                ->label('Abrir PDV')
                ->icon('heroicon-o-computer-desktop')
                ->color('primary')
                ->visible(fn (): bool => (string) $this->record->status === 'open' && (auth()->user()?->can('pos.sessions.open_pos') ?? false))
                ->url(fn (): string => url('/pos/sessions/' . $this->record->id . '/screen'))
                ->openUrlInNewTab(),
        ];
    }

    public function getTitle(): string
    {
        return 'Sesión PDV ' . ($this->record->number ?? ('#' . $this->record->id));
    }

    protected function getViewData(): array
    {
        return [
            'reportUrl' => url('/pos/sessions/' . $this->record->id . '/sales-report'),
            'closeTicketUrl' => url('/pos/sessions/' . $this->record->id . '/close-ticket/print'),
            'priceListChanges' => $this->priceListChanges(),
            'canViewPriceListChanges' => auth()->user()?->can('pos.sessions.view_price_list_changes') ?? false,
            'canDownloadReport' => auth()->user()?->can('pos.sessions.download_report') ?? false,
            'canPrintCloseTicket' => auth()->user()?->can('pos.sessions.print_close_ticket') ?? false,
        ];
    }

    protected function priceListChanges()
    {
        if (! Schema::hasTable('pos_price_list_changes')) {
            return collect();
        }

        $query = DB::table('pos_price_list_changes as plc')
            ->where('plc.pos_session_id', $this->record->id)
            ->orderByDesc('plc.changed_at')
            ->orderByDesc('plc.id');

        if (Schema::hasTable('users')) {
            $query->leftJoin('users as u', 'u.id', '=', 'plc.user_id');
        }

        if (Schema::hasTable('contacts')) {
            $query->leftJoin('contacts as c', 'c.id', '=', 'plc.customer_id');
        }

        return $query
            ->limit(300)
            ->get([
                'plc.id',
                'plc.pos_session_id',
                'plc.user_id',
                'plc.customer_id',
                'plc.previous_price_list_id',
                'plc.previous_price_list_name',
                'plc.new_price_list_id',
                'plc.new_price_list_name',
                'plc.source',
                'plc.changed_at',
                DB::raw(Schema::hasTable('users') ? 'u.name as user_name' : 'NULL as user_name'),
                DB::raw(Schema::hasTable('contacts') ? 'c.name as customer_name' : 'NULL as customer_name'),
            ]);
    }
}
