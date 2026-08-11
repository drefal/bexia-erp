<?php

namespace App\Support\Service;

use App\Models\RepairOrder;
use App\Models\ServiceCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ServiceCaseClassificationService
{
    public function classify(
        ServiceCase $serviceCase,
        array $data
    ): ?RepairOrder {
        if (! ServiceAccess::can('service.cases.classify')) {
            throw new AuthorizationException(
                'No tienes permiso para clasificar tickets.'
            );
        }

        $actorId = auth()->id();

        if (! $actorId) {
            throw new AuthorizationException(
                'Debes iniciar sesión para clasificar el ticket.'
            );
        }

        $route = (string) ($data['attention_route'] ?? '');

        if (! array_key_exists($route, ServiceCase::ATTENTION_ROUTES)) {
            throw ValidationException::withMessages([
                'attention_route' => 'Selecciona una ruta válida.',
            ]);
        }

        return DB::transaction(function () use (
            $serviceCase,
            $data,
            $route,
            $actorId
        ): ?RepairOrder {
            $case = ServiceCase::query()
                ->whereKey($serviceCase->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (filled($case->attention_route)) {
                throw ValidationException::withMessages([
                    'attention_route' =>
                        'Este ticket ya fue clasificado.',
                ]);
            }

            if (in_array(
                (string) $case->status,
                ['entregado', 'cerrado', 'rechazado', 'cancelado'],
                true
            )) {
                throw ValidationException::withMessages([
                    'attention_route' =>
                        'No se puede clasificar un ticket terminado.',
                ]);
            }

            if ($route === 'repair') {
                return $this->classifyAsRepair(
                    $case,
                    $data,
                    (int) $actorId
                );
            }

            $this->classifyAsNonRepair(
                $case,
                $data,
                (int) $actorId
            );

            return null;
        });
    }

    protected function classifyAsRepair(
        ServiceCase $case,
        array $data,
        int $actorId
    ): RepairOrder {
        if (
            RepairOrder::withTrashed()
                ->where('service_case_id', $case->getKey())
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'attention_route' =>
                    'Este ticket ya tiene una reparación vinculada.',
            ]);
        }

        if (
            blank($case->product_id)
            && blank($case->product_name)
        ) {
            throw ValidationException::withMessages([
                'attention_route' =>
                    'El ticket necesita un producto antes de crear la reparación.',
            ]);
        }

        if (
            blank($case->description)
            && blank($case->subject)
        ) {
            throw ValidationException::withMessages([
                'attention_route' =>
                    'El ticket necesita la descripción del problema.',
            ]);
        }

        $technicianId = (int) (
            $data['assigned_employee_id'] ?? 0
        );

        if ($technicianId <= 0) {
            throw ValidationException::withMessages([
                'assigned_employee_id' =>
                    'Selecciona el técnico responsable.',
            ]);
        }

        $technicians = ServiceAccess::technicianEmployeeOptions();

        if (! array_key_exists($technicianId, $technicians)) {
            throw ValidationException::withMessages([
                'assigned_employee_id' =>
                    'El técnico seleccionado no está disponible.',
            ]);
        }

        $diagnosis = trim((string) (
            $data['initial_diagnosis'] ?? ''
        ));

        if ($diagnosis === '') {
            throw ValidationException::withMessages([
                'initial_diagnosis' =>
                    'Captura el diagnóstico preliminar.',
            ]);
        }

        $warrantyStatus = (string) (
            $data['warranty_status'] ?? 'no_aplica'
        );

        if (
            ! array_key_exists(
                $warrantyStatus,
                RepairOrder::WARRANTY_STATUSES
            )
        ) {
            throw ValidationException::withMessages([
                'warranty_status' =>
                    'Selecciona un estado de garantía válido.',
            ]);
        }

        $notes = trim((string) (
            $data['classification_notes'] ?? ''
        ));

        if ($notes === '') {
            throw ValidationException::withMessages([
                'classification_notes' =>
                    'Captura las notas de clasificación.',
            ]);
        }

        $requiresQuote = (bool) (
            $data['requires_quote'] ?? true
        );

        $oldStatus = (string) $case->status;
        $now = now();

        $repair = RepairOrder::create([
            'company_id' => $case->company_id,
            'service_case_id' => $case->id,
            'customer_id' => $case->customer_id,
            'product_id' => $case->product_id,
            'sale_id' => $case->sale_id,
            'invoice_id' => $case->invoice_id,
            'invoice_reference' => $case->invoice_reference,
            'sale_reference' => $case->sale_reference,
            'product_name' => $case->product_name,
            'serial_number' => $case->serial_number,
            'lot_number' => $case->lot_number,
            'status' => 'en_diagnostico',
            'workflow_stage' => 'quote_draft',
            'quote_status' => $requiresQuote
                ? 'draft'
                : 'not_required',
            'requires_customer_approval' => $requiresQuote,
            'requires_internal_approval' => false,
            'warranty_status' => $warrantyStatus,
            'received_at' => $now,
            'promised_at' => $data['promised_at'] ?? null,
            'initial_diagnosis' => $diagnosis,
            'assigned_employee_id' => $technicianId,
            'assigned_at' => $now,
            'assigned_by' => $actorId,
            'created_by' => $actorId,
            'metadata' => [
                'created_from' =>
                    'service_case_classification',
                'classification_notes' => $notes,
            ],
        ]);

        $case->update([
            'attention_route' => 'repair',
            'classified_at' => $now,
            'classified_by' => $actorId,
            'classification_notes' => $notes,
            'non_repair_type' => null,
            'resolution_type' => null,
            'resolution_notes' => null,
            'case_type' => 'reparacion',
            'status' => 'en_diagnostico',
            'assigned_employee_id' => $technicianId,
            'assigned_at' => $now,
            'assigned_by' => $actorId,
            'due_at' => $data['promised_at'] ?? $case->due_at,
        ]);

        if (Schema::hasTable('service_attachments')) {
            DB::table('service_attachments')
                ->where('service_case_id', $case->id)
                ->whereNull('repair_order_id')
                ->update([
                    'repair_order_id' => $repair->id,
                    'updated_at' => $now,
                ]);
        }

        $this->logEvent(
            serviceCaseId: (int) $case->id,
            repairOrderId: (int) $repair->id,
            companyId: (int) $case->company_id,
            eventType: 'ticket_clasificado_reparacion',
            fromStatus: $oldStatus,
            toStatus: 'en_diagnostico',
            actorId: $actorId,
            notes: $notes,
            oldValues: [
                'attention_route' => null,
                'status' => $oldStatus,
            ],
            newValues: [
                'attention_route' => 'repair',
                'status' => 'en_diagnostico',
                'repair_order_id' => $repair->id,
            ]
        );

        $this->logEvent(
            serviceCaseId: (int) $case->id,
            repairOrderId: (int) $repair->id,
            companyId: (int) $case->company_id,
            eventType:
                'reparacion_creada_desde_clasificacion',
            fromStatus: null,
            toStatus: 'en_diagnostico',
            actorId: $actorId,
            notes:
                'Orden creada desde Clasificar atención.',
            oldValues: null,
            newValues: [
                'repair_order_id' => $repair->id,
                'workflow_stage' => 'quote_draft',
            ]
        );

        return $repair;
    }

    protected function classifyAsNonRepair(
        ServiceCase $case,
        array $data,
        int $actorId
    ): void {
        $type = (string) (
            $data['non_repair_type'] ?? ''
        );

        if (
            ! array_key_exists(
                $type,
                ServiceCase::NON_REPAIR_TYPES
            )
        ) {
            throw ValidationException::withMessages([
                'non_repair_type' =>
                    'Selecciona el tipo de atención.',
            ]);
        }

        $notes = trim((string) (
            $data['classification_notes'] ?? ''
        ));

        if ($notes === '') {
            throw ValidationException::withMessages([
                'classification_notes' =>
                    'Captura las notas de clasificación.',
            ]);
        }

        $technicianId = (int) (
            $data['assigned_employee_id'] ?? 0
        );

        if ($technicianId > 0) {
            $technicians =
                ServiceAccess::technicianEmployeeOptions();

            if (! array_key_exists(
                $technicianId,
                $technicians
            )) {
                throw ValidationException::withMessages([
                    'assigned_employee_id' =>
                        'El responsable seleccionado no está disponible.',
                ]);
            }
        }

        $oldStatus = (string) $case->status;
        $newStatus = $technicianId > 0
            ? 'asignado'
            : 'en_revision';

        $now = now();

        $updates = [
            'attention_route' => 'non_repair',
            'classified_at' => $now,
            'classified_by' => $actorId,
            'classification_notes' => $notes,
            'non_repair_type' => $type,
            'resolution_type' => null,
            'resolution_notes' => null,
            'status' => $newStatus,
            'due_at' =>
                $data['non_repair_due_at']
                ?? $case->due_at,
        ];

        if ($technicianId > 0) {
            $updates['assigned_employee_id'] =
                $technicianId;
            $updates['assigned_at'] = $now;
            $updates['assigned_by'] = $actorId;
        }

        $case->update($updates);

        $this->logEvent(
            serviceCaseId: (int) $case->id,
            repairOrderId: null,
            companyId: (int) $case->company_id,
            eventType:
                'ticket_clasificado_sin_reparacion',
            fromStatus: $oldStatus,
            toStatus: $newStatus,
            actorId: $actorId,
            notes: $notes,
            oldValues: [
                'attention_route' => null,
                'status' => $oldStatus,
            ],
            newValues: [
                'attention_route' => 'non_repair',
                'non_repair_type' => $type,
                'status' => $newStatus,
            ]
        );
    }

    protected function logEvent(
        int $serviceCaseId,
        ?int $repairOrderId,
        int $companyId,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        int $actorId,
        string $notes,
        ?array $oldValues,
        ?array $newValues
    ): void {
        if (! Schema::hasTable('service_case_events')) {
            return;
        }

        $now = now();

        DB::table('service_case_events')->insert([
            'company_id' => $companyId,
            'service_case_id' => $serviceCaseId,
            'repair_order_id' => $repairOrderId,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'performed_by' => $actorId,
            'performed_at' => $now,
            'notes' => $notes,
            'old_values' => $oldValues
                ? json_encode(
                    $oldValues,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                )
                : null,
            'new_values' => $newValues
                ? json_encode(
                    $newValues,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                )
                : null,
            'metadata' => json_encode([
                'source' =>
                    'service_case_classification',
            ], JSON_UNESCAPED_UNICODE),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
