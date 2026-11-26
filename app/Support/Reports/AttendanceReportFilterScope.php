<?php

declare(strict_types=1);

namespace App\Support\Reports;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AttendanceReportFilterScope
{
    public function validate(
        int $companyId,
        array $filters
    ): void {

        if (
            $companyId < 1
            || (int) ($filters['company_id'] ?? 0)
                !== $companyId
        ) {
            throw ValidationException::withMessages([
                'company_id' => 'Empresa no autorizada.',
            ]);
        }

        $employeeIds =
            $filters['employee_ids'] ?? [];

        if ($employeeIds !== []) {

            $count = DB::table('employees')
                ->where('company_id', $companyId)
                ->whereIn('id', $employeeIds)
                ->count();

            if ($count !== count($employeeIds)) {

                throw ValidationException::withMessages([
                    'employee_ids' =>
                        'La seleccion contiene empleados '
                        .'que no pertenecen a esta empresa.',
                ]);
            }
        }

        $departmentId =
            $filters['department_id'] ?? null;

        if ($departmentId !== null) {

            $exists = DB::table('hr_departments')
                ->where('company_id', $companyId)
                ->where('id', $departmentId)
                ->exists();

            if (!$exists) {

                throw ValidationException::withMessages([
                    'department_id' =>
                        'Departamento no autorizado.',
                ]);
            }
        }
    }
}
