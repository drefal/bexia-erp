<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PublicRepairTrackingController extends Controller
{
    public function __invoke(string $token)
    {
        $token = trim($token);

        abort_unless($token !== '' && strlen($token) >= 24, 404);
        abort_unless(Schema::hasTable('repair_orders'), 404);
        abort_unless(Schema::hasColumn('repair_orders', 'public_tracking_token'), 404);

        $query = DB::table('repair_orders')
            ->where('public_tracking_token', $token);

        if (Schema::hasColumn('repair_orders', 'public_tracking_enabled')) {
            $query->where('public_tracking_enabled', true);
        }

        $repair = $query->first();

        abort_unless($repair, 404);

        $company = $this->recordById('companies', $repair->company_id ?? null);
        $case = $this->recordById('service_cases', $repair->service_case_id ?? null);
        $customer = $this->firstExistingRecord(['customers', 'clients', 'contacts'], $repair->customer_id ?? ($case->customer_id ?? null));
        $product = $this->firstExistingRecord(['products', 'items'], $repair->product_id ?? ($case->product_id ?? null));

        $stage = (string) (($repair->workflow_stage ?? null) ?: ($repair->status ?? ''));
        $customerStatus = $this->customerStatus($stage, $repair);

        $timeline = [
            [
                'label' => 'Recepción',
                'date' => $this->formatDate($repair->received_at ?? $repair->created_at ?? null),
                'done' => filled($repair->received_at ?? $repair->created_at ?? null),
            ],
            [
                'label' => 'Inicio de reparación',
                'date' => $this->formatDate($repair->repair_started_at ?? $repair->started_at ?? null),
                'done' => filled($repair->repair_started_at ?? $repair->started_at ?? null),
            ],
            [
                'label' => 'Reparación terminada',
                'date' => $this->formatDate($repair->repair_finished_at ?? $repair->finished_at ?? null),
                'done' => filled($repair->repair_finished_at ?? $repair->finished_at ?? null),
            ],
            [
                'label' => 'Lista para entrega',
                'date' => $this->formatDate($repair->ready_for_delivery_at ?? null),
                'done' => filled($repair->ready_for_delivery_at ?? null),
            ],
            [
                'label' => 'Entregada',
                'date' => $this->formatDate($repair->delivered_at ?? null),
                'done' => filled($repair->delivered_at ?? null),
            ],
        ];

        $rows = [
            'Folio' => (string) ($repair->folio ?? 'Servicio'),
            'Cliente' => $this->displayName($customer, 'Cliente'),
            'Producto / equipo' => $this->displayName($product, (string) ($repair->product_name ?? $case->product_name ?? '')),
            'Serie' => (string) ($repair->serial_number ?? $case->serial_number ?? ''),
            'Lote' => (string) ($repair->lot_number ?? $case->lot_number ?? ''),
            'Fecha de recepción' => $this->formatDate($repair->received_at ?? $repair->created_at ?? null),
            'Fecha prometida' => $this->formatDate($repair->promised_at ?? null),
        ];

        $publicNotes = [
            'reported_problem' => (string) (($repair->initial_diagnosis ?? null) ?: ($case->description ?? '')),
            'received_condition' => (string) ($repair->received_condition ?? ''),
            'resolution' => in_array($stage, ['repaired', 'ready_for_delivery', 'delivered'], true)
                ? (string) ($repair->resolution ?? '')
                : '',
        ];

        $companyLogoUrl = $this->logoUrl($company);

        return view('service.public-tracking.show', [
            'repair' => $repair,
            'company' => $company,
            'case' => $case,
            'customer' => $customer,
            'product' => $product,
            'rows' => $rows,
            'customerStatus' => $customerStatus,
            'timeline' => $timeline,
            'stageDetails' => $this->stageDetails($repair, $case, $customer, $product),
            'publicNotes' => $publicNotes,
            'companyLogoUrl' => $companyLogoUrl,
            'updatedAt' => $this->formatDate($repair->updated_at ?? null),
        ]);
    }

    protected function stageDetails(object $repair, ?object $case, ?object $customer, ?object $product): array
    {
        $repairId = (int) ($repair->id ?? 0);
        $stage = (string) (($repair->workflow_stage ?? null) ?: ($repair->status ?? ''));

        $receivedBy = $this->userName($repair->created_by ?? null);
        $assignedBy = $this->userName($repair->assigned_by ?? null);
        $technician = $this->employeeName($repair->assigned_employee_id ?? null) ?: $this->userName($repair->assigned_user_id ?? null);
        $customerName = $this->displayName($customer, 'Cliente');
        $productName = $this->displayName($product, (string) ($repair->product_name ?? $case->product_name ?? ''));

        return [
            [
                'title' => 'Recepción',
                'status' => filled($repair->received_at ?? $repair->created_at ?? null) ? 'Realizada' : 'Pendiente',
                'items' => $this->cleanItems([
                    'Fecha de recepción' => $this->formatDate($repair->received_at ?? $repair->created_at ?? null),
                    'Recepción registrada por' => filled($repair->received_at ?? $repair->created_at ?? null) ? 'Área de recepción' : '',
                    'Persona que entregó' => $this->signaturePersonFromNotes($repairId, 'reception_signature') ?: $customerName,
                    'Cliente' => $customerName,
                    'Producto / equipo' => $productName,
                    'Serie' => (string) ($repair->serial_number ?? $case->serial_number ?? ''),
                    'Condición de recepción' => (string) ($repair->received_condition ?? ''),
                    'Problema reportado' => (string) (($repair->initial_diagnosis ?? null) ?: ($case->description ?? '')),
                    'Firma recepción' => $this->hasAttachmentStage($repairId, 'reception_signature') ? 'Sí' : 'Pendiente',
                    'Evidencia recepción' => $this->attachmentSummary($repairId, ['reception']),
                ]),
                'events' => $this->eventRows($repairId, [
                    'repair_created',
                    'reception_files_uploaded',
                    'reception_signature_captured',
                ]),
            ],
            [
                'title' => 'Presupuesto / autorización',
                'status' => $this->humanQuoteStatus((string) ($repair->quote_status ?? '')),
                'items' => $this->cleanItems([
                    'Estado presupuesto' => $this->humanQuoteStatus((string) ($repair->quote_status ?? '')),
                    'Fecha presupuesto' => $this->formatDate($repair->quote_submitted_at ?? null),
                    'Fecha autorizado' => $this->formatDate($repair->quote_approved_at ?? $repair->customer_approved_at ?? null),
                    'Requiere autorización del cliente' => isset($repair->requires_customer_approval) ? ((bool) $repair->requires_customer_approval ? 'Sí' : 'No') : '',
                    'Total presupuesto' => $this->formatMoneyValue($repair->quote_total ?? $repair->total_amount ?? null),
                    'Notas presupuesto' => (string) ($repair->quote_notes ?? ''),
                ]),
                'events' => $this->eventRows($repairId, [
                    'quote_submitted',
                    'quote_approved',
                    'quote_rejected',
                    'approval_created',
                    'approval_approved',
                ]),
            ],
            [
                'title' => 'Diagnóstico y reparación',
                'status' => in_array($stage, ['in_repair', 'repaired', 'supervisor_review', 'ready_for_delivery', 'delivered'], true)
                    ? 'En proceso / realizada'
                    : 'Pendiente',
                'items' => $this->cleanItems([
                    'Área responsable' => filled($repair->repair_started_at ?? $repair->started_at ?? null) ? 'Servicio técnico' : '',
                    'Fecha asignación' => $this->formatDate($repair->assigned_at ?? null),
                    'Inicio reparación' => $this->formatDate($repair->repair_started_at ?? $repair->started_at ?? null),
                    'Fin reparación' => $this->formatDate($repair->repair_finished_at ?? $repair->finished_at ?? null),
                    'Diagnóstico técnico' => (string) ($repair->technical_diagnosis ?? ''),
                    'Refacciones requeridas' => (string) ($repair->parts_required ?? ''),
                    'Solución / trabajo realizado' => (string) ($repair->resolution ?? ''),
                    'Evidencia solución' => $this->attachmentSummary($repairId, ['solution']),
                ]),
                'events' => $this->eventRows($repairId, [
                    'repair_started',
                    'repair_finished',
                    'solution_files_uploaded',
                    'repair_marked_repaired',
                    'ready_for_delivery',
                ]),
            ],
            [
                'title' => 'Entrega',
                'status' => filled($repair->delivered_at ?? null)
                    ? 'Entregada'
                    : (filled($repair->ready_for_delivery_at ?? null) ? 'Lista para entrega' : 'Pendiente'),
                'items' => $this->cleanItems([
                    'Lista para entrega' => $this->formatDate($repair->ready_for_delivery_at ?? null),
                    'Fecha de entrega' => $this->formatDate($repair->delivered_at ?? null),
                    'Entregado a' => (string) ($repair->delivered_to ?? ''),
                    'Observaciones entrega' => (string) ($repair->delivery_notes ?? ''),
                    'Firma entrega' => $this->hasAttachmentStage($repairId, 'delivery_signature') ? 'Sí' : 'Pendiente',
                    'Evidencia entrega' => $this->attachmentSummary($repairId, ['delivery', 'delivery_signature']),
                ]),
                'events' => $this->eventRows($repairId, [
                    'delivery_files_uploaded',
                    'delivery_signature_captured',
                    'repair_delivered',
                ]),
            ],
            [
                'title' => 'Pago / cierre',
                'status' => $this->humanEconomicStatus((string) ($repair->economic_status ?? $repair->economic_payment_status ?? '')),
                'items' => $this->cleanItems([
                    'Estado económico' => $this->humanEconomicStatus((string) ($repair->economic_status ?? $repair->economic_payment_status ?? '')),
                    'Listo para cobro' => $this->formatDate($repair->ready_to_charge_at ?? null),
                    'Fecha de cobro' => $this->formatDate($repair->economic_paid_at ?? null),
                    'Total del servicio' => $this->formatMoneyValue($repair->total_amount ?? $repair->economic_total ?? $repair->quote_total ?? null),
                ]),
                'events' => $this->eventRows($repairId, [
                    'economic_closure_created',
                    'account_receivable_created',
                    'payment_synced',
                    'repair_paid',
                ]),
            ],
        ];
    }

    protected function eventRows(int $repairId, array $types): array
    {
        if ($repairId <= 0 || ! Schema::hasTable('service_case_events')) {
            return [];
        }

        $columns = Schema::getColumnListing('service_case_events');

        $query = DB::table('service_case_events');

        if (in_array('repair_order_id', $columns, true)) {
            $query->where('repair_order_id', $repairId);
        } else {
            return [];
        }

        if (in_array('event_type', $columns, true) && $types !== []) {
            $query->whereIn('event_type', $types);
        }

        return $query
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(function (object $row): array {
                $type = (string) $this->pick($row, ['event_type', 'type', 'action'], '');
                $user = '';

                return [
                    'label' => $this->humanEventType($type),
                    'date' => $this->formatDate($this->pick($row, ['created_at', 'event_at', 'updated_at'])),
                    'user' => '',
                    'notes' => '',
                ];
            })
            ->all();
    }

    protected function cleanItems(array $items): array
    {
        return collect($items)
            ->filter(fn ($value): bool => filled($value))
            ->all();
    }

    protected function hasAttachmentStage(int $repairId, string $stage): bool
    {
        if ($repairId <= 0 || ! Schema::hasTable('service_attachments')) {
            return false;
        }

        return DB::table('service_attachments')
            ->where('repair_order_id', $repairId)
            ->where('stage', $stage)
            ->exists();
    }

    protected function attachmentSummary(int $repairId, array $stages): string
    {
        if ($repairId <= 0 || ! Schema::hasTable('service_attachments')) {
            return '';
        }

        $query = DB::table('service_attachments')
            ->where('repair_order_id', $repairId)
            ->whereIn('stage', $stages);

        $count = $query->count();

        if ($count <= 0) {
            return '';
        }

        return $count === 1 ? '1 archivo registrado' : $count . ' archivos registrados';
    }

    protected function signaturePersonFromNotes(int $repairId, string $stage): string
    {
        if ($repairId <= 0 || ! Schema::hasTable('service_attachments')) {
            return '';
        }

        $notes = (string) DB::table('service_attachments')
            ->where('repair_order_id', $repairId)
            ->where('stage', $stage)
            ->orderByDesc('id')
            ->value('notes');

        if ($notes === '') {
            return '';
        }

        foreach (['Entrega:', 'Recibe:', 'Entregado a:'] as $marker) {
            if (str_contains($notes, $marker)) {
                return trim(Str::of($notes)->after($marker)->before(' - ')->toString());
            }
        }

        return '';
    }

    protected function userName(mixed $id): string
    {
        $user = $this->recordById('users', $id);

        return $this->displayName($user, '');
    }

    protected function employeeName(mixed $id): string
    {
        return $this->displayName($this->firstExistingRecord(['employees', 'users'], $id), '');
    }

    protected function formatMoneyValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return '$' . number_format((float) $value, 2);
    }

    protected function humanQuoteStatus(string $status): string
    {
        return [
            'draft' => 'Borrador',
            'pending' => 'Pendiente',
            'submitted' => 'Enviado a autorización',
            'customer_approved' => 'Autorizado por cliente',
            'approved' => 'Autorizado',
            'rejected' => 'Rechazado',
            'cancelled' => 'Cancelado',
        ][$status] ?? ($status !== '' ? (string) Str::of($status)->replace('_', ' ')->title() : '');
    }

    protected function humanEconomicStatus(string $status): string
    {
        return [
            'pending' => 'Pendiente',
            'ready_to_charge' => 'Listo para cobro',
            'charged' => 'Cobrado',
            'paid' => 'Pagado',
            'cancelled' => 'Cancelado',
        ][$status] ?? ($status !== '' ? (string) Str::of($status)->replace('_', ' ')->title() : '');
    }

    protected function humanEventType(string $type): string
    {
        return [
            'repair_created' => 'Reparación creada',
            'reception_files_uploaded' => 'Evidencia de recepción agregada',
            'reception_signature_captured' => 'Firma de recepción capturada',
            'quote_submitted' => 'Presupuesto enviado',
            'quote_approved' => 'Presupuesto autorizado',
            'quote_rejected' => 'Presupuesto rechazado',
            'approval_created' => 'Solicitud de aprobación creada',
            'approval_approved' => 'Aprobación realizada',
            'repair_started' => 'Reparación iniciada',
            'repair_finished' => 'Reparación finalizada',
            'solution_files_uploaded' => 'Evidencia de solución agregada',
            'repair_marked_repaired' => 'Marcada como reparada',
            'ready_for_delivery' => 'Lista para entrega',
            'delivery_files_uploaded' => 'Evidencia de entrega agregada',
            'delivery_signature_captured' => 'Firma de entrega capturada',
            'repair_delivered' => 'Entregada al cliente',
            'economic_closure_created' => 'Cierre económico creado',
            'account_receivable_created' => 'Registro de cobro generado',
            'payment_synced' => 'Pago sincronizado',
            'repair_paid' => 'Servicio pagado',
        ][$type] ?? ($type !== '' ? (string) Str::of($type)->replace('_', ' ')->title() : 'Evento');
    }

    protected function customerStatus(string $stage, object $repair): array
    {
        $map = [
            'quote_draft' => ['label' => 'Presupuesto en preparación', 'message' => 'Estamos preparando la revisión o presupuesto de tu servicio.'],
            'pending_approval' => ['label' => 'Presupuesto pendiente de autorización', 'message' => 'El presupuesto está en revisión para autorización.'],
            'quote_approved' => ['label' => 'Presupuesto autorizado', 'message' => 'El servicio fue autorizado y continuará su proceso.'],
            'in_repair' => ['label' => 'En reparación', 'message' => 'Tu equipo o producto está en proceso de reparación.'],
            'repaired' => ['label' => 'Reparación terminada', 'message' => 'La reparación fue marcada como terminada.'],
            'supervisor_review' => ['label' => 'En revisión final', 'message' => 'Estamos revisando el servicio antes de liberarlo.'],
            'ready_for_delivery' => ['label' => 'Listo para entrega', 'message' => 'Tu equipo o producto ya está listo para ser entregado.'],
            'delivered' => ['label' => 'Entregado', 'message' => 'El servicio fue entregado.'],
            'cancelled' => ['label' => 'Cancelado', 'message' => 'El servicio fue cancelado o no continuó.'],
        ];

        if (isset($map[$stage])) {
            return $map[$stage];
        }

        if (! empty($repair->delivered_at)) {
            return $map['delivered'];
        }

        if (! empty($repair->ready_for_delivery_at)) {
            return $map['ready_for_delivery'];
        }

        if (! empty($repair->repair_finished_at)) {
            return $map['repaired'];
        }

        if (! empty($repair->repair_started_at)) {
            return $map['in_repair'];
        }

        return [
            'label' => 'Recibido',
            'message' => 'Hemos recibido tu equipo o producto para revisión.',
        ];
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

    protected function pick(?object $record, array|string $fields, mixed $default = null): mixed
    {
        if (! $record) {
            return $default;
        }

        foreach ((array) $fields as $field) {
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
            return Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    protected function logoUrl(?object $company): ?string
    {
        $logo = null;

        foreach (['logo_path', 'logo', 'logo_url', 'image_path'] as $field) {
            if ($company && property_exists($company, $field) && filled($company->{$field})) {
                $logo = (string) $company->{$field};
                break;
            }
        }

        if (! $logo) {
            return null;
        }

        if (Str::startsWith($logo, ['http://', 'https://'])) {
            return $logo;
        }

        if (Str::startsWith($logo, ['storage/'])) {
            return asset($logo);
        }

        return asset('storage/' . ltrim($logo, '/'));
    }
}
