<?php

namespace App\Http\Controllers;

use App\Filament\Resources\PosTicketResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PublicInvoicePortalController extends Controller
{
    /*
     * BEXIA_V5528A_PUBLIC_INVOICE_PORTAL_VALIDATE_TICKET
     * Primera fase del portal público: validar ticket por folio + total.
     * No crea factura ni timbra CFDI todavía.
     */
    public function show(Request $request)
    {
        return view('pos.invoice-placeholder', [
            'ticket' => trim((string) $request->query('ticket', '')),
            'total' => trim((string) $request->query('total', '')),
            'result' => null,
        ]);
    }

    public function validateTicket(Request $request)
    {
        $ticket = trim((string) $request->input('ticket', ''));
        $totalInput = trim((string) $request->input('total', ''));

        $result = $this->buildValidationResult($ticket, $totalInput);

        return view('pos.invoice-placeholder', [
            'ticket' => $ticket,
            'total' => $totalInput,
            'result' => $result,
        ]);
    }

    protected function buildValidationResult(string $ticket, string $totalInput): array
    {
        if ($ticket === '') {
            return $this->error('Captura el folio del ticket.');
        }

        if ($totalInput === '' || ! is_numeric(str_replace(',', '', $totalInput))) {
            return $this->error('Captura el total del ticket.');
        }

        if (! Schema::hasTable('pos_orders')) {
            return $this->error('El módulo de tickets PDV no está disponible.');
        }

        $totalInput = (float) str_replace(',', '', $totalInput);

        $order = DB::table('pos_orders')
            ->where('number', $ticket)
            ->orderByDesc('id')
            ->first();

        if (! $order) {
            return $this->error('No encontramos un ticket con ese folio.');
        }

        $orderTotal = round((float) ($order->total ?? 0), 2);
        $givenTotal = round($totalInput, 2);

        if (abs($orderTotal - $givenTotal) > 0.01) {
            return $this->error('El total capturado no coincide con el ticket.');
        }

        $status = (string) ($order->status ?? '');

        if ($status === 'pending_payment') {
            return $this->error('Este ticket todavía está pendiente de pago.');
        }

        if (in_array($status, ['cancelled', 'canceled', 'cancelled_test'], true)) {
            return $this->error('Este ticket está cancelado y no puede facturarse.');
        }

        if ($status === 'returned') {
            return $this->error('Este ticket tiene devolución y no puede facturarse desde el portal.');
        }

        if ($status !== 'paid') {
            return $this->error('Este ticket no está en estado pagado.');
        }

        $fiscalStatus = PosTicketResource::fiscalStatus($order);
        $fiscalLabel = PosTicketResource::fiscalStatusLabel($fiscalStatus);

        if (! PosTicketResource::canCreateIndividualInvoiceFromTicket($order)) {
            return [
                'ok' => false,
                'type' => 'blocked',
                'title' => 'Ticket no disponible para facturación',
                'message' => 'Estado fiscal: ' . $fiscalLabel . '. Si ya fue facturado, revisa la factura relacionada o solicita apoyo en tienda.',
                'ticket' => $ticket,
                'order_id' => (int) $order->id,
                'order_number' => (string) ($order->number ?? ''),
                'order_total' => $orderTotal,
                'status' => $status,
                'fiscal_status' => $fiscalStatus,
                'fiscal_label' => $fiscalLabel,
            ];
        }

        return [
            'ok' => true,
            'type' => 'eligible',
            'title' => 'Ticket encontrado',
            'message' => 'El ticket es elegible para facturación. En el siguiente paso capturaremos los datos fiscales del receptor.',
            'ticket' => $ticket,
            'order_id' => (int) $order->id,
            'order_number' => (string) ($order->number ?? ''),
            'order_total' => $orderTotal,
            'status' => $status,
            'fiscal_status' => $fiscalStatus,
            'fiscal_label' => $fiscalLabel,
        ];
    }

    protected function error(string $message): array
    {
        return [
            'ok' => false,
            'type' => 'error',
            'title' => 'No se pudo validar el ticket',
            'message' => $message,
        ];
    }
}
