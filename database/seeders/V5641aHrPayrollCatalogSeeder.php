<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V5641aHrPayrollCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        $companies = DB::table('companies')->get(['id']);

        foreach ($companies as $company) {
            $companyId = (int) $company->id;

            foreach ([
                ['name' => 'Administración', 'code' => 'ADMIN'],
                ['name' => 'Ventas', 'code' => 'VENTAS'],
                ['name' => 'Operaciones', 'code' => 'OPER'],
                ['name' => 'Recursos Humanos', 'code' => 'RRHH'],
            ] as $row) {
                DB::table('hr_departments')->updateOrInsert(
                    ['company_id' => $companyId, 'name' => $row['name']],
                    [
                        'code' => $row['code'],
                        'is_active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            foreach ([
                ['name' => 'Gerente', 'code' => 'GER'],
                ['name' => 'Coordinador', 'code' => 'COORD'],
                ['name' => 'Auxiliar', 'code' => 'AUX'],
                ['name' => 'Vendedor', 'code' => 'VEND'],
                ['name' => 'Cajero', 'code' => 'CAJ'],
            ] as $row) {
                DB::table('hr_job_positions')->updateOrInsert(
                    ['company_id' => $companyId, 'name' => $row['name']],
                    [
                        'code' => $row['code'],
                        'is_active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            foreach ([
                ['name' => 'INE', 'code' => 'INE', 'requires_expiration_date' => true, 'is_required_by_default' => true],
                ['name' => 'CURP', 'code' => 'CURP', 'requires_expiration_date' => false, 'is_required_by_default' => true],
                ['name' => 'RFC / Constancia fiscal', 'code' => 'RFC', 'requires_expiration_date' => false, 'is_required_by_default' => true],
                ['name' => 'Comprobante de domicilio', 'code' => 'DOMICILIO', 'requires_expiration_date' => false, 'is_required_by_default' => true],
                ['name' => 'Contrato', 'code' => 'CONTRATO', 'requires_expiration_date' => true, 'is_required_by_default' => true],
                ['name' => 'Alta IMSS', 'code' => 'IMSS', 'requires_expiration_date' => false, 'is_required_by_default' => false],
            ] as $row) {
                DB::table('hr_document_types')->updateOrInsert(
                    ['company_id' => $companyId, 'name' => $row['name']],
                    [
                        'code' => $row['code'],
                        'requires_expiration_date' => $row['requires_expiration_date'],
                        'is_required_by_default' => $row['is_required_by_default'],
                        'is_active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            foreach ([
                ['name' => 'Retardo', 'code' => 'RETARDO', 'effect' => 'deduction', 'requires_approval' => true, 'affects_payroll' => true],
                ['name' => 'Falta', 'code' => 'FALTA', 'effect' => 'deduction', 'requires_approval' => true, 'affects_payroll' => true],
                ['name' => 'Permiso con goce', 'code' => 'PERMISO_CON_GOCE', 'effect' => 'informational', 'requires_approval' => true, 'affects_payroll' => false],
                ['name' => 'Permiso sin goce', 'code' => 'PERMISO_SIN_GOCE', 'effect' => 'deduction', 'requires_approval' => true, 'affects_payroll' => true],
                ['name' => 'Incapacidad', 'code' => 'INCAPACIDAD', 'effect' => 'informational', 'requires_approval' => true, 'affects_payroll' => true],
                ['name' => 'Bono', 'code' => 'BONO', 'effect' => 'perception', 'requires_approval' => true, 'affects_payroll' => true],
                ['name' => 'Descuento', 'code' => 'DESCUENTO', 'effect' => 'deduction', 'requires_approval' => true, 'affects_payroll' => true],
            ] as $row) {
                DB::table('hr_incident_types')->updateOrInsert(
                    ['company_id' => $companyId, 'name' => $row['name']],
                    [
                        'code' => $row['code'],
                        'effect' => $row['effect'],
                        'requires_approval' => $row['requires_approval'],
                        'affects_payroll' => $row['affects_payroll'],
                        'is_active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            foreach ([
                ['name' => 'Horario administrativo', 'code' => 'ADMIN', 'schedule_type' => 'fixed', 'start_time' => '09:00', 'end_time' => '18:00', 'hours_per_day' => 8, 'hours_per_week' => 40],
                ['name' => 'Turno matutino', 'code' => 'MAT', 'schedule_type' => 'fixed', 'start_time' => '08:00', 'end_time' => '16:00', 'hours_per_day' => 8, 'hours_per_week' => 48],
                ['name' => 'Turno vespertino', 'code' => 'VES', 'schedule_type' => 'fixed', 'start_time' => '14:00', 'end_time' => '22:00', 'hours_per_day' => 8, 'hours_per_week' => 48],
            ] as $row) {
                DB::table('hr_work_schedules')->updateOrInsert(
                    ['company_id' => $companyId, 'name' => $row['name']],
                    [
                        'code' => $row['code'],
                        'schedule_type' => $row['schedule_type'],
                        'start_time' => $row['start_time'],
                        'end_time' => $row['end_time'],
                        'work_days' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
                        'hours_per_day' => $row['hours_per_day'],
                        'hours_per_week' => $row['hours_per_week'],
                        'is_active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            foreach ([
                ['name' => 'Semanal', 'sat_code' => '02', 'days' => 7],
                ['name' => 'Catorcenal', 'sat_code' => '03', 'days' => 14],
                ['name' => 'Quincenal', 'sat_code' => '04', 'days' => 15],
                ['name' => 'Mensual', 'sat_code' => '05', 'days' => 30],
            ] as $row) {
                DB::table('payroll_periodicities')->updateOrInsert(
                    ['company_id' => $companyId, 'name' => $row['name']],
                    [
                        'sat_code' => $row['sat_code'],
                        'days' => $row['days'],
                        'is_active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}
