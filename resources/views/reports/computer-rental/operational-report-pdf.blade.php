<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <title>Reporte de Renta de equipos</title>

    <style>
        @page {
            margin: 18px 20px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            color: #111827;
        }

        .header {
            width: 100%;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .logo-cell {
            width: 36%;
            vertical-align: top;
        }

        .doc-cell {
            width: 64%;
            vertical-align: top;
            text-align: right;
        }

        .logo {
            max-width: 125px;
            max-height: 48px;
        }

        .company-name {
            margin-top: 5px;
            font-size: 9px;
            font-weight: bold;
        }

        h1 {
            margin: 0 0 4px;
            font-size: 17px;
        }

        h2 {
            margin: 15px 0 6px;
            font-size: 11px;
        }

        .muted {
            color: #6b7280;
        }

        .summary {
            width: 100%;
            margin-top: 10px;
            border-collapse: separate;
            border-spacing: 4px;
        }

        .summary td {
            border: 1px solid #d1d5db;
            padding: 7px;
            vertical-align: top;
        }

        .summary-label {
            font-size: 7px;
            text-transform: uppercase;
            color: #6b7280;
        }

        .summary-value {
            margin-top: 2px;
            font-size: 12px;
            font-weight: bold;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data th,
        table.data td {
            border: 1px solid #d1d5db;
            padding: 4px;
            vertical-align: top;
        }

        table.data th {
            background: #f3f4f6;
            font-weight: bold;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .nowrap {
            white-space: nowrap;
        }

        .page-break {
            page-break-before: always;
        }
    </style>
</head>

<body>
@php
    $fmtDateTime = static function ($value): string {
        if (! $value) {
            return '—';
        }

        try {
            return \Carbon\Carbon::parse($value)
                ->format('d/m/Y H:i');
        } catch (\Throwable) {
            return '—';
        }
    };

    $fmtDuration = static function ($seconds): string {
        $seconds = max(
            0,
            (int) ($seconds ?? 0)
        );

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv(
            $seconds % 3600,
            60
        );

        if ($hours > 0) {
            return sprintf(
                '%dh %02dm',
                $hours,
                $minutes
            );
        }

        return sprintf(
            '%dm',
            $minutes
        );
    };

    $statusLabel = static function ($status): string {
        return \App\Support\ComputerRental\ComputerRentalReportService
            ::statusLabel($status);
    };

    $billingLabel = static function ($mode): string {
        return \App\Support\ComputerRental\ComputerRentalReportService
            ::billingModeLabel($mode);
    };
@endphp

{{-- CIBER3R4B_COMPANY_HEADER --}}
<div class="header">
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @if (! empty($logoDataUri))
                    <img
                        class="logo"
                        src="{{ $logoDataUri }}"
                        alt="Logo"
                    >
                @endif

                <div class="company-name">
                    {{ $company->name ?? 'Empresa' }}
                </div>

                @if (! empty($company->tax_id))
                    <div class="muted">
                        RFC: {{ $company->tax_id }}
                    </div>
                @endif
            </td>

            <td class="doc-cell">
                <h1>
                    Reporte de Renta de equipos
                </h1>

                <div class="muted">
                    Periodo:
                    {{ $filters['from'] ?? '—' }}
                    a
                    {{ $filters['to'] ?? '—' }}
                </div>

                <div class="muted">
                    Generado:
                    {{ now()->format('d/m/Y H:i') }}
                </div>
            </td>
        </tr>
    </table>
</div>

<table class="summary">
    <tr>
        <td>
            <div class="summary-label">Sesiones</div>
            <div class="summary-value">
                {{ $summary['sessions'] }}
            </div>
        </td>

        <td>
            <div class="summary-label">Pagadas</div>
            <div class="summary-value">
                {{ $summary['paid'] }}
            </div>
        </td>

        <td>
            <div class="summary-label">Canceladas</div>
            <div class="summary-value">
                {{ $summary['cancelled'] }}
            </div>
        </td>

        <td>
            <div class="summary-label">Horas ocupadas</div>
            <div class="summary-value">
                {{ number_format($summary['real_hours'], 2) }}
            </div>
        </td>

        <td>
            <div class="summary-label">Horas facturables</div>
            <div class="summary-value">
                {{ number_format($summary['billable_hours'], 2) }}
            </div>
        </td>

        <td>
            <div class="summary-label">Renta</div>
            <div class="summary-value">
                ${{ number_format($summary['rental_revenue'], 2) }}
            </div>
        </td>

        <td>
            <div class="summary-label">Consumos</div>
            <div class="summary-value">
                ${{ number_format($summary['consumption_revenue'], 2) }}
            </div>
        </td>

        <td>
            <div class="summary-label">Renta + consumos</div>
            <div class="summary-value">
                ${{ number_format($summary['rental_plus_consumption'], 2) }}
            </div>
        </td>
    </tr>
</table>

<h2>Operación por PC</h2>

<table class="data">
    <thead>
        <tr>
            <th>PC</th>
            <th class="right">Sesiones</th>
            <th class="right">Pagadas</th>
            <th class="right">Canceladas</th>
            <th class="right">H. ocupadas</th>
            <th class="right">H. facturables</th>
            <th class="right">Participación</th>
            <th class="right">Renta</th>
            <th class="right">Consumos</th>
        </tr>
    </thead>

    <tbody>
        @forelse ($stationSummary as $station)
            <tr>
                <td>
                    <strong>
                        {{ $station['station_code'] }}
                    </strong>

                    @if ($station['station_name'])
                        · {{ $station['station_name'] }}
                    @endif
                </td>

                <td class="right">
                    {{ $station['sessions'] }}
                </td>

                <td class="right">
                    {{ $station['paid'] }}
                </td>

                <td class="right">
                    {{ $station['cancelled'] }}
                </td>

                <td class="right">
                    {{ number_format($station['real_hours'], 2) }}
                </td>

                <td class="right">
                    {{ number_format($station['billable_hours'], 2) }}
                </td>

                <td class="right">
                    {{ number_format($station['share_percent'], 1) }}%
                </td>

                <td class="right">
                    ${{ number_format($station['rental_revenue'], 2) }}
                </td>

                <td class="right">
                    ${{ number_format($station['consumption_revenue'], 2) }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="center">
                    Sin información.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

<h2>Detalle de sesiones</h2>

<table class="data">
    <thead>
        <tr>
            <th>PC</th>
            <th>Inicio</th>
            <th>Fin</th>
            <th>Usuario</th>
            <th>Tarifa</th>
            <th>Tipo</th>
            <th class="right">Real</th>
            <th class="right">Fact.</th>
            <th class="right">Renta</th>
            <th class="right">Cons.</th>
            <th>Ticket</th>
            <th class="right">Total</th>
            <th>Estado</th>
            <th>Cancelación</th>
        </tr>
    </thead>

    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td class="nowrap">
                    {{ $row->station_code ?: '—' }}
                </td>

                <td class="nowrap">
                    {{ $fmtDateTime($row->started_at) }}
                </td>

                <td class="nowrap">
                    {{ $fmtDateTime($row->ended_at) }}
                </td>

                <td>
                    {{ $row->opened_by_name ?: '—' }}
                </td>

                <td>
                    {{ $row->rate_name ?: '—' }}
                </td>

                <td>
                    {{ $billingLabel($row->billing_mode) }}
                </td>

                <td class="right nowrap">
                    {{ $fmtDuration($row->duration_seconds) }}
                </td>

                <td class="right nowrap">
                    {{ (int) $row->billable_minutes }} min
                </td>

                <td class="right nowrap">
                    ${{ number_format((float) $row->rental_amount, 2) }}
                </td>

                <td class="right nowrap">
                    ${{ number_format((float) $row->consumption_total, 2) }}
                </td>

                <td class="nowrap">
                    {{ $row->pos_number ?: '—' }}
                </td>

                <td class="right nowrap">
                    @if ($row->pos_order_id)
                        ${{ number_format((float) $row->pos_total, 2) }}
                    @else
                        —
                    @endif
                </td>

                <td class="nowrap">
                    {{ $statusLabel($row->rental_status) }}
                </td>

                <td>
                    @if ($row->cancelled_at)
                        {{ $fmtDateTime($row->cancelled_at) }}

                        @if ($row->cancel_reason)
                            · {{ $row->cancel_reason }}
                        @endif
                    @else
                        —
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="14" class="center">
                    No hay sesiones para este periodo.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

<div
    class="muted"
    style="margin-top: 10px;"
>
    El tiempo ocupado representa duración real de uso.
    El tiempo facturable corresponde a la regla comercial aplicada.
</div>

</body>
</html>
