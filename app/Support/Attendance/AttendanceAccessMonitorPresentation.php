<?php

declare(strict_types=1);

namespace App\Support\Attendance;

final class AttendanceAccessMonitorPresentation
{
    public static function present(?object $decision): array
    {
        if ($decision === null) {
            return [
                'access_status' => 'not_evaluated',
                'access_label' => 'SIN EVALUAR',
                'access_reason' =>
                    'Sin decision registrada. Verificar ingreso.',
            ];
        }

        $status = (string) $decision->status;

        $labels = [
            'authorized' => 'AUTORIZADO',
            'hold' => 'RETENER',
            'denied' => 'DENEGADO',
            'review' => 'REVISAR',
            'not_evaluated' => 'SIN EVALUAR',
        ];

        $reasons = [
            'authorized' => [
                'immediate_authorization' =>
                    'Ingreso autorizado por responsable',
                'on_time' =>
                    'Entrada dentro del horario permitido',
                'within_tolerance' =>
                    'Dentro de la tolerancia',
                'prior_authorization' =>
                    'Autorizacion anticipada vigente al ingreso',
                'flexible_schedule' =>
                    'Horario flexible',
                'late_without_access_restriction' =>
                    'Retardo permitido por politica de acceso',
                'rest_day_without_access_restriction' =>
                    'Ingreso permitido en dia de descanso',
            ],

            'hold' => [
                'late_requires_authorization' =>
                    'Retardo pendiente de autorizacion',
                'non_working_day' =>
                    'Ingreso en dia de descanso',
                'approved_absence' =>
                    'Ingreso pendiente de revision',
                'temporary_restriction' =>
                    'Restriccion temporal de acceso',
                'outside_working_hours' =>
                    'Fuera del horario permitido',
                'early_arrival_outside_limit' =>
                    'Entrada anticipada fuera del limite',
            ],

            'denied' => [
                'immediate_rejection' =>
                    'Ingreso rechazado por responsable',
                'employee_inactive' =>
                    'Empleado no habilitado',
                'explicit_restriction' =>
                    'Restriccion expresa de acceso',
            ],

            'review' => [
                'missing_schedule' =>
                    'Horario no disponible',
                'missing_start_time' =>
                    'Horario incompleto',
                'unknown_schedule_type' =>
                    'Horario pendiente de revision',
                'invalid_schedule_interval' =>
                    'Configuracion de horario incorrecta',
                'invalid_working_day_configuration' =>
                    'Configuracion de jornada incorrecta',
                'invalid_entry_time' =>
                    'Hora de entrada pendiente de revision',
                'absence_context_not_available' =>
                    'Informacion de acceso incompleta',
            ],

            'not_evaluated' => [
                'access_control_disabled' =>
                    'Control de acceso desactivado',
            ],
        ];

        $code = (string) $decision->reason_code;

        // Do not interpret unknown states or reasons
        // as an authorized entry.
        if (
            !isset($labels[$status])
            || !isset($reasons[$status][$code])
        ) {
            return [
                'access_status' => 'review',
                'access_label' => 'REVISAR',
                'access_reason' =>
                    'Decision pendiente de verificacion',
            ];
        }

        return [
            'access_status' => $status,
            'access_label' => $labels[$status],
            'access_reason' => $reasons[$status][$code],
        ];
    }
}
