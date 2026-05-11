<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Support\PurchaseOrderXmlImporter;
use Illuminate\Http\Request;

class ImportPurchaseOrderXmlController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(auth()->check(), 403);

        $data = $request->validate([
            'company_id' => ['nullable', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'xml_file' => ['required', 'file', 'mimes:xml,txt', 'max:10240'],
        ]);

        $companyId = (int) ($data['company_id'] ?? request()->route('tenant') ?? auth()->user()?->company_id ?? 0);

        if ($companyId <= 0) {
            return redirect()->back()->with('error', 'No se pudo determinar la empresa actual.');
        }

        $path = $request->file('xml_file')->store('purchase-order-xml', 'public');
        $absolutePath = storage_path('app/public/' . $path);

        try {
            $orderId = app(PurchaseOrderXmlImporter::class)->import(
                $absolutePath,
                $companyId,
                ! empty($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
                ! empty($data['location_id']) ? (int) $data['location_id'] : null,
            );

            return redirect('/admin/' . $companyId . '/purchase-orders/' . $orderId . '/edit')
                ->with('success', 'OC creada desde XML. Revisa y mapea productos pendientes antes de confirmar.');
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }
}
