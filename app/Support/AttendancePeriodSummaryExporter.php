<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class AttendancePeriodSummaryExporter
{
    private static function headers(array $data): array
    {
        return array_merge(
            ['Numero', 'Empleado', 'Horario'],
            array_column($data['days'], 'date'),
            [
                'Asistencias', 'Retardos', 'Faltas por validar',
                'Incompletos', 'Vacaciones',
                'Permisos con goce', 'Permisos sin goce',
                'Incapacidades', 'Flexible sin marcaje',
            ]
        );
    }

    private static function values(array $employee): array
    {
        $c = $employee['counts'];

        return array_merge(
            [
                (string) ($employee['number'] ?? ''),
                (string) $employee['name'],
                (string) $employee['schedule'],
            ],
            array_column($employee['days'], 'code'),
            [
                $c['A'] + $c['R'],
                $c['R'],
                $c['F?'],
                $c['I'],
                $c['V'],
                $c['P'],
                $c['PSG'],
                $c['INC'],
                $c['FL'],
            ]
        );
    }

    private static function metadata(array $meta): array
    {
        return [
            ['Empresa', $meta['company']],
            ['Nomina', $meta['run']],
            ['Grupo', $meta['group']],
            ['Desde', $meta['from']],
            ['Hasta', $meta['to']],
            ['Generado', now()->format('d/m/Y H:i:s')],
            ['Nota', 'Informativo: no genera incidencias ni descuentos'],
        ];
    }

    private static function observations(array $data): array
    {
        $result = [];

        foreach ($data['rows'] as $employee) {
            foreach ($employee['days'] as $day) {
                if (
                    !in_array(
                        $day['code'],
                        ['R', 'I', 'F?', 'PP', 'RH', 'V', 'INC', 'P', 'PSG'],
                        true
                    )
                    && empty($day['has_incident'])
                ) {
                    continue;
                }

                $result[] = [
                    (string) ($employee['number'] ?? ''),
                    (string) $employee['name'],
                    (string) $day['date'],
                    (string) $day['code'],
                    !empty($day['has_incident'])
                        ? 'Existe incidencia registrada; verificar detalle'
                        : 'Verificar clasificacion',
                    (string) ($day['incident_status'] ?? ''),
                ];
            }
        }

        return $result;
    }

    public static function xlsx(array $data, array $meta): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rrhh_sum_');

        if ($path === false) {
            throw new \RuntimeException('No se pudo crear archivo temporal.');
        }

        $writer = new Writer();
        $opened = false;

        try {
            $writer->openToFile($path);
            $opened = true;

            $writer->getCurrentSheet()->setName('Resumen');

            foreach (self::metadata($meta) as $pair) {
                $writer->addRow(Row::fromValues($pair));
            }

            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(self::headers($data)));

            foreach ($data['rows'] as $employee) {
                $writer->addRow(Row::fromValues(self::values($employee)));
            }

            $writer->addNewSheetAndMakeItCurrent()
                ->setName('Observaciones');

            $writer->addRow(Row::fromValues([
                'Numero', 'Empleado', 'Fecha', 'Codigo',
                'Observacion', 'Estado',
            ]));

            foreach (self::observations($data) as $row) {
                $writer->addRow(Row::fromValues($row));
            }

            $writer->close();
            $opened = false;

            $bytes = file_get_contents($path);

            if ($bytes === false || substr($bytes, 0, 2) !== 'PK') {
                throw new \RuntimeException('El archivo XLSX no es valido.');
            }

            return $bytes;
        } finally {
            if ($opened) {
                try {
                    $writer->close();
                } catch (\Throwable) {
                }
            }

            @unlink($path);
        }
    }

    public static function pdf(array $data, array $meta): string
    {
        $escape = static fn ($value): string => htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $html = '<!doctype html><html><head>';
        $html .= '<meta charset="utf-8"><style>';
        $html .= '@page {margin:18px;}';
        $html .= 'body{font-family:DejaVu Sans,sans-serif;font-size:7px;}';
        $html .= 'table{width:100%;border-collapse:collapse;}';
        $html .= 'th,td{border:1px solid #bbb;padding:3px;}';
        $html .= 'th{background:#e5e7eb;}';
        $html .= 'tr{page-break-inside:avoid;}';
        $html .= '</style></head><body>';
        $html .= '<h2>Resumen de asistencia</h2><p>';

        foreach (self::metadata($meta) as $pair) {
            $html .= '<strong>' . $escape($pair[0]) . ':</strong> ';
            $html .= $escape($pair[1]) . ' &nbsp; ';
        }

        $html .= '</p><table><thead><tr>';

        foreach (self::headers($data) as $header) {
            $html .= '<th>' . $escape($header) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($data['rows'] as $employee) {
            $html .= '<tr>';

            foreach (self::values($employee) as $value) {
                $html .= '<td>' . $escape($value) . '</td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<p>F? = posible falta por validar. ';
        $html .= 'Las horas extra registradas no son ';
        $html .= 'autorizacion de pago.</p></body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a3', 'landscape');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }
}
