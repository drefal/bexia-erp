<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeIncident;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class EmployeeOrganizationResolver
{
    public static function validateHierarchy(Employee $employee): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        $employeeId = (int) ($employee->id ?? 0);
        $companyId = (int) ($employee->company_id ?? 0);

        foreach (['manager_employee_id' => 'jefe directo', 'coach_employee_id' => 'instructor / coach'] as $field => $label) {
            $relatedId = (int) ($employee->{$field} ?? 0);

            if ($relatedId <= 0) {
                continue;
            }

            if ($employeeId > 0 && $relatedId === $employeeId) {
                throw new RuntimeException('El empleado no puede ser su propio ' . $label . '.');
            }

            $related = Employee::query()->find($relatedId);

            if (! $related) {
                throw new RuntimeException('El ' . $label . ' seleccionado no existe.');
            }

            if ($companyId > 0 && (int) $related->company_id !== $companyId) {
                throw new RuntimeException('El ' . $label . ' debe pertenecer a la misma empresa.');
            }
        }

        if ((int) ($employee->manager_employee_id ?? 0) > 0 && self::wouldCreateManagerCycle($employee)) {
            throw new RuntimeException('La jerarquía genera un ciclo. Revisa el jefe directo seleccionado.');
        }
    }

    public static function wouldCreateManagerCycle(Employee $employee): bool
    {
        $employeeId = (int) ($employee->id ?? 0);
        $managerId = (int) ($employee->manager_employee_id ?? 0);

        if ($employeeId <= 0 || $managerId <= 0 || ! Schema::hasTable('employees')) {
            return false;
        }

        $seen = [$employeeId => true];
        $current = $managerId;

        for ($i = 0; $i < 100; $i++) {
            if (isset($seen[$current])) {
                return true;
            }

            $seen[$current] = true;

            $current = (int) DB::table('employees')
                ->where('id', $current)
                ->value('manager_employee_id');

            if ($current <= 0) {
                return false;
            }
        }

        return true;
    }

    public static function approvalManagerUserIdForIncident(EmployeeIncident $incident): ?int
    {
        $employee = $incident->employee ?: Employee::query()->find($incident->employee_id);

        if (! $employee) {
            return null;
        }

        return self::approvalManagerUserIdForEmployee($employee);
    }

    public static function approvalManagerUserIdForEmployee(Employee $employee): ?int
    {
        $manager = $employee->manager ?: (
            $employee->manager_employee_id
                ? Employee::query()->find($employee->manager_employee_id)
                : null
        );

        if ($manager && (int) $manager->company_id === (int) $employee->company_id && (bool) $manager->active) {
            $managerUserId = (int) ($manager->user_id ?? 0);

            if ($managerUserId > 0 && self::userExists($managerUserId)) {
                return $managerUserId;
            }
        }

        $employeeUserId = (int) ($employee->user_id ?? 0);

        if ($employeeUserId > 0 && Schema::hasTable('users') && Schema::hasColumn('users', 'approval_manager_user_id')) {
            $fallbackUserId = (int) DB::table('users')
                ->where('id', $employeeUserId)
                ->value('approval_manager_user_id');

            if ($fallbackUserId > 0 && self::userExists($fallbackUserId)) {
                return $fallbackUserId;
            }
        }

        return null;
    }

    public static function activeEmployeeOptions(?int $companyId = null, ?int $excludeEmployeeId = null): array
    {
        if (! Schema::hasTable('employees')) {
            return [];
        }

        return Employee::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when($excludeEmployeeId, fn ($query) => $query->where('id', '!=', $excludeEmployeeId))
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (Employee $employee): array {
                $label = trim((string) $employee->name);

                if (filled($employee->employee_number)) {
                    $label .= ' · ' . $employee->employee_number;
                }

                if ($employee->relationLoaded('hrJobPosition') && $employee->hrJobPosition) {
                    $label .= ' · ' . $employee->hrJobPosition->name;
                } elseif (filled($employee->position)) {
                    $label .= ' · ' . $employee->position;
                }

                return [$employee->id => $label];
            })
            ->toArray();
    }

    public static function organizationRows(?int $companyId = null): array
    {
        if (! Schema::hasTable('employees')) {
            return [];
        }

        return Employee::query()
            ->with(['manager', 'coach', 'hrDepartment', 'hrJobPosition', 'user'])
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->orderBy('manager_employee_id')
            ->orderBy('name')
            ->get()
            ->map(function (Employee $employee): array {
                $manager = $employee->manager;
                $coach = $employee->coach;

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'active' => (bool) $employee->active,
                    'employee_number' => $employee->employee_number,
                    'department' => $employee->hrDepartment?->name ?: $employee->department,
                    'position' => $employee->hrJobPosition?->name ?: $employee->position,
                    'manager' => $manager?->name,
                    'manager_user' => $manager?->user?->email,
                    'coach' => $coach?->name,
                    'user' => $employee->user?->email,
                ];
            })
            ->toArray();
    }

    protected static function userExists(int $userId): bool
    {
        return Schema::hasTable('users')
            && DB::table('users')->where('id', $userId)->exists();
    }
}
