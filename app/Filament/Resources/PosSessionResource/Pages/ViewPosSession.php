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

            // BEXIA_V5836_PDV1I_RESEND_ACTION
            Actions\Action::make('resend_close_email')
                ->label('Reenviar correo')
                ->icon('heroicon-o-envelope')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Reenviar correo de cierre')
                ->modalDescription(
                    'Se enviará nuevamente el corte de caja y el reporte de cierre a los correos configurados en este punto de venta.'
                )
                ->modalSubmitActionLabel('Sí, reenviar')
                ->visible(function (): bool {
                    if (
                        (string) $this->record->status
                        !== 'closed'
                    ) {
                        return false;
                    }

                    if (
                        ! (
                            auth()
                                ->user()
                                ?->can(
                                    'pos.sessions.download_report'
                                )
                            ?? false
                        )
                    ) {
                        return false;
                    }

                    return
                        \Illuminate\Support\Facades\DB::table(
                            'pos_points'
                        )
                            ->where(
                                'id',
                                (int) $this->record->pos_point_id
                            )
                            ->where(
                                'session_close_email_enabled',
                                true
                            )
                            ->exists();
                })
                ->action(function (): void {
                    try {
                        app(
                            \App\Http\Controllers\PosController::class
                        )->v5836Pdv1SendCloseEmail(
                            (int) $this->record->id
                        );

                        \Filament\Notifications\Notification::make()
                            ->title(
                                'Correo de cierre enviado'
                            )
                            ->body(
                                'El corte y el reporte fueron procesados para los destinatarios configurados.'
                            )
                            ->success()
                            ->send();

                    } catch (\Throwable $e) {

                        \Illuminate\Support\Facades\Log::error(
                            'PDV_CLOSE_RESEND_UI: fallo',
                            [
                                'session_id' =>
                                    (int) $this->record->id,

                                'error' =>
                                    $e->getMessage(),
                            ]
                        );

                        \Filament\Notifications\Notification::make()
                            ->title(
                                'No se pudo reenviar el correo'
                            )
                            ->body(
                                $e->getMessage()
                            )
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

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

    /* BEXIA_POS_ACCESS9_FIX4A_ADMIN */
    protected function bexiaAccess9ShiftRows()
    {
        $user = auth()->user();

        if (
            !($user?->can('pos.sessions.download_report') ?? false)
            || !($user?->can('pos.sessions.view_detail') ?? false)
            || !Schema::hasTable('pos_cashier_shifts')
        ) {
            return collect();
        }

        $companyId = (int) $this->record->company_id;
        $pointId = (int) $this->record->pos_point_id;
        $sessionId = (int) $this->record->id;

        $shifts = DB::table('pos_cashier_shifts as s')
            ->leftJoin('employees as e', function ($join) use ($companyId) {
                $join->on('e.id', '=', 's.employee_id')
                    ->where('e.company_id', '=', $companyId);
            })
            ->where('s.company_id', $companyId)
            ->where('s.pos_point_id', $pointId)
            ->where('s.pos_session_id', $sessionId)
            ->orderBy('s.id')
            ->get([
                's.id',
                's.employee_id',
                'e.name as employee_name',
                's.status',
                's.opened_at',
                's.closed_at',
            ]);

        if ($shifts->isEmpty()) {
            return collect();
        }

        // Reporte exacto por ID de turno, limitado
        // a 10 000 pagos por sesion. Fallar cerrado
        // ante una sesion demasiado grande.
        $payments = DB::table('pos_order_payments as p')
            ->join('pos_orders as o', 'o.id', '=', 'p.pos_order_id')
            ->where('p.pos_session_id', $sessionId)
            ->where('p.status', 'paid')
            ->where('o.company_id', $companyId)
            ->where('o.pos_point_id', $pointId)
            ->limit(10001)
            ->get([
                'p.id',
                'p.pos_order_id',
                'p.amount',
                'p.created_at',
                'p.metadata',
            ]);

        if ($payments->count() > 10000) {
            return collect();
        }

        $counts = [];
        $totals = [];
        $tickets = [];

        $indexed = $shifts->keyBy('id');

        foreach ($payments as $payment) {
            $meta = json_decode(
                (string) $payment->metadata,
                true
            );

            if (!is_array($meta)) {
                continue;
            }

            $shiftId = (int) (
                $meta['pos_cashier_shift_id'] ?? 0
            );

            $shift = $indexed->get($shiftId);

            if (!$shift) {
                continue;
            }

            // No atribuir importes inconsistentes.
            if (
                (int) ($meta['paid_by_employee_id'] ?? 0)
                    !== (int) $shift->employee_id
                || (string) $payment->created_at
                    < (string) $shift->opened_at
                || (
                    $shift->closed_at !== null
                    && (string) $payment->created_at
                        > (string) $shift->closed_at
                )
            ) {
                continue;
            }

            $counts[$shiftId] = ($counts[$shiftId] ?? 0) + 1;
            $totals[$shiftId] = ($totals[$shiftId] ?? 0)
                + (int) round((float) $payment->amount * 100);

            $tickets[$shiftId][(int) $payment->pos_order_id] = true;
        }

        return $shifts->map(function ($shift) use (
            $counts, $totals, $tickets
        ) {
            $id = (int) $shift->id;

            return [
                'id' => $id,
                'employee' => (string) (
                    $shift->employee_name
                    ?: 'Empleado #' . $shift->employee_id
                ),
                'status' => (string) $shift->status,
                'opened_at' => (string) $shift->opened_at,
                'closed_at' => $shift->closed_at,
                'payment_count' => (int) ($counts[$id] ?? 0),
                'ticket_count' => count($tickets[$id] ?? []),
                'total' => (int) ($totals[$id] ?? 0) / 100,
            ];
        });
    }

    protected function getViewData(): array
    {
        return [
            'reportUrl' => url('/pos/sessions/' . $this->record->id . '/sales-report'),
            'closeTicketUrl' => url('/pos/sessions/' . $this->record->id . '/close-ticket/print'),
            'priceListChanges' => $this->priceListChanges(),
            // BEXIA_V5836_POS53C1_SESSION_TICKETS
            'canViewSessionTickets' => (auth()->user()?->can('pos.menu.view') ?? false),
            'sessionTickets' => (auth()->user()?->can('pos.menu.view') ?? false)
                ? $this->v5836Pos53c1SessionTickets()
                : collect(),
            // BEXIA_POS_ACCESS9_FIX4A_ADMIN
            'access9ShiftRows' => $this->bexiaAccess9ShiftRows(),
            'canViewAccess9Shifts' =>
                (auth()->user()?->can('pos.sessions.download_report') ?? false)
                && (auth()->user()?->can('pos.sessions.view_detail') ?? false),

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

    protected function v5836Pos53c1SessionTickets()
    {
        if (
            ! Schema::hasTable('pos_orders')
            || ! Schema::hasTable('pos_order_payments')
            || ! Schema::hasColumn('pos_order_payments', 'pos_session_id')
        ) {
            return collect();
        }

        $sessionId = (int) $this->record->id;
        $companyId = (int) ($this->record->company_id ?? 0);
        $posPointId = (int) ($this->record->pos_point_id ?? 0);

        $sessionPayments = DB::table('pos_order_payments')
            ->select('pos_order_id')
            ->selectRaw('SUM(amount) AS session_paid')
            ->selectRaw('COUNT(*) AS session_payment_count')
            ->where('pos_session_id', $sessionId)
            ->where('status', 'paid')
            ->groupBy('pos_order_id');

        // BEXIA_V5836_POS53C1A_PAYMENT_COUNT
        $allPayments = DB::table('pos_order_payments')
            ->select('pos_order_id')
            ->selectRaw('COUNT(*) AS all_payment_count')
            ->where('status', 'paid')
            ->groupBy('pos_order_id');

        return DB::table('pos_orders as o')
            ->leftJoinSub($allPayments, 'ap', function ($join) {
                $join->on('ap.pos_order_id', '=', 'o.id');
            })
            ->leftJoinSub($sessionPayments, 'sp', function ($join) {
                $join->on('sp.pos_order_id', '=', 'o.id');
            })
            ->where('o.company_id', $companyId)
            ->where('o.pos_point_id', $posPointId)
            ->where(function ($query) use ($sessionId) {
                $query->where('o.pos_session_id', $sessionId)
                    ->orWhereNotNull('sp.pos_order_id');
            })
            ->select(
                'o.id',
                'o.number',
                'o.status',
                'o.total',
                'o.created_at',
                'o.pos_session_id'
            )
            ->selectRaw('COALESCE(sp.session_paid, 0) AS session_paid')
            ->selectRaw('COALESCE(sp.session_payment_count, 0) AS session_payment_count')
            ->selectRaw('COALESCE(ap.all_payment_count, 0) AS all_payment_count')
            ->orderBy('o.id')
            ->get();
    }


}
