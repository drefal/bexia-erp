<?php

namespace App\Filament\Resources\StockSerialNumberResource\Pages;

use App\Filament\Resources\StockSerialNumberResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStockSerialNumbers extends ListRecords
{
    protected static string $resource = StockSerialNumberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('serialReconciliation')
                ->label('Conciliación de series')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('gray')
                ->modalHeading('Conciliación de series vs existencias')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalWidth('7xl')
                ->modalContent(fn () => view('filament.inventory.stock-serial-numbers.reconciliation', [
                    'summary' => $this->serialReconciliationSummary(),
                ])),

            Actions\Action::make('specialMovementsHistory')
                ->label('Historial especial')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->modalHeading('Historial especial de series')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalWidth('7xl')
                ->modalContent(fn () => view('filament.inventory.stock-serial-numbers.special-movements-history', [
                    'rows' => $this->specialMovementsHistoryRows(),
                    'typeLabels' => \App\Models\StockSerialSpecialMovement::typeLabels(),
                ])),

            Actions\CreateAction::make()
                ->label('Nuevo número de serie'),
        ];
    }
    protected function serialReconciliationSummary(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('stock_serial_numbers')) {
            return [
                'totals' => [],
                'rows' => [],
                'status_counts' => [],
                'warnings' => ['No existe la tabla stock_serial_numbers.'],
            ];
        }

        $companyId = $this->currentCompanyId();

        $statusCounts = \Illuminate\Support\Facades\DB::table('stock_serial_numbers')
            ->select('status', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'status' => (string) ($row->status ?? ''),
                'total' => (int) $row->total,
            ])
            ->all();

        $warnings = [];

        $availableWithoutLocation = \Illuminate\Support\Facades\DB::table('stock_serial_numbers')
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->where('status', 'available')
            ->where(function ($query): void {
                $query->whereNull('current_warehouse_id')
                    ->orWhereNull('current_location_id');
            })
            ->count();

        if ($availableWithoutLocation > 0) {
            $warnings[] = "Hay {$availableWithoutLocation} series disponibles sin almacén o ubicación.";
        }

        $soldWithLocation = \Illuminate\Support\Facades\DB::table('stock_serial_numbers')
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->where('status', 'sold')
            ->where(function ($query): void {
                $query->whereNotNull('current_warehouse_id')
                    ->orWhereNotNull('current_location_id');
            })
            ->count();

        if ($soldWithLocation > 0) {
            $warnings[] = "Hay {$soldWithLocation} series vendidas que todavía conservan almacén/ubicación. No se deben recontar como disponibles.";
        }

        $duplicateCount = \Illuminate\Support\Facades\DB::table('stock_serial_numbers')
            ->select('serial_number', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->groupBy('serial_number')
            ->havingRaw('count(*) > 1')
            ->count();

        if ($duplicateCount > 0) {
            $warnings[] = "Hay {$duplicateCount} números de serie duplicados por revisar.";
        }

        $rows = [];

        if (
            \Illuminate\Support\Facades\Schema::hasTable('stock_quants')
            && \Illuminate\Support\Facades\Schema::hasColumn('stock_serial_numbers', 'current_warehouse_id')
            && \Illuminate\Support\Facades\Schema::hasColumn('stock_serial_numbers', 'current_location_id')
        ) {
            $bindings = [];
            $companyWhere = '';

            if ($companyId) {
                $companyWhere = 'and ssn.company_id = ?';
                $bindings[] = $companyId;
            }

            $sql = "
                select
                    ssn.company_id,
                    ssn.product_id,
                    ssn.product_variant_id,
                    ssn.lot_id,
                    ssn.current_warehouse_id as warehouse_id,
                    ssn.current_location_id as location_id,
                    count(*) as available_serials,
                    coalesce(max(sq.quantity), 0) as quant_quantity,
                    coalesce(max(sq.reserved_quantity), 0) as reserved_quantity,
                    max(p.name) as product_name,
                    max(p.sku) as product_sku,
                    max(p.internal_reference) as product_reference,
                    max(v.name) as variant_name,
                    max(v.sku) as variant_sku,
                    max(v.internal_reference) as variant_reference,
                    max(l.lot_number) as lot_number,
                    max(w.name) as warehouse_name,
                    max(sl.name) as location_name
                from stock_serial_numbers ssn
                left join stock_quants sq
                  on sq.company_id = ssn.company_id
                 and sq.product_id = ssn.product_id
                 and coalesce(sq.product_variant_id, 0) = coalesce(ssn.product_variant_id, 0)
                 and coalesce(sq.lot_id, 0) = coalesce(ssn.lot_id, 0)
                 and sq.warehouse_id = ssn.current_warehouse_id
                 and sq.location_id = ssn.current_location_id
                left join products p on p.id = ssn.product_id
                left join products v on v.id = ssn.product_variant_id
                left join stock_lots l on l.id = ssn.lot_id
                left join warehouses w on w.id = ssn.current_warehouse_id
                left join stock_locations sl on sl.id = ssn.current_location_id
                where ssn.status = 'available'
                  {$companyWhere}
                  and ssn.current_warehouse_id is not null
                  and ssn.current_location_id is not null
                group by
                    ssn.company_id,
                    ssn.product_id,
                    ssn.product_variant_id,
                    ssn.lot_id,
                    ssn.current_warehouse_id,
                    ssn.current_location_id
                order by
                    max(p.name),
                    max(v.name),
                    max(l.lot_number),
                    max(w.name),
                    max(sl.name)
                limit 200
            ";

            $rows = collect(\Illuminate\Support\Facades\DB::select($sql, $bindings))
                ->map(function ($row): array {
                    $availableSerials = (int) ($row->available_serials ?? 0);
                    $quantQuantity = (float) ($row->quant_quantity ?? 0);
                    $reservedQuantity = (float) ($row->reserved_quantity ?? 0);
                    $difference = $availableSerials - $quantQuantity;

                    return [
                        'product_label' => $this->entityLabel($row->product_reference ?? $row->product_sku ?? null, $row->product_name ?? null, 'Producto #' . (int) ($row->product_id ?? 0)),
                        'variant_label' => ! empty($row->product_variant_id)
                            ? $this->entityLabel($row->variant_reference ?? $row->variant_sku ?? null, $row->variant_name ?? null, 'Variante #' . (int) $row->product_variant_id)
                            : '—',
                        'lot_label' => ! empty($row->lot_id)
                            ? ((string) ($row->lot_number ?? ('Lote #' . (int) $row->lot_id)))
                            : '—',
                        'warehouse_label' => (string) ($row->warehouse_name ?? ('Almacén #' . (int) ($row->warehouse_id ?? 0))),
                        'location_label' => (string) ($row->location_name ?? ('Ubicación #' . (int) ($row->location_id ?? 0))),
                        'available_serials' => $availableSerials,
                        'quant_quantity' => $quantQuantity,
                        'reserved_quantity' => $reservedQuantity,
                        'difference' => $difference,
                        'status' => abs($difference) < 0.000001 ? 'ok' : 'mismatch',
                    ];
                })
                ->all();
        }

        $totals = [
            'groups' => count($rows),
            'mismatches' => collect($rows)->where('status', 'mismatch')->count(),
            'available_serials' => collect($rows)->sum('available_serials'),
            'quant_quantity' => collect($rows)->sum('quant_quantity'),
        ];

        return [
            'totals' => $totals,
            'rows' => $rows,
            'status_counts' => $statusCounts,
            'warnings' => $warnings,
        ];
    }

    protected function currentCompanyId(): ?int
    {
        $tenant = \Filament\Facades\Filament::getTenant();

        if (is_object($tenant) && method_exists($tenant, 'getKey')) {
            return (int) $tenant->getKey();
        }

        if (is_numeric($tenant)) {
            return (int) $tenant;
        }

        return null;
    }

    protected function entityLabel(?string $code, ?string $name, string $fallback): string
    {
        $code = trim((string) $code);
        $name = trim((string) $name);

        if ($code !== '' && $name !== '') {
            return $code . ' - ' . $name;
        }

        if ($name !== '') {
            return $name;
        }

        if ($code !== '') {
            return $code;
        }

        return $fallback;
    }

    protected function specialMovementsHistoryRows(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('stock_serial_special_movements')) {
            return [];
        }

        $companyId = $this->currentCompanyId();
        $typeLabels = \App\Models\StockSerialSpecialMovement::typeLabels();

        $rows = \Illuminate\Support\Facades\DB::table('stock_serial_special_movements as m')
            ->leftJoin('products as p', 'p.id', '=', 'm.product_id')
            ->leftJoin('products as v', 'v.id', '=', 'm.product_variant_id')
            ->leftJoin('warehouses as sw', 'sw.id', '=', 'm.source_warehouse_id')
            ->leftJoin('stock_locations as sl', 'sl.id', '=', 'm.source_location_id')
            ->leftJoin('warehouses as dw', 'dw.id', '=', 'm.destination_warehouse_id')
            ->leftJoin('stock_locations as dl', 'dl.id', '=', 'm.destination_location_id')
            ->leftJoin('users as u', 'u.id', '=', 'm.created_by')
            ->select([
                'm.id',
                'm.movement_type',
                'm.serial_number_before',
                'm.serial_number_after',
                'm.reason',
                'm.created_at',
                'p.name as product_name',
                'p.sku as product_sku',
                'p.internal_reference as product_reference',
                'v.name as variant_name',
                'v.sku as variant_sku',
                'v.internal_reference as variant_reference',
                'sw.name as source_warehouse_name',
                'sl.name as source_location_name',
                'dw.name as destination_warehouse_name',
                'dl.name as destination_location_name',
                'u.name as user_name',
                'u.email as user_email',
            ])
            ->when($companyId, fn ($query) => $query->where('m.company_id', $companyId))
            ->orderByDesc('m.id')
            ->limit(50)
            ->get();

        return $rows->map(function ($row) use ($typeLabels): array {
            $source = trim(implode(' / ', array_filter([
                $row->source_warehouse_name ?? null,
                $row->source_location_name ?? null,
            ])));

            $destination = trim(implode(' / ', array_filter([
                $row->destination_warehouse_name ?? null,
                $row->destination_location_name ?? null,
            ])));

            $user = trim((string) ($row->user_name ?? ''));

            if ($user === '') {
                $user = trim((string) ($row->user_email ?? ''));
            }

            return [
                'created_at' => $row->created_at ? \Illuminate\Support\Carbon::parse($row->created_at)->format('d/m/Y H:i') : '—',
                'movement_type' => (string) ($row->movement_type ?? ''),
                'movement_type_label' => $typeLabels[$row->movement_type] ?? ($row->movement_type ?: '—'),
                'serial_number_before' => $row->serial_number_before ?: '—',
                'serial_number_after' => $row->serial_number_after ?: '—',
                'product_label' => $this->entityLabel($row->product_reference ?? $row->product_sku ?? null, $row->product_name ?? null, '—'),
                'variant_label' => $this->entityLabel($row->variant_reference ?? $row->variant_sku ?? null, $row->variant_name ?? null, '—'),
                'source_label' => $source !== '' ? $source : '—',
                'destination_label' => $destination !== '' ? $destination : '—',
                'reason' => $row->reason ?: '—',
                'created_by_label' => $user !== '' ? $user : '—',
            ];
        })->all();
    }

}
