<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayrollAttendanceReviewerResolver
{
    /**
     * Resuelve un posible responsable.
     *
     * No concede permisos de aprobacion.
     * Los vistos buenos deberan validar adicionalmente
     * el permiso especifico y el alcance del periodo.
     */
    public static function resolve(
        int $companyId,
        int $employeeId
    ): array {
        $pending = [
            'employee_id' => $employeeId,
            'company_id' => $companyId,
            'reviewer_user_id' => null,
            'source' => 'unassigned',
            'reason' => 'Sin responsable de validacion',
        ];

        if ($companyId <= 0 || $employeeId <= 0) {
            return $pending;
        }

        $company = Company::query()->find($companyId);

        if (!$company) {
            return [
                ...$pending,
                'reason' => 'Empresa no encontrada',
            ];
        }

        $fields = ['id', 'hr_department_id'];

        if (Schema::hasColumn(
            'employees',
            'payroll_attendance_reviewer_user_id'
        )) {
            $fields[] = 'payroll_attendance_reviewer_user_id';
        }

        $employee = DB::table('employees')
            ->where('company_id', $companyId)
            ->where('id', $employeeId)
            ->first($fields);

        if (!$employee) {
            return [
                ...$pending,
                'reason' => 'Empleado no encontrado en la empresa',
            ];
        }

        $overrideId = (int) (
            $employee->payroll_attendance_reviewer_user_id ?? 0
        );

        if ($overrideId > 0) {
            return self::eligible(
                $company,
                $overrideId,
                $pending,
                'employee_exception'
            );
        }

        if (!$employee->hr_department_id) {
            return [
                ...$pending,
                'reason' => 'Empleado sin departamento',
            ];
        }

        $department = DB::table('hr_departments')
            ->where('company_id', $companyId)
            ->where('id', $employee->hr_department_id)
            ->where('is_active', true)
            ->first(['id', 'manager_employee_id']);

        if (!$department || !$department->manager_employee_id) {
            return [
                ...$pending,
                'reason' => 'Departamento sin responsable activo',
            ];
        }

        $manager = DB::table('employees')
            ->where('company_id', $companyId)
            ->where('id', $department->manager_employee_id)
            ->where('active', true)
            ->first(['id', 'user_id']);

        if (!$manager || !$manager->user_id) {
            return [
                ...$pending,
                'reason' => 'Responsable sin usuario vinculado',
            ];
        }

        return self::eligible(
            $company,
            (int) $manager->user_id,
            $pending,
            'department_manager'
        );
    }

    private static function eligible(
        Company $company,
        int $userId,
        array $pending,
        string $source
    ): array {
        $user = User::query()->find($userId);

        if (!$user || !$user->canAccessTenant($company)) {
            return [
                ...$pending,
                'reason' =>
                    'El responsable no tiene acceso a esta empresa',
            ];
        }

        return [
            ...$pending,
            'reviewer_user_id' => $userId,
            'source' => $source,
            'reason' => null,
        ];
    }
}
