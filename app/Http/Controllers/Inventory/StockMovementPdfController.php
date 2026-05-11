<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockMovementPdfController extends Controller
{
    public function pdf($tenant, $stockMovement)
    {
        abort_unless(auth()->check(), 403);

        $data = $this->movementData((int) $tenant, (int) $stockMovement);
        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) ($data['movement']->reference ?? 'movimiento')) . '.pdf';

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('inventory.stock-movements.pdf', $data)
                ->setPaper('letter', 'portrait')
                ->stream($filename);
        }

        return view('inventory.stock-movements.pdf', $data + [
            'pdfFallback' => true,
        ]);
    }

    protected function movementData(int $tenantId, int $movementId): array
    {
        abort_unless(Schema::hasTable('stock_movements'), 404);

        $movement = DB::table('stock_movements')
            ->where('id', $movementId)
            ->first();

        abort_if(! $movement, 404);

        if ($tenantId > 0 && (int) ($movement->company_id ?? 0) > 0) {
            abort_if($tenantId !== (int) $movement->company_id, 403);
        }

        $lines = Schema::hasTable('stock_movement_lines')
            ? DB::table('stock_movement_lines')
                ->where('stock_movement_id', $movement->id)
                ->orderBy('id')
                ->get()
            : collect();

        $operationType = Schema::hasTable('stock_operation_types') && ! empty($movement->stock_operation_type_id)
            ? DB::table('stock_operation_types')->where('id', $movement->stock_operation_type_id)->first()
            : null;

        $warehouse = Schema::hasTable('warehouses') && ! empty($movement->warehouse_id)
            ? DB::table('warehouses')->where('id', $movement->warehouse_id)->first()
            : null;

        $sourceLocation = Schema::hasTable('stock_locations') && ! empty($movement->source_location_id)
            ? DB::table('stock_locations')->where('id', $movement->source_location_id)->first()
            : null;

        $destinationLocation = Schema::hasTable('stock_locations') && ! empty($movement->destination_location_id)
            ? DB::table('stock_locations')->where('id', $movement->destination_location_id)->first()
            : null;

        $createdBy = Schema::hasTable('users') && ! empty($movement->created_by)
            ? DB::table('users')->where('id', $movement->created_by)->first()
            : null;

        $confirmedBy = Schema::hasTable('users') && ! empty($movement->confirmed_by)
            ? DB::table('users')->where('id', $movement->confirmed_by)->first()
            : null;

        return [
            'tenantId' => $tenantId,
            'movement' => $movement,
            'lines' => $lines,
            'operationType' => $operationType,
            'warehouse' => $warehouse,
            'sourceLocation' => $sourceLocation,
            'destinationLocation' => $destinationLocation,
            'createdBy' => $createdBy,
            'confirmedBy' => $confirmedBy,
            'companyInfo' => $this->companyInfo((int) ($movement->company_id ?? $tenantId)),
            'originLabel' => $this->originLabel($movement),
        ];
    }

    protected function originLabel(object $movement): string
    {
        $origin = trim((string) ($movement->origin_document ?? ''));

        if ($origin === '') {
            return '—';
        }

        if (str_starts_with($origin, 'exit:')) {
            $folio = trim(substr($origin, strlen('exit:')));

            return $folio !== '' ? $folio : 'Salida';
        }

        if (str_starts_with($origin, 'purchase_receipt:')) {
            $receiptNumber = trim(substr($origin, strlen('purchase_receipt:')));

            if (
                $receiptNumber !== ''
                && Schema::hasTable('purchase_receipts')
                && Schema::hasTable('purchase_orders')
            ) {
                $receipt = DB::table('purchase_receipts')
                    ->where('number', $receiptNumber)
                    ->first(['purchase_order_id']);

                if ($receipt && $receipt->purchase_order_id) {
                    $orderNumber = DB::table('purchase_orders')
                        ->where('id', $receipt->purchase_order_id)
                        ->value('number');

                    if ($orderNumber) {
                        return $orderNumber;
                    }
                }
            }

            return $receiptNumber ?: 'Recepción de compra';
        }

        if (str_starts_with($origin, 'purchase_order:')) {
            $value = trim(substr($origin, strlen('purchase_order:')));

            return $value !== '' ? $value : 'Orden de compra';
        }

        if (str_starts_with($origin, 'stock_adjustment:')) {
            $value = trim(substr($origin, strlen('stock_adjustment:')));

            return $value !== '' ? $value : 'Ajuste de inventario';
        }

        if (str_starts_with($origin, 'transfer:')) {
            $value = trim(substr($origin, strlen('transfer:')));

            return $value !== '' ? $value : 'Transferencia';
        }

        return $origin;
    }

    protected function companyInfo(int $companyId): array
    {
        $name = 'BexiaERP';
        $logo = null;
        $rfc = null;

        if ($companyId > 0 && Schema::hasTable('companies')) {
            $company = DB::table('companies')->where('id', $companyId)->first();

            if ($company) {
                foreach (['business_name', 'legal_name', 'name', 'razon_social'] as $column) {
                    if (property_exists($company, $column) && trim((string) $company->{$column}) !== '') {
                        $name = trim((string) $company->{$column});
                        break;
                    }
                }

                foreach (['rfc', 'tax_id'] as $column) {
                    if (property_exists($company, $column) && trim((string) $company->{$column}) !== '') {
                        $rfc = trim((string) $company->{$column});
                        break;
                    }
                }

                foreach (['logo_path', 'logo', 'logo_url', 'image', 'avatar'] as $column) {
                    if (property_exists($company, $column) && trim((string) $company->{$column}) !== '') {
                        $logo = $this->logoDataUri(trim((string) $company->{$column}));
                        break;
                    }
                }
            }
        }

        return [
            'name' => $name,
            'rfc' => $rfc,
            'logo' => $logo,
        ];
    }

    protected function logoDataUri(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'data:image/')) {
            return $value;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $clean = ltrim($value, '/');

        $candidates = [
            public_path($clean),
            public_path('storage/' . preg_replace('#^storage/#', '', $clean)),
            storage_path('app/public/' . preg_replace('#^storage/#', '', $clean)),
            storage_path('app/' . $clean),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                $mime = mime_content_type($path) ?: 'image/png';

                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
            }
        }

        return null;
    }
}
