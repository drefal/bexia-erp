<?php

namespace App\Http\Controllers;

use App\Models\PayrollCfdiReceipt;
use App\Support\PayrollCfdi\PayrollCfdiReceiptPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PayrollCfdiReceiptPdfController extends Controller
{
    public function show(Request $request, int $receipt, PayrollCfdiReceiptPdfService $service)
    {
        $user = $request->user();

        abort_unless($user, 403);

        $record = PayrollCfdiReceipt::query()->findOrFail($receipt);

        $allowed = (bool) ($user->is_system_admin ?? false)
            || (($user->email ?? null) === 'admin@bexiaerp.com')
            || $user->can('nomina.recibos_cfdi.ver')
            || $user->can('nomina.procesos.ver')
            || $user->can('company.update');

        abort_unless($allowed, 403);

        $fresh = (bool) $request->boolean('fresh');

        if ($fresh || ! filled($record->pdf_path) || ! Storage::disk('local')->exists($record->pdf_path)) {
            $result = $service->generate(
                companyId: (int) $record->company_id,
                receiptId: (int) $record->id,
                userId: $user->id ?? null,
                force: $fresh,
            );

            abort_unless((bool) ($result['success'] ?? false), 500, $result['message'] ?? 'No se pudo generar PDF.');

            $record->refresh();
        }

        abort_unless(filled($record->pdf_path) && Storage::disk('local')->exists($record->pdf_path), 404);

        $filename = 'recibo_nomina_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) ($record->folio ?: $record->id)) . '.pdf';
        $path = Storage::disk('local')->path($record->pdf_path);

        if ($request->boolean('download')) {
            return response()->download($path, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
