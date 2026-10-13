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

    protected function getViewData(): array
    {
        return [
            'reportUrl' => url('/pos/sessions/' . $this->record->id . '/sales-report'),
            'closeTicketUrl' => url('/pos/sessions/' . $this->record->id . '/close-ticket/print'),
            'priceListChanges' => $this->priceListChanges(),
            // BEXIA_V5836_POS_REFUND_FIX2B8P9C
            'canViewSessionRefunds' =>
                (auth()->user()?->can('pos.menu.view') ?? false),
            'sessionRefundLedger' =>
                (auth()->user()?->can('pos.menu.view') ?? false)
                    ? $this->v5836SessionRefundLedger()
                    : ['refunds' => [], 'total' => 0.0],
            // BEXIA_V5836_POS53C1_SESSION_TICKETS
            'canViewSessionTickets' => (auth()->user()?->can('pos.menu.view') ?? false),
            'sessionTickets' => (auth()->user()?->can('pos.menu.view') ?? false)
                ? $this->v5836Pos53c1SessionTickets()
                : collect(),
            'canViewPriceListChanges' => auth()->user()?->can('pos.sessions.view_price_list_changes') ?? false,
            'canDownloadReport' => auth()->user()?->can('pos.sessions.download_report') ?? false,
            'canPrintCloseTicket' => auth()->user()?->can('pos.sessions.print_close_ticket') ?? false,
        ];
    }

    // BEXIA_V5836_POS_REFUND_FIX2B8P9C
    // Historial asociado a la sesion de devolucion,
    // independientemente de la fecha de venta original.
    protected function v5836SessionRefundLedger(): array
    {
        if (
            ! Schema::hasTable('pos_order_refunds')
            || ! Schema::hasTable('pos_order_refund_payments')
            || ! Schema::hasTable('pos_orders')
        ) {
            return ['refunds' => [], 'total' => 0.0];
        }

        $sessionId = (int) $this->record->id;
        $companyId = (int) $this->record->company_id;
        $posPointId = (int) $this->record->pos_point_id;

        $refunds = DB::table('pos_order_refunds as r')
            ->leftJoin(
                'pos_orders as o',
                'o.id',
                '=',
                'r.pos_order_id'
            )
            ->where('r.pos_session_id', $sessionId)
            ->where('r.company_id', $companyId)
            ->where('r.pos_point_id', $posPointId)
            ->whereNotIn('r.status', [
                'cancelled', 'canceled', 'void',
            ])
            ->orderBy('r.created_at')
            ->orderBy('r.id')
            ->get([
                'r.id',
                'r.number',
                'r.type',
                'r.status',
                'r.payment_total',
                'r.created_at',
                'r.created_by_user_id',
                'r.pos_order_id',
                'o.number as ticket_number',
            ]);

        $ids = $refunds->pluck('id')->all();

        $payments = $ids === []
            ? collect()
            : DB::table('pos_order_refund_payments')
                ->whereIn('pos_order_refund_id', $ids)
                ->orderBy('id')
                ->get()
                ->groupBy('pos_order_refund_id');

        $userIds = $refunds->pluck('created_by_user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $users = $userIds === []
            ? collect()
            : DB::table('users')
                ->whereIn('id', $userIds)
                ->pluck('name', 'id');

        $rows = [];
        $totalCents = 0;

        foreach ($refunds as $refund) {
            $refundCents = (int) round(
                abs((float) $refund->payment_total) * 100
            );
            $totalCents += $refundCents;

            $methods = [];

            foreach (
                $payments->get((int) $refund->id, collect())
                as $payment
            ) {
                $metadata = json_decode(
                    (string) ($payment->metadata ?? '{}'),
                    true
                );
                $metadata = is_array($metadata) ? $metadata : [];

                $methods[] = [
                    'id' => (int) $payment->id,
                    'method' => (string) (
                        $payment->payment_label ?: 'Sin metodo'
                    ),
                    'amount' => round(
                        abs((float) $payment->amount),
                        2
                    ),
                    'status' => (string) $payment->status,
                    'settlement_status' => (string) (
                        $metadata['settlement_status'] ?? ''
                    ),
                    'confirmed_at' => (string) (
                        $metadata['bank_confirmed_at'] ?? ''
                    ),
                    'confirmed_by' =>
                        $metadata['bank_confirmed_by_user_id'] ?? null,
                ];
            }

            $rows[] = [
                'id' => (int) $refund->id,
                'number' => (string) $refund->number,
                'ticket_id' => (int) $refund->pos_order_id,
                'ticket_number' => (string) (
                    $refund->ticket_number ?? ''
                ),
                'type' => (string) $refund->type,
                'status' => (string) $refund->status,
                'created_at' => (string) $refund->created_at,
                'user' => (string) (
                    $users->get($refund->created_by_user_id)
                    ?: 'Usuario #' . (
                        $refund->created_by_user_id ?? 'N/D'
                    )
                ),
                'amount' => $refundCents / 100,
                'methods' => $methods,
            ];
        }

        return [
            'refunds' => $rows,
            'total' => $totalCents / 100,
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
