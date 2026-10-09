<?php

namespace App\Support\Expenses;

use App\Models\Employee;
use App\Support\Security\BexiaTenantPermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExpenseAccess
{
    public static function isAdmin(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ((bool) ($user->is_system_admin ?? false)) {
            return true;
        }

        return BexiaTenantPermission::can('expenses.admin');
    }

    public static function canViewAll(): bool
    {
        return static::isAdmin()
            || BexiaTenantPermission::can(
                'expenses.view_all'
            );
    }

    public static function canCreateForOthers(): bool
    {
        return static::isAdmin()
            || BexiaTenantPermission::can(
                'expenses.create_for_others'
            );
    }

    public static function currentEmployeeId(
        ?int $companyId = null
    ): ?int {
        $userId = (int) (auth()->id() ?? 0);

        if ($userId <= 0) {
            return null;
        }

        $query = Employee::query()
            ->where('user_id', $userId)
            ->where('active', true);

        if ($companyId) {
            $query->where(
                'company_id',
                $companyId
            );
        }

        $id = $query->value('id');

        return $id ? (int) $id : null;
    }

    public static function canAccessEmployee(
        ?int $companyId,
        ?int $employeeId
    ): bool {
        if (static::canViewAll()) {
            return true;
        }

        if (! $companyId || ! $employeeId) {
            return false;
        }

        return static::currentEmployeeId(
            $companyId
        ) === (int) $employeeId;
    }

    public static function scopeEmployeeQuery(
        Builder $query,
        ?int $companyId,
        string $column = 'employee_id'
    ): Builder {
        if (static::canViewAll()) {
            return $query;
        }

        $employeeId =
            static::currentEmployeeId(
                $companyId
            );

        if (! $employeeId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(
            $column,
            $employeeId
        );
    }

    public static function employeeOptions(
        ?int $companyId
    ): array {
        if (! $companyId) {
            return [];
        }

        $query = Employee::query()
            ->where(
                'company_id',
                $companyId
            )
            ->where('active', true);

        if (! static::canViewAll()) {
            $employeeId =
                static::currentEmployeeId(
                    $companyId
                );

            if (! $employeeId) {
                return [];
            }

            $query->whereKey(
                $employeeId
            );
        }

        return $query
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public static function assertAdmin(): void
    {
        if (! static::isAdmin()) {
            throw new RuntimeException(
                'Solo un usuario con permiso '
                . 'Gastos - Administrador puede '
                . 'aprobar o rechazar este documento.'
            );
        }
    }

    public static function isExpenseAdvanceTransfer(
        object $request
    ): bool {
        $type = (string) (
            $request->type ?? ''
        );

        if (! in_array(
            $type,
            [
                'expense_advance',
                'expense_advance_reimbursement',
            ],
            true
        )) {
            return false;
        }

        $metadata =
            static::metadataArray(
                $request->metadata ?? null
            );

        return (
            ($metadata['module'] ?? null)
                === 'expenses'
            && ($metadata['source'] ?? null)
                === 'petty_cash'
            && ! empty(
                $metadata['expense_advance_id']
            )
        );
    }

    public static function approvalRequiresAdmin(
        object $row
    ): bool {
        $documentType = (string) (
            $row->document_type ?? ''
        );

        if ($documentType === 'expense_report') {
            return true;
        }

        if (
            $documentType
            !== 'treasury_cash_transfer_request'
        ) {
            return false;
        }

        $requestId = (int) (
            $row->approvable_id ?? 0
        );

        if ($requestId <= 0) {
            return false;
        }

        $request = DB::table(
            'treasury_cash_transfer_requests'
        )
            ->where('id', $requestId)
            ->select(
                'id',
                'type',
                'metadata'
            )
            ->first();

        return $request
            ? static::isExpenseAdvanceTransfer(
                $request
            )
            : false;
    }

    protected static function metadataArray(
        mixed $metadata
    ): array {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (
            is_string($metadata)
            && trim($metadata) !== ''
        ) {
            $decoded = json_decode(
                $metadata,
                true
            );

            return is_array($decoded)
                ? $decoded
                : [];
        }

        return [];
    }
}
