<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RepairOrderPrintController extends Controller
{
    public function show(int|string $tenant, int|string $record, string $type)
    {
        $type = strtolower(trim($type));

        $types = [
            'reception' => [
                'title' => 'Acuse de recepción / Orden de servicio',
                'subtitle' => 'Documento para cliente y archivo de recepción',
                'customer_copy' => true,
            ],
            'quote' => [
                'title' => 'Presupuesto de reparación',
                'subtitle' => 'Documento para autorización del cliente',
                'customer_copy' => true,
            ],
            'internal' => [
                'title' => 'Orden interna de reparación',
                'subtitle' => 'Documento interno para técnico / archivo',
                'customer_copy' => false,
            ],
            'solution' => [
                'title' => 'Constancia de reparación / solución',
                'subtitle' => 'Resumen del trabajo realizado',
                'customer_copy' => true,
            ],
            'delivery' => [
                'title' => 'Comprobante de entrega',
                'subtitle' => 'Documento para entrega y recibido del cliente',
                'customer_copy' => true,
            ],
        ];

        abort_unless(isset($types[$type]), 404);

        abort_unless(Schema::hasTable('repair_orders'), 404);

        $repair = DB::table('repair_orders')->where('id', $record)->first();

        abort_unless($repair, 404);

        if (
            property_exists($repair, 'company_id')
            && $repair->company_id !== null
            && (string) $repair->company_id !== (string) $tenant
        ) {
            abort(403);
        }

        $company = $this->recordById('companies', $this->pick($repair, ['company_id']));
        $case = $this->recordById('service_cases', $this->pick($repair, ['service_case_id']));

        $customerId = $this->pick($repair, ['customer_id'], $this->pick($case, ['customer_id']));
        $customer = $this->firstExistingRecord(['customers', 'clients', 'contacts'], $customerId);

        $productId = $this->pick($repair, ['product_id'], $this->pick($case, ['product_id']));
        $product = $this->firstExistingRecord(['products', 'items'], $productId);

        $employeeId = $this->pick($repair, ['assigned_employee_id', 'technician_employee_id', 'employee_id']);
        $technician = $this->firstExistingRecord(['employees', 'users'], $employeeId);

        $parts = $this->repairParts((int) $record);
        $attachments = $this->attachments((int) $record, $type);

        $rows = $this->rowsForType($type, $repair, $case, $customer, $product, $technician);

        $totals = [
            'refacciones_materiales' => $this->formatMoney($this->partsTotal($parts)),
            'mano_obra_estimada' => $this->formatMoney((float) $this->pick($repair, ['labor_total', 'labor_amount', 'estimated_labor_cost', 'quote_labor_total'], 0)),
            'presupuesto_total' => $this->formatMoney((float) $this->pick($repair, ['quote_total', 'budget_total', 'total'], 0)),
            'horas_estimadas' => $this->formatNumber($this->pick($repair, ['labor_hours_estimate', 'estimated_labor_hours'])),
            'horas_reales' => $this->formatNumber($this->pick($repair, ['actual_labor_hours'])),
            'costo_real_mano_obra' => $this->formatMoney((float) $this->pick($repair, ['actual_labor_cost'], 0)),
        ];

        $logoUrl = $this->logoUrl($company);

        return view('service.repair-orders.print', [
            'type' => $type,
            'document' => $types[$type],
            'tenant' => $tenant,
            'repair' => $repair,
            'case' => $case,
            'company' => $company,
            'customer' => $customer,
            'product' => $product,
            'technician' => $technician,
            'rows' => $rows,
            'parts' => $parts,
            'attachments' => $attachments,
            'totals' => $totals,
            'logoUrl' => $logoUrl,
            'printedAt' => now(),
        ]);
    }

    protected function rowsForType(string $type, object $repair, ?object $case, ?object $customer, ?object $product, ?object $technician): array
    {
        $shared = [
            'Folio' => $this->pick($repair, ['folio', 'number', 'code'], 'Sin folio'),
            'Cliente' => $this->displayName($customer, $this->pick($repair, ['customer_name'], '')),
            'Producto / equipo' => $this->displayName($product, $this->pick($repair, ['product_name', 'item_name', 'equipment_name'], '')),
            'Serie' => $this->pick($repair, ['serial_number', 'serial', 'serie'], ''),
            'Lote' => $this->pick($repair, ['lot_number', 'lot', 'lote'], ''),
            'Estado operativo' => $this->humanStage($this->pick($repair, ['workflow_stage', 'status'], '')),
        ];

        $reportedProblem = $this->pick($repair, [
            'reported_problem',
            'problem_description',
            'failure_description',
            'issue_description',
            'customer_report',
            'description',
        ], $this->pick($case, ['reported_problem', 'description', 'notes'], ''));

        $diagnosis = $this->pick($repair, [
            'diagnosis',
            'technical_diagnosis',
            'diagnostic_notes',
            'technician_notes',
        ], '');

        $resolution = $this->pick($repair, [
            'resolution',
            'repair_resolution',
            'final_resolution',
            'solution',
            'solution_notes',
        ], '');

        return match ($type) {
            'reception' => array_merge($shared, [
                'Fecha recepción' => $this->formatDate($this->pick($repair, ['received_at', 'created_at'])),
                'Problema reportado' => $reportedProblem,
                'Condición de recepción' => $this->pick($repair, ['received_condition', 'condition_notes', 'initial_condition'], ''),
                'Garantía' => $this->pick($repair, ['warranty_status', 'warranty_notes'], ''),
                'Observaciones' => $this->pick($repair, ['notes', 'internal_notes'], ''),
            ]),

            'quote' => array_merge($shared, [
                'Fecha presupuesto' => $this->formatDate($this->pick($repair, ['quote_submitted_at', 'updated_at', 'created_at'])),
                'Diagnóstico' => $diagnosis,
                'Horas estimadas' => $this->formatNumber($this->pick($repair, ['labor_hours_estimate', 'estimated_labor_hours'])),
                'Tarifa por hora' => $this->formatMoney((float) $this->pick($repair, ['labor_hour_rate'], 0)),
                'Total presupuesto' => $this->formatMoney((float) $this->pick($repair, ['quote_total', 'budget_total', 'total'], 0)),
                'Notas presupuesto' => $this->pick($repair, ['quote_notes', 'budget_notes', 'notes'], ''),
            ]),

            'internal' => array_merge($shared, [
                'Técnico asignado' => $this->displayName($technician, ''),
                'Fecha inicio reparación' => $this->formatDate($this->pick($repair, ['repair_started_at', 'started_at'])),
                'Fecha fin reparación' => $this->formatDate($this->pick($repair, ['repair_finished_at', 'finished_at'])),
                'Problema reportado' => $reportedProblem,
                'Diagnóstico' => $diagnosis,
                'Resolución' => $resolution,
                'Horas reales' => $this->formatNumber($this->pick($repair, ['actual_labor_hours'])),
                'Costo real mano de obra' => $this->formatMoney((float) $this->pick($repair, ['actual_labor_cost'], 0)),
                'Notas internas' => $this->pick($repair, ['internal_notes', 'technician_notes', 'notes'], ''),
            ]),

            'solution' => array_merge($shared, [
                'Fecha inicio reparación' => $this->formatDate($this->pick($repair, ['repair_started_at', 'started_at'])),
                'Fecha reparación finalizada' => $this->formatDate($this->pick($repair, ['repair_finished_at', 'finished_at'])),
                'Diagnóstico' => $diagnosis,
                'Solución / trabajo realizado' => $resolution,
                'Técnico' => $this->displayName($technician, ''),
            ]),

            'delivery' => array_merge($shared, [
                'Fecha entrega' => $this->formatDate($this->pick($repair, ['delivered_at', 'delivery_at'])),
                'Solución / trabajo realizado' => $resolution,
                'Recibe' => $this->pick($repair, ['delivered_to', 'received_by', 'customer_receiver_name'], ''),
                'Observaciones entrega' => $this->pick($repair, ['delivery_notes', 'delivery_observations'], ''),
            ]),

            default => $shared,
        };
    }

    protected function repairParts(int $repairId): array
    {
        if (! Schema::hasTable('repair_order_parts')) {
            return [];
        }

        return DB::table('repair_order_parts')
            ->where('repair_order_id', $repairId)
            ->orderBy('id')
            ->get()
            ->map(function ($row): array {
                $quantity = (float) $this->pick($row, ['quantity', 'qty'], 0);
                $unitPrice = (float) $this->pick($row, ['unit_price', 'price', 'sale_price'], 0);
                $lineTotal = (float) $this->pick($row, ['line_total', 'total', 'amount'], $quantity * $unitPrice);

                return [
                    'concept' => $this->pick($row, ['description', 'part_name', 'product_name', 'name', 'notes'], 'Refacción / material'),
                    'quantity' => $this->formatNumber($quantity),
                    'unit_price' => $this->formatMoney($unitPrice),
                    'total' => $this->formatMoney($lineTotal),
                    'total_raw' => $lineTotal,
                ];
            })
            ->all();
    }

    protected function partsTotal(array $parts): float
    {
        return array_reduce($parts, fn (float $carry, array $part): float => $carry + (float) ($part['total_raw'] ?? 0), 0.0);
    }

    protected function attachments(int $repairId, string $type): array
    {
        if (! Schema::hasTable('service_attachments')) {
            return [];
        }

        $query = DB::table('service_attachments')->where('repair_order_id', $repairId);

        if ($type === 'solution') {
            $query->where(function ($q): void {
                $q->where('stage', 'solution')
                    ->orWhere('file_path', 'like', 'service/solution-files/%')
                    ->orWhere('file_path', 'like', 'service/solution-photos/%');
            });
        }

        return $query
            ->orderBy('id')
            ->get()
            ->map(function ($row): array {
                $path = (string) $this->pick($row, ['file_path', 'path'], '');
                $name = $this->pick($row, ['file_name', 'filename', 'original_name', 'name'], basename($path));

                return [
                    'name' => $name,
                    'path' => $path,
                    'url' => $this->attachmentUrl($path),
                    'stage' => $this->pick($row, ['stage'], ''),
                    'mime_type' => $this->pick($row, ['mime_type'], ''),
                    'notes' => $this->pick($row, ['notes'], ''),
                ];
            })
            ->all();
    }

    protected function attachmentUrl(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '#';
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (Str::startsWith($path, ['storage/'])) {
            return asset($path);
        }

        return asset('storage/' . ltrim($path, '/'));
    }

    protected function recordById(string $table, mixed $id): ?object
    {
        if (! $id || ! Schema::hasTable($table)) {
            return null;
        }

        return DB::table($table)->where('id', $id)->first();
    }

    protected function firstExistingRecord(array $tables, mixed $id): ?object
    {
        foreach ($tables as $table) {
            $record = $this->recordById($table, $id);

            if ($record) {
                return $record;
            }
        }

        return null;
    }

    protected function pick(?object $record, array $fields, mixed $default = null): mixed
    {
        if (! $record) {
            return $default;
        }

        foreach ($fields as $field) {
            if (property_exists($record, $field) && $record->{$field} !== null && $record->{$field} !== '') {
                return $record->{$field};
            }
        }

        return $default;
    }

    protected function displayName(?object $record, string $default = ''): string
    {
        if (! $record) {
            return $default;
        }

        foreach ([
            'name',
            'full_name',
            'business_name',
            'legal_name',
            'company_name',
            'display_name',
            'description',
            'email',
        ] as $field) {
            if (property_exists($record, $field) && filled($record->{$field})) {
                return (string) $record->{$field};
            }
        }

        return $default;
    }

    protected function formatDate(mixed $value): string
    {
        if (! $value) {
            return '';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    protected function formatMoney(float $value): string
    {
        return '$' . number_format($value, 2);
    }

    protected function formatNumber(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, 2);
    }

    protected function humanStage(string $stage): string
    {
        return [
            'quote_draft' => 'Cotización en borrador',
            'pending_approval' => 'Pendiente de aprobación',
            'quote_approved' => 'Aprobada / pendiente reparación',
            'in_repair' => 'En reparación',
            'repaired' => 'Reparado',
            'supervisor_review' => 'Revisión supervisor',
            'ready_for_delivery' => 'Listo para entrega',
            'delivered' => 'Entregado',
            'cancelled' => 'Cancelado',
        ][$stage] ?? $stage;
    }

    protected function logoUrl(?object $company): ?string
    {
        $logo = $this->pick($company, ['logo_path', 'logo', 'logo_url', 'image_path']);

        if (! $logo) {
            return null;
        }

        $logo = (string) $logo;

        if (Str::startsWith($logo, ['http://', 'https://'])) {
            return $logo;
        }

        if (Str::startsWith($logo, ['storage/'])) {
            return asset($logo);
        }

        return asset('storage/' . ltrim($logo, '/'));
    }
}
