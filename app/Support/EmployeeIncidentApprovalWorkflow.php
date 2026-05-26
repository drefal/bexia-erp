<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeIncident;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\EmployeeVacationBalanceCalculator;

class EmployeeIncidentApprovalWorkflow
{
    public const DOCUMENT_TYPE = 'employee_incident';

    public static function hasApplicableWorkflow(EmployeeIncident $incident): bool
    {
        return static::findWorkflow($incident) !== null;
    }

    public static function sendToApproval(EmployeeIncident $incident): object
    {
        if (
            ! Schema::hasTable('approval_workflows')
            || ! Schema::hasTable('approval_workflow_steps')
            || ! Schema::hasTable('approval_requests')
            || ! Schema::hasTable('approval_request_steps')
        ) {
            throw new \RuntimeException('El motor de aprobaciones no está instalado.');
        }

        $incident->refresh();

        if (in_array((string) $incident->status, ['pending', 'approved'], true)) {
            throw new \RuntimeException('La incidencia ya está pendiente o aprobada.');
        }

        static::validateVacationBalanceBeforeSend($incident);

        $workflow = static::findWorkflow($incident);

        if (! $workflow) {
            throw new \RuntimeException('No existe un flujo activo para Incidencia RRHH. Configúralo en Configuración empresa > Flujos de aprobación.');
        }

        $steps = static::workflowSteps($workflow, $incident);

        if ($steps->isEmpty()) {
            throw new \RuntimeException('El flujo de Incidencia RRHH no tiene etapas activas aplicables.');
        }

        return DB::transaction(function () use ($incident, $workflow, $steps): object {
            $existing = DB::table('approval_requests')
                ->where('approvable_type', EmployeeIncident::class)
                ->where('approvable_id', $incident->id)
                ->where('document_type', self::DOCUMENT_TYPE)
                ->where('status', 'pending')
                ->orderByDesc('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            $firstStep = $steps->first();
            $firstOrder = (int) ($firstStep->sort_order ?? 1);
            $requesterId = (int) (($incident->created_by_user_id ?? null) ?: auth()->id());
            $requesterName = null;

            if ($requesterId > 0 && Schema::hasTable('users')) {
                $requesterName = DB::table('users')->where('id', $requesterId)->value('name')
                    ?: DB::table('users')->where('id', $requesterId)->value('email');
            }

            $documentNumber = static::documentNumber($incident);

            $requestId = DB::table('approval_requests')->insertGetId(static::filterColumns('approval_requests', [
                'company_id' => $incident->company_id,
                'approval_workflow_id' => $workflow->id,
                'approvable_type' => EmployeeIncident::class,
                'approvable_id' => $incident->id,
                'document_type' => self::DOCUMENT_TYPE,
                'document_number' => $documentNumber,
                'requester_user_id' => $requesterId ?: null,
                'requester_name' => $requesterName,
                'status' => 'pending',
                'current_step_order' => $firstOrder,
                'amount_total' => static::approvalAmount($incident),
                'sent_at' => now(),
                'notes' => $incident->title,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            foreach ($steps as $index => $step) {
                foreach (static::approvalStepRows($step, $incident, $index === 0) as $row) {
                    $row['approval_request_id'] = $requestId;
                    DB::table('approval_request_steps')->insert(
                        static::filterColumns('approval_request_steps', $row)
                    );
                }
            }

            DB::table('employee_incidents')
                ->where('id', $incident->id)
                ->update(static::filterColumns('employee_incidents', [
                    'status' => 'pending',
                    'requires_approval' => true,
                    'updated_by_user_id' => auth()->id(),
                    'updated_at' => now(),
                ]));

            $request = DB::table('approval_requests')->where('id', $requestId)->first();

            static::notifyCurrentApprovers($request);
            static::notifyRequester(
                $request,
                'Incidencia enviada a aprobación',
                'La incidencia ' . $documentNumber . ' fue enviada al flujo de aprobación.',
                'employee_incident_sent_to_approval',
                'Solicitud enviada a aprobación.'
            );

            return $request;
        });
    }

    public static function findWorkflow(EmployeeIncident $incident): ?object
    {
        if (! Schema::hasTable('approval_workflows')) {
            return null;
        }

        $amount = static::approvalAmount($incident);
        $companyId = (int) ($incident->company_id ?? 0);
        $requesterId = (int) (($incident->created_by_user_id ?? null) ?: auth()->id());

        $query = DB::table('approval_workflows')
            ->where('document_type', self::DOCUMENT_TYPE);

        if (Schema::hasColumn('approval_workflows', 'is_active')) {
            $query->where('is_active', true);
        }

        if ($companyId > 0 && Schema::hasColumn('approval_workflows', 'company_id')) {
            $query->where(function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        if (Schema::hasColumn('approval_workflows', 'amount_min')) {
            $query->where(function ($query) use ($amount): void {
                $query->whereNull('amount_min')->orWhere('amount_min', '<=', $amount);
            });
        }

        if (Schema::hasColumn('approval_workflows', 'amount_max')) {
            $query->where(function ($query) use ($amount): void {
                $query->whereNull('amount_max')->orWhere('amount_max', '>=', $amount);
            });
        }

        if ($requesterId > 0 && Schema::hasColumn('approval_workflows', 'applies_to_user_id')) {
            $query->where(function ($query) use ($requesterId): void {
                $query->whereNull('applies_to_user_id')->orWhere('applies_to_user_id', $requesterId);
            });
        }

        if (Schema::hasColumn('approval_workflows', 'priority')) {
            $query->orderBy('priority');
        }

        return $query->orderByDesc('company_id')->orderBy('id')->first();
    }

    protected static function workflowSteps(object $workflow, EmployeeIncident $incident)
    {
        if (! Schema::hasTable('approval_workflow_steps')) {
            return collect();
        }

        $amount = static::approvalAmount($incident);

        $query = DB::table('approval_workflow_steps')
            ->where('approval_workflow_id', $workflow->id);

        if (Schema::hasColumn('approval_workflow_steps', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('approval_workflow_steps', 'amount_min')) {
            $query->where(function ($query) use ($amount): void {
                $query->whereNull('amount_min')->orWhere('amount_min', '<=', $amount);
            });
        }

        if (Schema::hasColumn('approval_workflow_steps', 'amount_max')) {
            $query->where(function ($query) use ($amount): void {
                $query->whereNull('amount_max')->orWhere('amount_max', '>=', $amount);
            });
        }

        return $query
            ->orderBy(Schema::hasColumn('approval_workflow_steps', 'sort_order') ? 'sort_order' : 'id')
            ->orderBy('id')
            ->get();
    }

    protected static function approvalStepRows(object $step, EmployeeIncident $incident, bool $isFirstStep): array
    {
        $base = [
            'approval_workflow_step_id' => $step->id,
            'step_order' => (int) ($step->sort_order ?? 1),
            'step_name' => (string) ($step->name ?? 'Aprobación'),
            'approver_type' => (string) ($step->approver_type ?? 'specific_user'),
            'approver_user_id' => $step->approver_user_id ?? null,
            'approver_role_name' => $step->approver_role_name ?? null,
            'status' => $isFirstStep ? 'pending' : 'waiting',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $userIds = static::approverUserIds($step, $incident);

        if (empty($userIds)) {
            return [$base];
        }

        return collect($userIds)
            ->map(function (int $userId) use ($base): array {
                $row = $base;
                $row['approver_user_id'] = $userId;

                return $row;
            })
            ->values()
            ->all();
    }

    protected static function approverUserIds(object $step, EmployeeIncident $incident): array
    {
        $type = (string) ($step->approver_type ?? '');

        if ($type === 'specific_user' && ! empty($step->approver_user_id)) {
            return [(int) $step->approver_user_id];
        }

        if ($type === 'requester_manager') {
            $managerId = static::requesterManagerId($incident);

            if ($managerId) {
                return [$managerId];
            }

            return static::userIdsWithAnyRole(['Admin Empresa', 'Administrador', 'admin'], $incident->company_id, false);
        }

        if ($type === 'role' && ! empty($step->approver_role_name)) {
            return static::userIdsWithAnyRole(
                [(string) $step->approver_role_name],
                $incident->company_id,
                (bool) ($step->require_all ?? false)
            );
        }

        $roles = match ($type) {
            'company_admin' => ['Admin Empresa', 'Administrador', 'admin'],
            'group_admin' => ['Admin Grupo', 'super_admin', 'Super Admin'],
            'warehouse_responsible' => ['Inventarios', 'Admin Empresa', 'Administrador', 'admin'],
            'purchase_responsible' => ['Compras', 'Admin Empresa', 'Administrador', 'admin'],
            'accounting_responsible' => ['Contabilidad', 'Admin Empresa', 'Administrador', 'admin'],
            default => [],
        };

        return static::userIdsWithAnyRole($roles, $incident->company_id, false);
    }

    protected static function userIdsWithAnyRole(array $roles, ?int $companyId = null, bool $all = false): array
    {
        if (empty($roles) || ! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return [];
        }

        $query = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('roles.name', $roles);

        if ($companyId && Schema::hasColumn('roles', 'company_id')) {
            $query->where(function ($query) use ($companyId): void {
                $query->where('roles.company_id', $companyId)->orWhereNull('roles.company_id');
            });
        }

        $ids = $query
            ->orderBy('model_has_roles.model_id')
            ->pluck('model_has_roles.model_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $all ? $ids : array_slice($ids, 0, 1);
    }

    protected static function requesterManagerId(EmployeeIncident $incident): ?int
    {
        if (class_exists(EmployeeOrganizationResolver::class)) {
            $employeeManagerUserId = EmployeeOrganizationResolver::approvalManagerUserIdForIncident($incident);

            if ($employeeManagerUserId) {
                return $employeeManagerUserId;
            }
        }

        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'approval_manager_user_id')) {
            return null;
        }

        $requesterId = (int) (($incident->created_by_user_id ?? null) ?: auth()->id());

        if ($requesterId <= 0) {
            return null;
        }

        $managerId = DB::table('users')->where('id', $requesterId)->value('approval_manager_user_id');

        return $managerId ? (int) $managerId : null;
    }

    public static function markApproved(object $request, ?int $userId = null, ?string $comment = null): void
    {
        if (! Schema::hasTable('employee_incidents')) {
            return;
        }

        DB::table('employee_incidents')
            ->where('id', $request->approvable_id)
            ->update(static::filterColumns('employee_incidents', [
                'status' => 'approved',
                'approved_by_user_id' => $userId ?: auth()->id(),
                'approved_at' => now(),
                'resolution_notes' => $comment,
                'updated_by_user_id' => $userId ?: auth()->id(),
                'updated_at' => now(),
            ]));

        static::recalculateVacationBalanceIfNeeded($request);
    }

    public static function markRejected(object $request, ?int $userId = null, ?string $reason = null): void
    {
        if (! Schema::hasTable('employee_incidents')) {
            return;
        }

        DB::table('employee_incidents')
            ->where('id', $request->approvable_id)
            ->update(static::filterColumns('employee_incidents', [
                'status' => 'rejected',
                'resolution_notes' => $reason,
                'updated_by_user_id' => $userId ?: auth()->id(),
                'updated_at' => now(),
            ]));
    }

    protected static function validateVacationBalanceBeforeSend(EmployeeIncident $incident): void
    {
        if (! static::isVacationIncident($incident)) {
            return;
        }

        if (! class_exists(EmployeeVacationBalanceCalculator::class)) {
            throw new \RuntimeException('El calculador de vacaciones no está disponible.');
        }

        $employee = $incident->employee ?: Employee::query()->find($incident->employee_id);

        if (! $employee) {
            throw new \RuntimeException('No se encontró el empleado de la incidencia.');
        }

        if (blank($employee->hire_date)) {
            throw new \RuntimeException('El empleado no tiene fecha de ingreso. Captúrala en Empleados > Contrato y nómina antes de solicitar vacaciones.');
        }

        $requestedDays = static::requestedVacationDays($incident);

        if ($requestedDays <= 0) {
            throw new \RuntimeException('La solicitud de vacaciones debe tener una cantidad de días mayor a cero.');
        }

        $balance = EmployeeVacationBalanceCalculator::generateCurrentBalance($employee, auth()->id());
        $availableDays = (float) $balance->pending_days;

        if ($requestedDays > ($availableDays + 0.0001)) {
            throw new \RuntimeException(
                'Días de vacaciones insuficientes. Solicitados: '
                . number_format($requestedDays, 2)
                . ', disponibles: '
                . number_format($availableDays, 2)
                . '.'
            );
        }
    }

    protected static function recalculateVacationBalanceIfNeeded(object $request): void
    {
        if (($request->document_type ?? null) !== self::DOCUMENT_TYPE) {
            return;
        }

        if (! class_exists(EmployeeVacationBalanceCalculator::class) || ! Schema::hasTable('employee_incidents')) {
            return;
        }

        $incident = EmployeeIncident::query()->find($request->approvable_id ?? null);

        if (! $incident || ! static::isVacationIncident($incident)) {
            return;
        }

        $employee = $incident->employee ?: Employee::query()->find($incident->employee_id);

        if (! $employee || blank($employee->hire_date)) {
            return;
        }

        EmployeeVacationBalanceCalculator::generateCurrentBalance($employee, auth()->id());
    }

    protected static function isVacationIncident(EmployeeIncident $incident): bool
    {
        if (! Schema::hasTable('hr_incident_types') || blank($incident->hr_incident_type_id)) {
            return false;
        }

        $code = DB::table('hr_incident_types')
            ->where('id', $incident->hr_incident_type_id)
            ->value('code');

        return strtoupper((string) $code) === EmployeeVacationBalanceCalculator::VACATION_INCIDENT_CODE;
    }

    protected static function requestedVacationDays(EmployeeIncident $incident): float
    {
        if ($incident->quantity !== null && $incident->quantity_unit === 'days') {
            return round((float) $incident->quantity, 2);
        }

        if (blank($incident->start_date)) {
            return 0.0;
        }

        $start = \Carbon\CarbonImmutable::parse($incident->start_date);
        $end = $incident->end_date
            ? \Carbon\CarbonImmutable::parse($incident->end_date)
            : $start;

        return round((float) ($start->diffInDays($end) + 1), 2);
    }

    public static function documentUrl(object $row): string
    {
        $companyId = (int) ($row->company_id ?? 0);
        $id = (int) ($row->approvable_id ?? $row->id ?? 0);

        if ($companyId <= 0 || $id <= 0) {
            return '#';
        }

        return url('/admin/' . $companyId . '/employee-incidents/' . $id . '/edit');
    }

    public static function documentNumber(EmployeeIncident $incident): string
    {
        return 'INC-' . str_pad((string) $incident->id, 6, '0', STR_PAD_LEFT);
    }

    protected static function approvalAmount(EmployeeIncident $incident): float
    {
        if ($incident->payroll_amount !== null) {
            return (float) $incident->payroll_amount;
        }

        return (float) ($incident->quantity ?? 0);
    }

    protected static function notifyCurrentApprovers(object $request): void
    {
        if (! class_exists(\App\Support\BexiaUserNotification::class) || ! Schema::hasTable('approval_request_steps')) {
            return;
        }

        $steps = DB::table('approval_request_steps')
            ->where('approval_request_id', $request->id)
            ->where('step_order', $request->current_step_order)
            ->where('status', 'pending')
            ->whereNotNull('approver_user_id')
            ->get();

        foreach ($steps as $step) {
            \App\Support\BexiaUserNotification::send(
                (int) $step->approver_user_id,
                'Incidencia RRHH pendiente de aprobación',
                'Tienes una incidencia pendiente: ' . $request->document_number,
                static::documentUrl($request),
                (int) $request->company_id,
                'employee_incident_pending_approval',
                [
                    'approval_request_id' => (int) $request->id,
                    'document_type' => self::DOCUMENT_TYPE,
                    'document_number' => (string) $request->document_number,
                    'approvable_id' => (int) $request->approvable_id,
                ]
            );
        }
    }

    public static function notifyRequester(object $request, string $title, string $body, string $type, string $reason): void
    {
        if (! class_exists(\App\Support\BexiaUserNotification::class)) {
            return;
        }

        $requesterId = (int) ($request->requester_user_id ?? 0);
        $companyId = (int) ($request->company_id ?? 0);

        if ($requesterId <= 0 || $companyId <= 0) {
            return;
        }

        \App\Support\BexiaUserNotification::send(
            $requesterId,
            $title,
            $body,
            static::documentUrl($request),
            $companyId,
            $type,
            [
                'approval_request_id' => (int) $request->id,
                'document_type' => self::DOCUMENT_TYPE,
                'document_number' => (string) ($request->document_number ?? ''),
                'approvable_id' => (int) ($request->approvable_id ?? 0),
                'reason' => $reason,
            ]
        );
    }

    protected static function filterColumns(string $table, array $data): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $columns = Schema::getColumnListing($table);

        return array_filter(
            $data,
            fn ($value, $key): bool => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
