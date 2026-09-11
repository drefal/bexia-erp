<?php

namespace App\Support;

final class EmployeeXlsxDownload
{
    public static function make(
        callable $callback,
        string $filename,
        array $headers = []
    ) {
        // Reutiliza las filas y controles de acceso del exportador existente.
        $level = ob_get_level();
        ob_start();
        try {
            $callback();
            $csv = ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }

        if (str_starts_with($csv, "\xEF\xBB\xBF")) {
            $csv = substr($csv, 3);
        }

        $input = fopen('php://temp', 'w+b');
        if ($input === false) {
            throw new \RuntimeException('No se pudo preparar el Excel.');
        }

        try {
            fwrite($input, $csv);
            rewind($input);
            $rows = [];
            while (($row = fgetcsv($input, null, ',', '"', '')) !== false) {
                $rows[] = $row;
            }
        } finally {
            fclose($input);
        }

        if (!$rows || !$rows[0]) {
            throw new \RuntimeException('No hay encabezados para exportar.');
        }

        $xml = static function ($value): string {
            $text = preg_replace(
                '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
                '',
                (string) ($value ?? '')
            );
            return htmlspecialchars(
                $text ?? '',
                ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        };

        $column = static function (int $number): string {
            $name = '';
            while ($number > 0) {
                $number--;
                $name = chr(65 + $number % 26).$name;
                $number = intdiv($number, 26);
            }
            return $name;
        };

        $last = $column(count($rows[0])).count($rows);
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:'.$last.'"/>'
            .'<sheetViews><sheetView workbookViewId="0">'
            .'<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            .'</sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="20"/>'
            .'<cols>';

        foreach ($rows[0] as $index => $heading) {
            $width = match ($heading) {
                'Nombre', 'Jefe directo', 'Nombre fiscal' => 38,
                'Empresa', 'Correo trabajo' => 32,
                'Observaciones para actualizar' => 45,
                default => 24,
            };
            $n = $index + 1;
            $sheet .= '<col min="'.$n.'" max="'.$n
                .'" width="'.$width.'" customWidth="1"/>';
        }

        $sheet .= '</cols><sheetData>';

        foreach ($rows as $index => $row) {
            $n = $index + 1;
            $sheet .= '<row r="'.$n.'"'
                .($index === 0 ? ' ht="32" customHeight="1"' : '').'>';
            foreach ($row as $c => $value) {
                // inlineStr conserva ceros iniciales y evita fórmulas.
                $sheet .= '<c r="'.$column($c + 1).$n
                    .'" s="'.($index === 0 ? '1' : '0')
                    .'" t="inlineStr"><is><t xml:space="preserve">'
                    .$xml($value).'</t></is></c>';
            }
            $sheet .= '</row>';
        }

        $sheet .= '</sheetData><autoFilter ref="A1:'.$last.'"/></worksheet>';

        $files = [
            '[Content_Types].xml' =>
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                .'</Types>',
            '_rels/.rels' =>
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',
            'xl/workbook.xml' =>
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="Empleados" sheetId="1" r:id="rId1"/></sheets>'
                .'</workbook>',
            'xl/_rels/workbook.xml.rels' =>
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                .'</Relationships>',
            'xl/styles.xml' =>
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<fonts count="2">'
                .'<font><sz val="11"/><name val="Calibri"/></font>'
                .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
                .'</fonts>'
                .'<fills count="3">'
                .'<fill><patternFill patternType="none"/></fill>'
                .'<fill><patternFill patternType="gray125"/></fill>'
                .'<fill><patternFill patternType="solid"><fgColor rgb="FF174784"/><bgColor indexed="64"/></patternFill></fill>'
                .'</fills>'
                .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                .'<cellXfs count="2">'
                .'<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
                .'<xf numFmtId="49" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
                .'</cellXfs>'
                .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
                .'</styleSheet>',
            'xl/worksheets/sheet1.xml' => $sheet,
        ];

        $path = tempnam(sys_get_temp_dir(), 'bexia_empleados_');
        if ($path === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal.');
        }

        try {
            $zip = new \ZipArchive();
            if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('No se pudo crear el XLSX.');
            }
            try {
                foreach ($files as $name => $content) {
                    if (!$zip->addFromString($name, $content)) {
                        throw new \RuntimeException('Error al escribir el XLSX.');
                    }
                }
            } finally {
                if (!$zip->close()) {
                    throw new \RuntimeException('No se pudo finalizar el XLSX.');
                }
            }

            return response()->download(
                $path,
                $filename,
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'private, no-store',
                ]
            )->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            @unlink($path);
            throw $e;
        }
    }
}
