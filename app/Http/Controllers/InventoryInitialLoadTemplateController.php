<?php

namespace App\Http\Controllers;

use App\Support\InventoryInitialLoadTemplate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryInitialLoadTemplateController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $this->authorizeDownload();

        $warehouseId = (int) $request->query('warehouse_id');
        $locationId = (int) $request->query('location_id');
        $companyId = $request->query('company_id') !== null && $request->query('company_id') !== ''
            ? (int) $request->query('company_id')
            : $this->currentCompanyId();

        if (! $warehouseId || ! $locationId) {
            abort(422, 'Falta almacén o ubicación.');
        }

        $this->validateWarehouseAndLocation($companyId, $warehouseId, $locationId);

        return InventoryInitialLoadTemplate::download($companyId, $warehouseId, $locationId);
    }

    protected function authorizeDownload(): void
    {
        /*
         * El permiso separado se valida en el botón de Filament:
         * inventory.download_inventory_template
         *
         * La ruta queda protegida con URL firmada temporal.
         * Así el usuario debe venir desde el botón autorizado y no puede
         * abrir una URL manual sin firma válida.
         */

        $user = auth()->user();

        if (! $user && class_exists(\Filament\Facades\Filament::class)) {
            try {
                $user = \Filament\Facades\Filament::auth()->user();
            } catch (\Throwable $exception) {
                $user = null;
            }
        }

        if (! $user) {
            abort(403, 'No autenticado.');
        }

        if (! request()->hasValidSignature()) {
            abort(403, 'La liga de descarga no es válida o expiró.');
        }
    }



    protected function currentCompanyId(): ?int
    {
        if (class_exists(\Filament\Facades\Filament::class)) {
            try {
                $tenant = \Filament\Facades\Filament::getTenant();

                if ($tenant && method_exists($tenant, 'getKey')) {
                    return (int) $tenant->getKey();
                }
            } catch (\Throwable $exception) {
                // Continuar con usuario.
            }
        }

        $user = auth()->user();

        if ($user && isset($user->company_id)) {
            return (int) $user->company_id;
        }

        return null;
    }

    protected function validateWarehouseAndLocation(?int $companyId, int $warehouseId, int $locationId): void
    {
        if (! Schema::hasTable('warehouses') || ! Schema::hasTable('stock_locations')) {
            abort(422, 'No existen tablas de almacenes o ubicaciones.');
        }

        $warehouseQuery = DB::table('warehouses')->where('id', $warehouseId);

        if ($companyId && Schema::hasColumn('warehouses', 'company_id')) {
            $warehouseQuery->where('company_id', $companyId);
        }

        if (! $warehouseQuery->exists()) {
            abort(403, 'El almacén no pertenece a la empresa actual.');
        }

        $locationQuery = DB::table('stock_locations')
            ->where('id', $locationId)
            ->where('warehouse_id', $warehouseId);

        if ($companyId && Schema::hasColumn('stock_locations', 'company_id')) {
            $locationQuery->where(function ($query) use ($companyId): void {
                $query
                    ->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            });
        }

        if (! $locationQuery->exists()) {
            abort(403, 'La ubicación no pertenece al almacén seleccionado.');
        }
    }
}
