<?php

namespace App\Support;

/**
 * BEXIA_RRHH_ASIS03
 *
 * Presentacion visual de estados del reporte.
 * No modifica estados, incidencias ni calculos.
 */
final class AttendanceReportStatusPresentation
{
    public static function all(): array
    {
        return [
            'present' => [
                'label' => 'Asistencia correcta',
                'color' => '#15803d',
                'shape' =>
                    '<circle cx="12" cy="12" r="9"/>'
                    .'<path d="m8 12 3 3 5-6"/>',
            ],

            'late' => [
                'label' => 'Retardo',
                'color' => '#b45309',
                'shape' =>
                    '<circle cx="12" cy="12" r="9"/>'
                    .'<path d="M12 6v6l4 2"/>',
            ],

            'early_leave' => [
                'label' => 'Salida anticipada',
                'color' => '#b45309',
                'shape' =>
                    '<path d="M10 4H5a1 1 0 0 0-1 1v14'
                    .'a1 1 0 0 0 1 1h5"/>'
                    .'<path d="M9 12h11m-4-4 4 4-4 4"/>',
            ],

            'late_early_leave' => [
                'label' => 'Retardo y salida anticipada',
                'color' => '#b45309',
                'shape' =>
                    '<path d="M12 3 22 20H2Z"/>'
                    .'<path d="M12 9v5"/>'
                    .'<circle cx="12" cy="17" r=".6"/>',
            ],

            'absence' => [
                'label' => 'Falta',
                'color' => '#dc2626',
                'shape' =>
                    '<circle cx="12" cy="12" r="9"/>'
                    .'<path d="m9 9 6 6m0-6-6 6"/>',
            ],

            'incomplete' => [
                'label' => 'Marcaje incompleto',
                'color' => '#dc2626',
                'shape' =>
                    '<circle cx="12" cy="12" r="9"/>'
                    .'<path d="M12 7v6"/>'
                    .'<circle cx="12" cy="16" r=".6"/>',
            ],

            'rest_day' => [
                'label' => 'Día de descanso',
                'color' => '#64748b',
                'shape' =>
                    '<rect x="3" y="5" width="18" height="16" rx="2"/>'
                    .'<path d="M7 3v4m10-4v4M3 10h18"/>',
            ],

            'rest_day_worked' => [
                'label' => 'Descanso trabajado',
                'color' => '#2563eb',
                'shape' =>
                    '<rect x="3" y="5" width="18" height="16" rx="2"/>'
                    .'<path d="M7 3v4m10-4v4M3 10h18"/>'
                    .'<path d="m8 15 3 3 5-5"/>',
            ],

            'no_schedule' => [
                'label' => 'Sin horario asignado',
                'color' => '#64748b',
                'shape' =>
                    '<circle cx="12" cy="12" r="9"/>'
                    .'<path d="M8 12h8"/>',
            ],
        ];
    }

    public static function item(?string $status): array
    {
        return static::all()[$status] ?? [
            'label' => 'Estado no identificado',
            'color' => '#64748b',
            'shape' =>
                '<circle cx="12" cy="12" r="9"/>'
                .'<path d="M9 9a3 3 0 1 1 4 3l-1 1"/>'
                .'<circle cx="12" cy="17" r=".6"/>',
        ];
    }

    public static function label(?string $status): string
    {
        return static::item($status)['label'];
    }

    public static function svg(?string $status): string
    {
        $item = static::item($status);

        return '<svg xmlns="http://www.w3.org/2000/svg"'
            .' width="24" height="24" viewBox="0 0 24 24"'
            .' fill="none" stroke="'.$item['color'].'"'
            .' stroke-width="1.9" stroke-linecap="round"'
            .' stroke-linejoin="round">'
            .$item['shape']
            .'</svg>';
    }

    public static function image(?string $status): string
    {
        return 'data:image/svg+xml;base64,'
            .base64_encode(static::svg($status));
    }
}
