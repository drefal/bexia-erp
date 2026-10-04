<?php

namespace App\Support\ComputerRental;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ComputerRentalReportService
{
    /**
     * CIBER3R3_REPORT_ROWS
     *
     * Fuente principal:
     * computer_rental_sessions.
     *
     * El ticket PDV se utiliza como complemento y no como
     * fuente primaria de la operación de renta.
     */
    public static function rows(array $filters): Collection
    {
        $companyId = (int) ($filters['company_id'] ?? 0);

        if ($companyId <= 0) {
            return collect();
        }

        $consumptions = DB::table(
            'computer_rental_session_lines'
        )
            ->selectRaw(
                '
                computer_rental_session_id,
                COUNT(*) as consumption_lines,
                COALESCE(SUM(quantity), 0) as consumption_quantity,
                COALESCE(SUM(total), 0) as consumption_total
                '
            )
            ->groupBy(
                'computer_rental_session_id'
            );

        $query = DB::table(
            'computer_rental_sessions as crs'
        )
            ->leftJoin(
                'computer_rental_stations as st',
                'st.id',
                '=',
                'crs.station_id'
            )
            ->leftJoin(
                'computer_rental_rates as rt',
                'rt.id',
                '=',
                'crs.rate_id'
            )
            ->leftJoin(
                'users as opener',
                'opener.id',
                '=',
                'crs.opened_by_user_id'
            )
            ->leftJoin(
                'users as closer',
                'closer.id',
                '=',
                'crs.closed_by_user_id'
            )
            ->leftJoin(
                'pos_orders as po',
                'po.id',
                '=',
                'crs.pos_order_id'
            )
            ->leftJoinSub(
                $consumptions,
                'cons',
                function ($join): void {
                    $join->on(
                        'cons.computer_rental_session_id',
                        '=',
                        'crs.id'
                    );
                }
            )
            ->where(
                'crs.company_id',
                $companyId
            );

        $from = trim(
            (string) ($filters['from'] ?? '')
        );

        $to = trim(
            (string) ($filters['to'] ?? '')
        );

        if ($from !== '') {
            $query->whereDate(
                'crs.started_at',
                '>=',
                $from
            );
        }

        if ($to !== '') {
            $query->whereDate(
                'crs.started_at',
                '<=',
                $to
            );
        }

        $stationId = (int) (
            $filters['station_id']
            ?? 0
        );

        if ($stationId > 0) {
            $query->where(
                'crs.station_id',
                $stationId
            );
        }

        $status = trim(
            (string) ($filters['status'] ?? '')
        );

        if ($status !== '') {
            $query->where(
                'crs.status',
                $status
            );
        }

        $billingMode = trim(
            (string) (
                $filters['billing_mode']
                ?? ''
            )
        );

        if ($billingMode !== '') {
            $query->where(
                'crs.billing_mode',
                $billingMode
            );
        }

        $userId = (int) (
            $filters['user_id']
            ?? 0
        );

        if ($userId > 0) {
            $query->where(
                function ($query) use ($userId): void {
                    $query
                        ->where(
                            'crs.opened_by_user_id',
                            $userId
                        )
                        ->orWhere(
                            'crs.closed_by_user_id',
                            $userId
                        );
                }
            );
        }

        return $query
            ->orderByDesc('crs.started_at')
            ->orderByDesc('crs.id')
            ->get([
                'crs.id',
                'crs.station_id',
                'crs.rate_id',
                'crs.opened_by_user_id',
                'crs.closed_by_user_id',
                'crs.status as rental_status',
                'crs.billing_mode',
                'crs.hourly_rate',
                'crs.minimum_minutes',
                'crs.billing_increment_minutes',
                'crs.prepaid_minutes',
                'crs.prepaid_price',
                'crs.started_at',
                'crs.ended_at',
                'crs.duration_seconds',
                'crs.billable_minutes',
                'crs.amount as rental_amount',
                'crs.pos_order_id',
                'crs.sent_to_pos_at',
                'crs.paid_at as rental_paid_at',
                'crs.cancelled_at',
                'crs.cancel_reason',

                'st.code as station_code',
                'st.name as station_name',

                'rt.name as rate_name',

                'opener.name as opened_by_name',
                'closer.name as closed_by_name',

                'po.number as pos_number',
                'po.status as pos_status',
                'po.total as pos_total',
                'po.paid_at as pos_paid_at',
                'po.cancelled_at as pos_cancelled_at',

                DB::raw(
                    'COALESCE(cons.consumption_lines, 0) '
                    . 'as consumption_lines'
                ),
                DB::raw(
                    'COALESCE(cons.consumption_quantity, 0) '
                    . 'as consumption_quantity'
                ),
                DB::raw(
                    'COALESCE(cons.consumption_total, 0) '
                    . 'as consumption_total'
                ),
            ]);
    }

    /**
     * CIBER3R3_REPORT_SUMMARY
     */
    public static function summary(
        Collection $rows
    ): array {
        $paid = $rows->filter(
            fn ($row): bool =>
                (string) $row->rental_status
                === 'paid'
        );

        $cancelled = $rows->filter(
            fn ($row): bool =>
                (string) $row->rental_status
                === 'cancelled'
        );

        $pending = $rows->filter(
            fn ($row): bool =>
                ! in_array(
                    (string) $row->rental_status,
                    [
                        'paid',
                        'cancelled',
                    ],
                    true
                )
        );

        $realSeconds = (int) $rows->sum(
            fn ($row): int =>
                max(
                    0,
                    (int) (
                        $row->duration_seconds
                        ?? 0
                    )
                )
        );

        $billableMinutes = (int) $rows->sum(
            fn ($row): int =>
                max(
                    0,
                    (int) (
                        $row->billable_minutes
                        ?? 0
                    )
                )
        );

        $rentalRevenue = round(
            (float) $paid->sum(
                fn ($row): float =>
                    (float) (
                        $row->rental_amount
                        ?? 0
                    )
            ),
            2
        );

        $consumptionRevenue = round(
            (float) $paid->sum(
                fn ($row): float =>
                    (float) (
                        $row->consumption_total
                        ?? 0
                    )
            ),
            2
        );

        $linkedPosTotal = round(
            (float) $paid->sum(
                fn ($row): float =>
                    (float) (
                        $row->pos_total
                        ?? 0
                    )
            ),
            2
        );

        return [
            'sessions' => $rows->count(),
            'paid' => $paid->count(),
            'cancelled' => $cancelled->count(),
            'pending' => $pending->count(),

            /*
             * Tiempo real = ocupación física de la PC.
             */
            'real_seconds' => $realSeconds,
            'real_hours' => round(
                $realSeconds / 3600,
                2
            ),

            /*
             * Tiempo facturable = cálculo comercial.
             */
            'billable_minutes' =>
                $billableMinutes,

            'billable_hours' => round(
                $billableMinutes / 60,
                2
            ),

            'rental_revenue' =>
                $rentalRevenue,

            'consumption_revenue' =>
                $consumptionRevenue,

            'rental_plus_consumption' =>
                round(
                    $rentalRevenue
                    + $consumptionRevenue,
                    2
                ),

            /*
             * Puede incluir artículos normales agregados
             * posteriormente al ticket.
             */
            'linked_pos_total' =>
                $linkedPosTotal,
        ];
    }

    /**
     * CIBER3R3_STATION_USAGE
     *
     * No llamamos "porcentaje de utilización" a este valor
     * porque todavía no existe un horario disponible por PC.
     *
     * share_percent representa solamente la participación de
     * cada estación sobre el tiempo total ocupado del filtro.
     */
    public static function stationSummary(
        Collection $rows
    ): Collection {
        $totalSeconds = max(
            0,
            (int) $rows->sum(
                fn ($row): int =>
                    max(
                        0,
                        (int) (
                            $row->duration_seconds
                            ?? 0
                        )
                    )
            )
        );

        return $rows
            ->groupBy(
                fn ($row): int =>
                    (int) $row->station_id
            )
            ->map(
                function (
                    Collection $stationRows
                ) use ($totalSeconds): array {
                    $first = $stationRows->first();

                    $seconds = (int)
                        $stationRows->sum(
                            fn ($row): int =>
                                max(
                                    0,
                                    (int) (
                                        $row->duration_seconds
                                        ?? 0
                                    )
                                )
                        );

                    $billableMinutes = (int)
                        $stationRows->sum(
                            fn ($row): int =>
                                max(
                                    0,
                                    (int) (
                                        $row->billable_minutes
                                        ?? 0
                                    )
                                )
                        );

                    $paid =
                        $stationRows->filter(
                            fn ($row): bool =>
                                (string)
                                    $row->rental_status
                                === 'paid'
                        );

                    return [
                        'station_id' =>
                            (int) $first->station_id,

                        'station_code' =>
                            (string) (
                                $first->station_code
                                ?? ''
                            ),

                        'station_name' =>
                            (string) (
                                $first->station_name
                                ?? ''
                            ),

                        'sessions' =>
                            $stationRows->count(),

                        'paid' =>
                            $paid->count(),

                        'cancelled' =>
                            $stationRows
                                ->where(
                                    'rental_status',
                                    'cancelled'
                                )
                                ->count(),

                        'real_hours' =>
                            round(
                                $seconds / 3600,
                                2
                            ),

                        'billable_hours' =>
                            round(
                                $billableMinutes / 60,
                                2
                            ),

                        'share_percent' =>
                            $totalSeconds > 0
                                ? round(
                                    (
                                        $seconds
                                        / $totalSeconds
                                    ) * 100,
                                    1
                                )
                                : 0,

                        'rental_revenue' =>
                            round(
                                (float)
                                    $paid->sum(
                                        fn ($row): float =>
                                            (float) (
                                                $row
                                                    ->rental_amount
                                                ?? 0
                                            )
                                    ),
                                2
                            ),

                        'consumption_revenue' =>
                            round(
                                (float)
                                    $paid->sum(
                                        fn ($row): float =>
                                            (float) (
                                                $row
                                                    ->consumption_total
                                                ?? 0
                                            )
                                    ),
                                2
                            ),
                    ];
                }
            )
            ->sortBy('station_code')
            ->values();
    }


    /**
     * CIBER3R4_EXPORT_DATA
     */
    public static function data(array $filters): array
    {
        $rows = static::rows($filters);

        $companyId = (int) (
            $filters['company_id']
            ?? 0
        );

        $company = $companyId > 0
            ? DB::table('companies')
                ->where('id', $companyId)
                ->first()
            : null;

        return [
            'rows' => $rows,
            'summary' => static::summary($rows),
            'stationSummary' => static::stationSummary($rows),
            'filters' => $filters,
            'company' => $company,
            'logoDataUri' => static::companyLogoDataUri(
                $company
            ),
        ];
    }

    /**
     * CIBER3R4_EXPORT_XLSX
     */
    public static function writeExcel(
        string $path,
        array $filters
    ): void {
        $data = static::data($filters);

        $writer = new \OpenSpout\Writer\XLSX\Writer();

        $writer->openToFile($path);

        try {
            $add = static function (
                \OpenSpout\Writer\XLSX\Writer $writer,
                array $values
            ): void {
                $writer->addRow(
                    \OpenSpout\Common\Entity\Row::fromValues(
                        $values
                    )
                );
            };

            $add(
                $writer,
                [
                    'Reporte de Renta de equipos',
                ]
            );

            $add(
                $writer,
                [
                    'Periodo',
                    (string) (
                        $data['filters']['from']
                        ?? ''
                    ),
                    (string) (
                        $data['filters']['to']
                        ?? ''
                    ),
                ]
            );

            $add($writer, []);

            /*
             * Resumen general.
             */
            $add(
                $writer,
                [
                    'RESUMEN GENERAL',
                ]
            );

            $add(
                $writer,
                [
                    'Sesiones',
                    $data['summary']['sessions'],
                ]
            );

            $add(
                $writer,
                [
                    'Pagadas',
                    $data['summary']['paid'],
                ]
            );

            $add(
                $writer,
                [
                    'Canceladas',
                    $data['summary']['cancelled'],
                ]
            );

            $add(
                $writer,
                [
                    'Pendientes',
                    $data['summary']['pending'],
                ]
            );

            $add(
                $writer,
                [
                    'Horas ocupadas',
                    $data['summary']['real_hours'],
                ]
            );

            $add(
                $writer,
                [
                    'Horas facturables',
                    $data['summary']['billable_hours'],
                ]
            );

            $add(
                $writer,
                [
                    'Ingresos renta',
                    $data['summary']['rental_revenue'],
                ]
            );

            $add(
                $writer,
                [
                    'Consumos de renta',
                    $data['summary']['consumption_revenue'],
                ]
            );

            $add(
                $writer,
                [
                    'Renta + consumos',
                    $data['summary']['rental_plus_consumption'],
                ]
            );

            $add(
                $writer,
                [
                    'Total tickets PDV vinculados',
                    $data['summary']['linked_pos_total'],
                ]
            );

            $add($writer, []);

            /*
             * Resumen por estación.
             */
            $add(
                $writer,
                [
                    'OPERACION POR PC',
                ]
            );

            $add(
                $writer,
                [
                    'Código',
                    'Estación',
                    'Sesiones',
                    'Pagadas',
                    'Canceladas',
                    'Horas ocupadas',
                    'Horas facturables',
                    'Participación %',
                    'Ingresos renta',
                    'Consumos',
                ]
            );

            foreach (
                $data['stationSummary']
                as $station
            ) {
                $add(
                    $writer,
                    [
                        $station['station_code'],
                        $station['station_name'],
                        $station['sessions'],
                        $station['paid'],
                        $station['cancelled'],
                        $station['real_hours'],
                        $station['billable_hours'],
                        $station['share_percent'],
                        $station['rental_revenue'],
                        $station['consumption_revenue'],
                    ]
                );
            }

            $add($writer, []);

            /*
             * Detalle.
             */
            $add(
                $writer,
                [
                    'DETALLE DE SESIONES',
                ]
            );

            $add(
                $writer,
                [
                    'ID',
                    'PC',
                    'Estación',
                    'Inicio',
                    'Fin',
                    'Usuario apertura',
                    'Usuario cierre',
                    'Tarifa',
                    'Tipo',
                    'Tiempo real',
                    'Minutos facturables',
                    'Renta',
                    'Consumos',
                    'Ticket',
                    'Estado ticket',
                    'Total ticket',
                    'Estado renta',
                    'Pagada',
                    'Cancelada',
                    'Motivo cancelación',
                ]
            );

            foreach ($data['rows'] as $row) {
                $seconds = max(
                    0,
                    (int) (
                        $row->duration_seconds
                        ?? 0
                    )
                );

                $hours = intdiv(
                    $seconds,
                    3600
                );

                $minutes = intdiv(
                    $seconds % 3600,
                    60
                );

                $secs = $seconds % 60;

                $realDuration = sprintf(
                    '%02d:%02d:%02d',
                    $hours,
                    $minutes,
                    $secs
                );

                $add(
                    $writer,
                    [
                        (int) $row->id,
                        (string) (
                            $row->station_code
                            ?? ''
                        ),
                        (string) (
                            $row->station_name
                            ?? ''
                        ),
                        static::exportDateTime(
                            $row->started_at
                            ?? null
                        ),
                        static::exportDateTime(
                            $row->ended_at
                            ?? null
                        ),
                        (string) (
                            $row->opened_by_name
                            ?? ''
                        ),
                        (string) (
                            $row->closed_by_name
                            ?? ''
                        ),
                        (string) (
                            $row->rate_name
                            ?? ''
                        ),
                        static::billingModeLabel(
                            $row->billing_mode
                            ?? null
                        ),
                        $realDuration,
                        (int) (
                            $row->billable_minutes
                            ?? 0
                        ),
                        round(
                            (float) (
                                $row->rental_amount
                                ?? 0
                            ),
                            2
                        ),
                        round(
                            (float) (
                                $row->consumption_total
                                ?? 0
                            ),
                            2
                        ),
                        (string) (
                            $row->pos_number
                            ?? ''
                        ),
                        static::statusLabel(
                            $row->pos_status
                            ?? null
                        ),
                        $row->pos_order_id
                            ? round(
                                (float) (
                                    $row->pos_total
                                    ?? 0
                                ),
                                2
                            )
                            : '',
                        static::statusLabel(
                            $row->rental_status
                            ?? null
                        ),
                        static::exportDateTime(
                            $row->rental_paid_at
                            ?? null
                        ),
                        static::exportDateTime(
                            $row->cancelled_at
                            ?? null
                        ),
                        (string) (
                            $row->cancel_reason
                            ?? ''
                        ),
                    ]
                );
            }
        } finally {
            $writer->close();
        }
    }


    /**
     * CIBER3R4B_COMPANY_LOGO_DATA_URI
     *
     * Convierte el logo configurado de la empresa a
     * data URI para que DomPDF no dependa de HTTP.
     */
    protected static function companyLogoDataUri(
        ?object $company
    ): ?string {
        if (! $company) {
            return null;
        }

        foreach (
            [
                'logo_path',
                'logo_compact_path',
            ]
            as $field
        ) {
            $value = trim(
                (string) (
                    $company->{$field}
                    ?? ''
                )
            );

            if ($value === '') {
                continue;
            }

            if (
                str_starts_with(
                    $value,
                    'data:image/'
                )
            ) {
                return $value;
            }

            $relative = ltrim(
                $value,
                '/'
            );

            /*
             * Rutas normales de FileUpload:
             * companies/logos/...
             */
            $candidates = [
                storage_path(
                    'app/public/'
                    . $relative
                ),
                public_path(
                    'storage/'
                    . $relative
                ),
                public_path(
                    $relative
                ),
            ];

            foreach ($candidates as $path) {
                if (
                    ! is_file($path)
                    || ! is_readable($path)
                ) {
                    continue;
                }

                $contents =
                    @file_get_contents(
                        $path
                    );

                if (
                    $contents === false
                    || $contents === ''
                ) {
                    continue;
                }

                $mime = null;

                if (
                    function_exists(
                        'mime_content_type'
                    )
                ) {
                    $mime =
                        @mime_content_type(
                            $path
                        );
                }

                if (
                    ! is_string($mime)
                    || ! str_starts_with(
                        $mime,
                        'image/'
                    )
                ) {
                    $extension =
                        strtolower(
                            pathinfo(
                                $path,
                                PATHINFO_EXTENSION
                            )
                        );

                    $mime = match (
                        $extension
                    ) {
                        'jpg',
                        'jpeg' =>
                            'image/jpeg',

                        'gif' =>
                            'image/gif',

                        'webp' =>
                            'image/webp',

                        'svg' =>
                            'image/svg+xml',

                        default =>
                            'image/png',
                    };
                }

                return
                    'data:'
                    . $mime
                    . ';base64,'
                    . base64_encode(
                        $contents
                    );
            }
        }

        return null;
    }

    protected static function exportDateTime(
        mixed $value
    ): string {
        if (! $value) {
            return '';
        }

        try {
            return \Carbon\Carbon::parse(
                $value
            )->format(
                'd/m/Y H:i:s'
            );
        } catch (\Throwable) {
            return '';
        }
    }

    public static function statusLabel(
        ?string $status
    ): string {
        return match (
            strtolower(
                trim(
                    (string) $status
                )
            )
        ) {
            'active' => 'En uso',
            'finished' => 'Finalizada',
            'sent_to_pos' =>
                'Enviada a PDV',
            'paid' => 'Pagada',
            'cancelled',
            'canceled' => 'Cancelada',
            default =>
                $status
                    ? ucfirst(
                        str_replace(
                            '_',
                            ' ',
                            $status
                        )
                    )
                    : '—',
        };
    }

    public static function billingModeLabel(
        ?string $mode
    ): string {
        return match (
            strtolower(
                trim(
                    (string) $mode
                )
            )
        ) {
            'open' => 'Abierta',
            'prepaid' => 'Prepago / paquete',
            default => $mode ?: '—',
        };
    }
}
