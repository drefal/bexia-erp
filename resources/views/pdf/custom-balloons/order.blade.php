<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 12pt; }
body {
    font-family: DejaVu Sans, sans-serif;
    color: #161616;
    font-size: 8pt;
    margin: 0;
}
.layout {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}
.layout > tbody > tr > td {
    vertical-align: top;
    padding: 0 7pt;
}
.layout .left-copy {
    border-right: 1pt dashed #888;
}
.page-copy {
    border: 1pt solid #333;
    padding: 10pt;
}
.header {
    width: 100%;
    border-bottom: 1pt solid #aaa;
    padding-bottom: 7pt;
    margin-bottom: 9pt;
}
.logo {
    max-width: 105pt;
    max-height: 39pt;
}
.brand {
    font-weight: bold;
    font-size: 18pt;
}
.copy-title {
    font-size: 10pt;
    font-weight: bold;
    text-align: right;
}
.folio {
    font-size: 8pt;
    text-align: right;
}
.section-title {
    font-weight: bold;
    font-size: 10pt;
    margin: 8pt 0 5pt;
}
.info-table {
    width: 100%;
    table-layout: fixed;
    border-collapse: separate;
    border-spacing: 4pt;
}
.info-table td {
    border: 1pt solid #777;
    padding: 5pt;
    vertical-align: top;
}
.info-label {
    font-weight: bold;
    margin-bottom: 4pt;
}
.line {
    width: 100%;
    border-bottom: 0.4pt solid #ddd;
    border-collapse: collapse;
    page-break-inside: avoid;
}
.line td {
    padding: 5pt 2pt;
    vertical-align: top;
}
.photo {
    max-height: 45pt;
    max-width: 38pt;
}
.photo-cell {
    width: 42pt;
}
.money {
    text-align: right;
    white-space: nowrap;
}
.summary {
    width: 100%;
    border-top: 1pt solid #bbb;
    margin-top: 10pt;
    padding-top: 6pt;
}
.summary td {
    padding: 3pt 1pt;
}
.disclaimer {
    margin-top: 12pt;
    font-size: 6.5pt;
    text-align: center;
    color: #555;
}
.signature {
    margin-top: 25pt;
    font-size: 8pt;
}
.muted {
    color: #555;
    font-size: 7pt;
}
.note {
    margin-top: 7pt;
    white-space: pre-line;
}
</style>
</head>
<body>
@if ($double)
    <table class="layout">
        <tr>
            <td class="left-copy" style="width:50%;">
                @include('pdf.custom-balloons.copy', [
                    'copyTitle' => 'ENTREGA'
                ])
            </td>
            <td style="width:50%;">
                @include('pdf.custom-balloons.copy', [
                    'copyTitle' => 'ARCHIVO'
                ])
            </td>
        </tr>
    </table>
@else
    <div style="max-width:570pt;margin:auto;">
        @include('pdf.custom-balloons.copy', [
            'copyTitle' => 'ENTREGA'
        ])
    </div>
@endif
</body>
</html>
