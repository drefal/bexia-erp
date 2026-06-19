<?php

namespace App\Filament\Pages;

use App\Support\Service\ServiceAccess;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServiceRepairKanban extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationGroup = 'Atencion y Servicio';

    protected static ?string $navigationLabel = 'Kanban de servicio';

    protected static ?string $title = 'Kanban de servicio';

    protected static ?int $navigationSort = 12;

    protected static string $view = 'filament.pages.service-repair-kanban';

    public ?int $transitionRepairId = null;

    public ?string $transitionFromStage = null;

    public ?string $transitionTargetStage = null;

    public string $transitionNotes = '';

    public string $deliveredTo = '';

    public string $deliveryNotes = '';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return ServiceAccess::can([
            'service.menu.view',
            'service.repairs.view',
            'service.repairs.update',
        ]);
    }

    public function columns(): array
    {
        return [
            'quote_draft' => 'Borrador',
            'pending_approval' => 'Pendiente aprobación',
            'quote_approved' => 'Aprobada',
            'in_repair' => 'En reparación',
            'repaired' => 'Reparado',
            'ready_for_delivery' => 'Listo entrega',
            'delivered' => 'Entregado',
        ];
    }

    public function stageColor(string $stage): string
    {
        return match ($stage) {
            'quote_draft' => 'border-yellow-300 bg-yellow-50',
            'pending_approval' => 'border-orange-300 bg-orange-50',
            'quote_approved' => 'border-blue-300 bg-blue-50',
            'in_repair' => 'border-indigo-300 bg-indigo-50',
            'repaired' => 'border-green-300 bg-green-50',
            'ready_for_delivery' => 'border-teal-300 bg-teal-50',
            'delivered' => 'border-gray-300 bg-gray-50',
            default => 'border-gray-200 bg-white',
        };
    }

    public function getBoard(): array
    {
        if (! Schema::hasTable('repair_orders')) {
            return [];
        }

        $query = DB::table('repair_orders')
            ->select('repair_orders.*');

        $companyId = ServiceAccess::currentCompanyId();

        if ($companyId && Schema::hasColumn('repair_orders', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        $rows = $query
            ->orderByDesc(Schema::hasColumn('repair_orders', 'updated_at') ? 'updated_at' : 'id')
            ->limit(250)
            ->get();

        $board = [];

        foreach (array_keys($this->columns()) as $stage) {
            $board[$stage] = [];
        }

        foreach ($rows as $row) {
            $stage = (string) ($row->workflow_stage ?? '');

            if ($stage === '' || ! array_key_exists($stage, $board)) {
                $stage = 'quote_draft';
            }

            $board[$stage][] = $row;
        }

        return $board;
    }

    public function cardDescription(object $repair): string
    {
        $repairFields = [
            'problem_description',
            'reported_problem',
            'customer_report',
            'customer_problem',
            'failure_description',
            'diagnosis',
            'diagnostic_notes',
            'description',
            'issue',
            'notes',
            'observations',
            'symptoms',
        ];

        $direct = $this->firstFilledField($repair, $repairFields);

        if ($direct !== null) {
            return str($direct)->limit(120)->toString();
        }

        $serviceCaseId = $repair->service_case_id ?? null;

        if ($serviceCaseId && Schema::hasTable('service_cases')) {
            $case = DB::table('service_cases')->where('id', $serviceCaseId)->first();

            if ($case) {
                $caseFields = [
                    'subject',
                    'title',
                    'problem_description',
                    'reported_problem',
                    'customer_report',
                    'customer_problem',
                    'description',
                    'issue',
                    'notes',
                    'observations',
                    'symptoms',
                ];

                $fromCase = $this->firstFilledField($case, $caseFields);

                if ($fromCase !== null) {
                    return str($fromCase)->limit(120)->toString();
                }
            }
        }

        return 'Sin descripción capturada';
    }

    protected function firstFilledField(object $row, array $fields): ?string
    {
        foreach ($fields as $field) {
            if (property_exists($row, $field) && filled($row->{$field})) {
                return (string) $row->{$field};
            }
        }

        return null;
    }

    public function cardAmount(object $repair): string
    {
        foreach (['quote_total', 'budget_total', 'total'] as $field) {
            if (property_exists($repair, $field) && $repair->{$field} !== null) {
                return '$' . number_format((float) $repair->{$field}, 2);
            }
        }

        return '$0.00';
    }

    public function editUrl(int $repairId): string
    {
        $tenantId = 1;

        try {
            $tenant = \Filament\Facades\Filament::getTenant();

            if ($tenant && method_exists($tenant, 'getKey')) {
                $tenantId = $tenant->getKey();
            }
        } catch (\Throwable $exception) {
            $tenantId = 1;
        }

        return url('/admin/' . $tenantId . '/repair-orders/' . $repairId . '/edit');
    }

public function nextStageOptions(object $repair): array
    {
        $stage = (string) ($repair->workflow_stage ?: $repair->status ?: '');

        $labels = [
            'quote_draft' => 'Borrador',
            'pending_approval' => 'Pendiente aprobación',
            'quote_approved' => 'Aprobada',
            'in_repair' => 'En reparación',
            'repaired' => 'Reparado',
            'ready_for_delivery' => 'Listo entrega',
            'delivered' => 'Entregado',
            'cancelled' => 'Cancelado',
        ];

        $options = [];

        foreach ($this->allowedTargetsForStage($stage) as $targetStage) {
            $options[$targetStage] = $labels[$targetStage] ?? $targetStage;
        }

        return $options;
    }



public function allowedTargetsForStage(?string $fromStage): array
    {
        $fromStage = (string) $fromStage;

        return match ($fromStage) {
            'quote_approved' => ['in_repair'],
            'in_repair' => ['repaired'],
            'repaired' => ['ready_for_delivery'],
            'ready_for_delivery' => ['delivered'],
            default => [],
        };
    }



    public function requestStageChange($repairId, string $targetStage): void
    {
        $repairId = (int) $repairId;

        if ($repairId <= 0) {
            Notification::make()
                ->title('No se pudo identificar la reparación')
                ->body('Intenta usar el botón de avance dentro de la tarjeta.')
                ->danger()
                ->send();

            return;
        }

        if (! array_key_exists($targetStage, $this->columns())) {
            Notification::make()
                ->title('Etapa no válida')
                ->danger()
                ->send();

            return;
        }

        $repair = DB::table('repair_orders')->where('id', $repairId)->first();

        if (! $repair) {
            Notification::make()
                ->title('No se encontró la reparación')
                ->danger()
                ->send();

            return;
        }

        $fromStage = (string) ($repair->workflow_stage ?? 'quote_draft');

        if ($fromStage === $targetStage) {
            return;
        }

        if (! $this->canMove($fromStage, $targetStage)) {
            Notification::make()
                ->title('Movimiento no permitido')
                ->body($this->blockedMoveMessage($fromStage, $targetStage))
                ->warning()
                ->send();

            return;
        }

        $this->transitionRepairId = $repairId;
        $this->transitionFromStage = $fromStage;
        $this->transitionTargetStage = $targetStage;
        $this->transitionNotes = '';
        $this->deliveredTo = '';
        $this->deliveryNotes = '';

        $this->dispatch('open-modal', id: 'service-kanban-transition-modal');
    }

    public function canMove(string $fromStage, string $targetStage): bool
    {
        return in_array($targetStage, $this->allowedTargetsForStage($fromStage), true);
    }

    public function blockedMoveMessage(string $fromStage, string $targetStage): string
    {
        if ($fromStage === 'quote_draft' && $targetStage === 'pending_approval') {
            return 'El envío a aprobación debe hacerse desde la reparación para crear la solicitud formal de aprobación.';
        }

        if ($targetStage === 'quote_approved') {
            return 'La aprobación debe hacerse desde Mis aprobaciones; no se permite aprobar arrastrando.';
        }

        return 'Solo se permite avanzar al siguiente paso operativo permitido.';
    }

    public function transitionHeading(): string
    {
        return match ($this->transitionTargetStage) {
            'in_repair' => 'Tomar / iniciar reparación',
            'repaired' => 'Marcar reparación como reparada',
            'ready_for_delivery' => 'Marcar lista para entrega',
            'delivered' => 'Entregar al cliente',
            default => 'Cambiar etapa',
        };
    }

    public function transitionHint(): string
    {
        return match ($this->transitionTargetStage) {
            'in_repair' => 'Se registrará el inicio de reparación si aún no existe.',
            'repaired' => 'Se registrará la fecha de término y se calcularán las horas hábiles reales.',
            'ready_for_delivery' => 'La reparación quedará lista para que el cliente la recoja.',
            'delivered' => 'Se registrará quién recibe y la fecha de entrega.',
            default => 'Confirma el cambio de etapa.',
        };
    }

    public function confirmStageChange(): void
    {
        if (! $this->transitionRepairId || ! $this->transitionTargetStage) {
            return;
        }

        $repair = DB::table('repair_orders')->where('id', $this->transitionRepairId)->first();

        if (! $repair) {
            Notification::make()
                ->title('No se encontró la reparación')
                ->danger()
                ->send();

            return;
        }

        $fromStage = (string) ($repair->workflow_stage ?? 'quote_draft');
        $targetStage = (string) $this->transitionTargetStage;

        if (! $this->canMove($fromStage, $targetStage)) {
            Notification::make()
                ->title('Movimiento no permitido')
                ->warning()
                ->send();

            return;
        }

        $now = Carbon::now();
        $payload = [
            'workflow_stage' => $targetStage,
            'status' => $targetStage,
            'updated_at' => $now,
        ];

        if ($targetStage === 'in_repair') {
            if (empty($repair->repair_started_at ?? null)) {
                $payload['repair_started_at'] = $now;
            }

            if (empty($repair->started_at ?? null)) {
                $payload['started_at'] = $now;
            }
        }

        if ($targetStage === 'repaired') {
            if (blank($this->transitionNotes)) {
                Notification::make()
                    ->title('Falta la solución')
                    ->body('Captura la solución o comentario de cierre.')
                    ->danger()
                    ->send();

                return;
            }

            $payload['repair_finished_at'] = $now;
            $payload['finished_at'] = $now;

            foreach (['final_resolution', 'solution_notes', 'resolution_notes', 'repair_notes'] as $column) {
                $payload[$column] = $this->transitionNotes;
            }

            $start = $repair->repair_started_at ?? $repair->started_at ?? null;
            $hours = $this->businessHoursBetween($start, $now);

            $payload['actual_labor_hours'] = $hours;

            $rate = (float) ($repair->labor_hour_rate ?? 0);
            $payload['actual_labor_cost'] = round($hours * $rate, 2);
        }

        if ($targetStage === 'ready_for_delivery') {
            $payload['ready_for_delivery_at'] = $now;
        }

        if ($targetStage === 'delivered') {
            if (blank($this->deliveredTo)) {
                Notification::make()
                    ->title('Falta quién recibe')
                    ->body('Captura el nombre de la persona que recibe.')
                    ->danger()
                    ->send();

                return;
            }

            $payload['delivered_at'] = $now;
            $payload['delivered_to'] = $this->deliveredTo;
            $payload['delivery_notes'] = $this->deliveryNotes;
        }

        $payload = $this->repairOrderPayloadForExistingColumns($payload);

        DB::table('repair_orders')
            ->where('id', $repair->id)
            ->update($payload);

        $this->createTransitionEvent($repair, $fromStage, $targetStage);

        $this->dispatch('close-modal', id: 'service-kanban-transition-modal');

        Notification::make()
            ->title('Etapa actualizada')
            ->body(($repair->folio ?? 'Reparación') . ' cambió a ' . ($this->columns()[$targetStage] ?? $targetStage))
            ->success()
            ->send();

        $this->reset([
            'transitionRepairId',
            'transitionFromStage',
            'transitionTargetStage',
            'transitionNotes',
            'deliveredTo',
            'deliveryNotes',
        ]);
    }

    protected function repairOrderPayloadForExistingColumns(array $payload): array
    {
        $columns = Schema::getColumnListing('repair_orders');

        return array_filter(
            $payload,
            fn ($value, string $key): bool => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    protected function businessHoursBetween(null|string|\DateTimeInterface $start, \DateTimeInterface|string $end): float
    {
        if (! $start) {
            return 0.0;
        }

        $start = Carbon::parse($start);
        $end = Carbon::parse($end);

        if ($end->lessThanOrEqualTo($start)) {
            return 0.0;
        }

        $minutes = 0;
        $day = $start->copy()->startOfDay();

        while ($day->lessThanOrEqualTo($end)) {
            $iso = (int) $day->dayOfWeekIso;

            if ($iso >= 1 && $iso <= 5) {
                $windowStart = $day->copy()->setTime(9, 0);
                $windowEnd = $day->copy()->setTime(17, 0);
            } elseif ($iso === 6) {
                $windowStart = $day->copy()->setTime(9, 0);
                $windowEnd = $day->copy()->setTime(14, 0);
            } else {
                $day->addDay();

                continue;
            }

            $from = $start->greaterThan($windowStart) ? $start->copy() : $windowStart;
            $to = $end->lessThan($windowEnd) ? $end->copy() : $windowEnd;

            if ($to->greaterThan($from)) {
                $minutes += $from->diffInMinutes($to);
            }

            $day->addDay();
        }

        return round($minutes / 60, 2);
    }

    protected function createTransitionEvent(object $repair, string $fromStage, string $targetStage): void
    {
        if (! Schema::hasTable('service_case_events')) {
            return;
        }

        $columns = Schema::getColumnListing('service_case_events');
        $payload = [];

        $map = [
            'company_id' => $repair->company_id ?? null,
            'service_case_id' => $repair->service_case_id ?? null,
            'repair_order_id' => $repair->id ?? null,
            'event_type' => 'kanban_stage_change',
            'type' => 'kanban_stage_change',
            'title' => 'Cambio de etapa por Kanban',
            'description' => 'Cambio de ' . $fromStage . ' a ' . $targetStage,
            'notes' => 'Cambio de ' . $fromStage . ' a ' . $targetStage,
            'from_stage' => $fromStage,
            'to_stage' => $targetStage,
            'created_by' => auth()->id(),
            'user_id' => auth()->id(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        foreach ($map as $column => $value) {
            if (in_array($column, $columns, true) && $value !== null) {
                $payload[$column] = $value;
            }
        }

        if (! empty($payload)) {
            DB::table('service_case_events')->insert($payload);
        }
    }

protected function canMoveStage(?string $fromStage, ?string $toStage): bool
    {
        $fromStage = (string) $fromStage;
        $toStage = (string) $toStage;

        if ($fromStage === '' || $toStage === '') {
            return false;
        }

        if ($fromStage === $toStage) {
            return false;
        }

        return in_array($toStage, $this->allowedTargetsForStage($fromStage), true);
    }
}
