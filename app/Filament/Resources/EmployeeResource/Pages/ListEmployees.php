<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_employees_csv')
                ->label('Descargar empleados (Excel)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool =>
                    EmployeeResource::canViewAny()
                    && EmployeeResource::canCreate()
                    && Filament::getTenant() !== null
                )
                ->action(function () {
                    abort_unless(
                        EmployeeResource::canViewAny()
                        && EmployeeResource::canCreate(),
                        403
                    );

                    $tenant = Filament::getTenant();
                    abort_unless($tenant !== null, 403);
                    $companyId = $tenant->getKey();

                    $employees = $this->getFilteredTableQuery()
                        ->where('employees.company_id', $companyId)
                        ->with([
                            'branch', 'hrDepartment', 'hrJobPosition',
                            'manager', 'hrWorkSchedule', 'payrollPeriodicity',
                        ])
                        ->reorder('employees.id')
                        ->get();

                    $companyName = (string) $tenant->name;
                    $filename = 'empleados_empresa_'.$companyId.'_'
                        .now()->format('Ymd_His').'.xlsx';

                    return \App\Support\EmployeeXlsxDownload::make(
                        function () use ($employees, $companyId, $companyName): void {
                            $out = fopen('php://output', 'wb');
                            if ($out === false) {
                                throw new \RuntimeException('No se pudo abrir la descarga.');
                            }
                            fwrite($out, "\xEF\xBB\xBF");

                            $write = static function (array $row) use ($out): void {
                                $row = array_map(static function ($value): string {
                                    $text = (string) ($value ?? '');
                                    return preg_match('/^[\s]*[=+\-@]/u', $text)
                                        ? "'".$text
                                        : $text;
                                }, $row);
                                if (fputcsv($out, $row, ',', '"', '') === false) {
                                    throw new \RuntimeException('Error al generar el CSV.');
                                }
                            };

                            // No mostrar etiquetas de relaciones de otra empresa.
                            $label = static function ($related) use ($companyId): string {
                                if (!$related) {
                                    return '';
                                }
                                return (string) $related->company_id === (string) $companyId
                                    ? (string) $related->name
                                    : '';
                            };

                            $write([
                                'ID empleado', 'ID empresa', 'Empresa',
                                'Numero empleado',
                                'Nombre(s)',
                                'Apellido paterno',
                                'Apellido materno',
                                'Nombre completo',
                                'Activo',
                                'Ubicacion', 'Departamento RRHH',
                                'Departamento anterior', 'Puesto RRHH',
                                'Puesto anterior', 'Jefe directo',
                                'Correo trabajo', 'Telefono trabajo', 'Movil trabajo',
                                'Fecha ingreso', 'Fecha baja',
                                'Horario RRHH', 'Horario anterior', 'Zona horaria',
                                'Horas flexibles', 'Periodicidad registrada',
                                'RFC', 'CURP', 'NSS', 'Nombre fiscal', 'CP fiscal',
                                'Foto registrada', 'QR habilitado',
                                'Token QR disponible', 'Observaciones para actualizar',
                            ]);

                            foreach ($employees as $e) {
                                $write([
                                    $e->id, $e->company_id, $companyName,
                                    $e->employee_number,
                                    $e->first_name,
                                    $e->paternal_surname,
                                    $e->maternal_surname,
                                    $e->name,
                                    $e->active ? 'Si' : 'No',
                                    $label($e->branch), $label($e->hrDepartment),
                                    $e->department, $label($e->hrJobPosition),
                                    $e->position, $label($e->manager),
                                    $e->email, $e->phone, $e->work_mobile,
                                    $e->hire_date?->format('Y-m-d'),
                                    $e->termination_date?->format('Y-m-d'),
                                    $label($e->hrWorkSchedule), $e->working_schedule,
                                    $e->work_timezone,
                                    $e->flexible_hours ? 'Si' : 'No',
                                    $label($e->payrollPeriodicity),
                                    $e->rfc, $e->curp,
                                    $e->social_security_number ?: $e->ssn,
                                    $e->fiscal_name, $e->fiscal_postal_code,
                                    filled($e->avatar_path) ? 'Si' : 'No',
                                    $e->attendance_qr_enabled ? 'Si' : 'No',
                                    filled($e->attendance_qr_token) ? 'Si' : 'No',
                                    '',
                                ]);
                            }
                            fclose($out);
                        },
                        $filename,
                        [
                            'Content-Type' => 'text/csv; charset=UTF-8',
                            'Cache-Control' => 'private, no-store',
                        ]
                    );
                }),
            Actions\CreateAction::make(),
        ];
    }
}
