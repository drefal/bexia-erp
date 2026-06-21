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
            'publicNotes' => $publicNotes,
            'companyLogoUrl' => $companyLogoUrl,
            'updatedAt' => $this->formatDate($repair->updated_at ?? null),
        ]);
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
