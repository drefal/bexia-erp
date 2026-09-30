<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeAttendanceResource\Pages;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Support\EmployeeAttendanceIncidentSync;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

// BEXIA_EMPLOYEE_ATTENDANCE_RESOURCE_RESPONSIVE_V5_79_31C
class EmployeeAttendanceResource extends Resource
{
    protected static ?string $model = EmployeeAttendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'RRHH';

    protected static ?string $navigationLabel = 'Asistencias';

    protected static ?string $modelLabel = 'asistencia';

    protected static ?string $pluralModelLabel = 'asistencias';

    protected static ?int $navigationSort = 22;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static function bexiaCanAsistenciaPermission(string $permission): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ((bool) ($user->is_system_admin ?? false)) {
            return true;
        }

        if (($user->email ?? null) === 'admin@bexiaerp.com') {
            return true;
        }

        return $user->can($permission);
    }


    protected static function bexiaCanExactAsistenciaPermission(string $permission): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ((bool) ($user->is_system_admin ?? false)) {
            return true;
        }

        if (($user->email ?? null) === 'admin@bexiaerp.com') {
            return true;
        }

        return $user->can($permission);
    }

    public static function canReviewMobileAttendance(?EmployeeAttendance $record = null): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ((bool) ($user->is_system_admin ?? false)) {
            return true;
        }

        if (($user->email ?? null) === 'admin@bexiaerp.com') {
            return true;
        }

        if (
            $user->can('rrhh.asistencias.revisar_movil')
            || $user->can('rrhh.asistencias.revisar_geocerca')
            || $user->can('rrhh.asistencias.editar')
        ) {
            return true;
        }

        if ($record) {
            $employee = $record->relationLoaded('employee')
                ? $record->employee
                : $record->employee()->first();

            if ($employee && (int) ($employee->supervisor_user_id ?? 0) === (int) $user->id) {
                return true;
            }
        }

        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::bexiaCanAsistenciaPermission('rrhh.asistencias.ver');
    }

    public static function canViewAny(): bool
    {
        return static::bexiaCanAsistenciaPermission('rrhh.asistencias.ver');
    }

    public static function canView(Model $record): bool
    {
        return static::bexiaCanAsistenciaPermission('rrhh.asistencias.ver');
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = Filament::getTenant()?->getKey();

        return parent::getEloquentQuery()
            ->with(['employee', 'workSchedule'])
            ->when($tenantId, fn (Builder $query) => $query->where('company_id', $tenantId));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Registro de asistencia')
                    ->extraAttributes(['class' => 'bexia-employee-attendance-section bexia-employee-attendance-record-section'])
                    ->description('Captura entrada y salida. El sistema calcula estado, retardo, salida temprana, horas trabajadas y horas extra contra el horario operativo.')
                    ->schema([
                        Grid::make(2)
                            ->extraAttributes(['class' => 'bexia-employee-attendance-grid bexia-employee-attendance-record-grid'])
                            ->schema([
                                Select::make('employee_id')
                                    ->label('Empleado')
                                    ->extraAttributes(['class' => 'bexia-employee-attendance-field bexia-employee-attendance-employee-field'])
                                    ->options(fn () => self::employeeOptions())
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                DatePicker::make('attendance_date')
                                    ->label('Fecha')
                                    ->extraAttributes(['class' => 'bexia-employee-attendance-field bexia-employee-attendance-date-field'])
                                    ->native(false)
                                    ->default(now())
                                    ->required(),

                                DateTimePicker::make('clock_in_at')
                                    ->label('Entrada real')
                                    ->extraAttributes(['class' => 'bexia-employee-attendance-field bexia-employee-attendance-clock-in-field'])
                                    ->seconds(false)
                                    ->native(false),

                                DateTimePicker::make('clock_out_at')
                                    ->label('Salida real')
                                    ->extraAttributes(['class' => 'bexia-employee-attendance-field bexia-employee-attendance-clock-out-field'])
                                    ->seconds(false)
                                    ->native(false),

                                Select::make('source')
                                    ->label('Origen')
                                    ->extraAttributes(['class' => 'bexia-employee-attendance-field bexia-employee-attendance-source-field'])
                                    ->options([
                                        'manual' => 'Manual',
                                        'clock' => 'Checador',
                                        'import' => 'Importación',
                                        'system' => 'Sistema',
                                    ])
                                    ->default('manual'),

                                Placeholder::make('calculation_hint')
                                    ->label('Cálculo automático')
                                    ->extraAttributes(['class' => 'bexia-employee-attendance-placeholder bexia-employee-attendance-calculation-wrapper'])
                                    ->content(new HtmlString('<div class="bexia-employee-attendance-calculation-hint rounded-xl border border-sky-300 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-700 dark:bg-sky-950 dark:text-sky-200">Al guardar se calcula el horario esperado, minutos trabajados, retardo, salida temprana, horas extra y estado.</div>'))
                                    ->columnSpanFull(),

                                Textarea::make('notes')
                                    ->label('Notas')
                                    ->extraAttributes(['class' => 'bexia-employee-attendance-field bexia-employee-attendance-notes-field'])
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('Incidencias / aprobación')
                    ->extraAttributes([
                        'class' =>
                            'bexia-employee-attendance-section '
                            . 'bexia-employee-attendance-incident-section',
                    ])
                    ->description(
                        'Incidencias generadas automáticamente desde '
                        . 'esta asistencia. La aprobación formal sigue '
                        . 'en Mis aprobaciones.'
                    )
                    ->visible(
                        fn (?EmployeeAttendance $record): bool =>
                            (bool) $record
                    )
                    ->schema([
                        Placeholder::make(
                            'attendance_incidents_summary'
                        )
                            ->label('Incidencias')
                            ->content(
                                fn (
                                    ?EmployeeAttendance $record
                                ) =>
                                    static::incidentReviewHtml(
                                        $record
                                    )
                            )
                            ->columnSpanFull(),
                    ]),

                Section::make('Revisión móvil / geocerca')
                    ->extraAttributes(['class' => 'bexia-employee-attendance-section bexia-employee-attendance-mobile-review-section'])
                    ->description('Revisión operativa de la ubicación reportada por QR o checador móvil. No sustituye el flujo formal de aprobación de incidencias.')
                    ->visible(fn (?EmployeeAttendance $record): bool => (bool) $record && (
                        $record->mobile_review_status !== null
                        || $record->clock_in_location_status !== null
                        || $record->clock_out_location_status !== null
                        || $record->clock_in_latitude !== null
                        || $record->clock_out_latitude !== null
                    ))
                    ->schema([
                        Grid::make(3)
                            ->extraAttributes(['class' => 'bexia-employee-attendance-grid bexia-employee-attendance-mobile-review-grid'])
                            ->schema([
                                Select::make('mobile_review_status')
                                    ->label('Estado revisión móvil')
                                    ->extraAttributes(['class' => 'bexia-employee-attendance-field bexia-employee-attendance-mobile-status-field'])
                                    ->options([
                                        'accepted' => 'Aceptada',
                                        'pending' => 'Pendiente',
                                        'rejected' => 'Rechazada',
                                    ])
                                    ->native(false)
                                    ->disabled(fn (?EmployeeAttendance $record): bool => ! static::canReviewMobileAttendance($record))
                                    ->helperText('La ubicación se revisa aquí; las incidencias mantienen su propio flujo de aprobación.'),

                                Placeholder::make('mobile_reviewed_by_label')
                                    ->label('Revisado por')
                                    ->extraAttributes(['class' => 'bexia-employee-attendance-placeholder bexia-employee-attendance-reviewed-by-wrapper'])
                                    ->content(fn (?EmployeeAttendance $record): string => $record?->mobileReviewer?->name ?? '—'),

                                Placeholder::make('mobile_reviewed_at_label')
                                    ->label('Revisado el')
                                    ->extraAttributes(['class' => 'bexia-employee-attendance-placeholder bexia-employee-attendance-reviewed-at-wrapper'])
                                    ->content(fn (?EmployeeAttendance $record): string => $record?->mobile_reviewed_at?->format('d/m/Y H:i') ?? '—'),

                                Placeholder::make('clock_in_geo_summary')
                                    ->label('Entrada / geocerca')
                                    ->extraAttributes(['class' => 'bexia-employee-attendance-placeholder bexia-employee-attendance-clock-in-geo-wrapper'])
                                    ->content(fn (?EmployeeAttendance $record): string => $record
                                        ? 'Estado: ' . match ($record->clock_in_location_status) {
                                            'inside' => 'Dentro',
                                            'outside' => 'Fuera',
                                            'poor_accuracy' => 'GPS bajo',
                                            'no_location' => 'Sin ubicación',
                                            'no_geofence' => 'Sin geocerca',
                                            default => $record->clock_in_location_status ?: '—',
                                        } . ' · Distancia: ' . ($record->clock_in_distance_meters !== null ? ((int) $record->clock_in_distance_meters) . ' m' : '—') . ' · Precisión: ' . ($record->clock_in_accuracy_meters !== null ? ((int) $record->clock_in_accuracy_meters) . ' m' : '—')
                                        : '—'),

                                Placeholder::make('clock_out_geo_summary')
                                    ->label('Salida / geocerca')
                                    ->extraAttributes(['class' => 'bexia-employee-attendance-placeholder bexia-employee-attendance-clock-out-geo-wrapper'])
                                    ->content(fn (?EmployeeAttendance $record): string => $record
                                        ? 'Estado: ' . match ($record->clock_out_location_status) {
                                            'inside' => 'Dentro',
                                            'outside' => 'Fuera',
                                            'poor_accuracy' => 'GPS bajo',
                                            'no_location' => 'Sin ubicación',
                                            'no_geofence' => 'Sin geocerca',
                                            default => $record->clock_out_location_status ?: '—',
                                        } . ' · Distancia: ' . ($record->clock_out_distance_meters !== null ? ((int) $record->clock_out_distance_meters) . ' m' : '—') . ' · Precisión: ' . ($record->clock_out_accuracy_meters !== null ? ((int) $record->clock_out_accuracy_meters) . ' m' : '—')
                                        : '—'),

                                Placeholder::make('mobile_reviewer_rule')
                                    ->label('Quién puede revisar')
                                    ->extraAttributes(['class' => 'bexia-employee-attendance-placeholder bexia-employee-attendance-reviewer-rule-wrapper'])
                                    ->content('Super Admin, admin de empresa, RRHH autorizado o jefe directo del empleado.'),
                            ]),

                        Textarea::make('mobile_review_notes')
                            ->label('Notas de revisión móvil')
                            ->extraAttributes(['class' => 'bexia-employee-attendance-field bexia-employee-attendance-mobile-notes-field'])
                            ->rows(3)
                            ->disabled(fn (?EmployeeAttendance $record): bool => ! static::canReviewMobileAttendance($record))
                            ->required(fn (\Filament\Forms\Get $get): bool => $get('mobile_review_status') === 'rejected')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function employeeOptions(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        return Employee::query()
            ->when($tenantId, fn ($query) => $query->where('company_id', $tenantId))
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public static function statusLabel(?string $state): string
    {
        return EmployeeAttendance::statusOptions()[$state] ?? ($state ?: '-');
    }

    /**
     * V5.83.6J2AP3
     *
     * Incidencias generadas desde una asistencia.
     * Se consulta por employee_attendance_id para permitir
     * más de una incidencia en la misma jornada.
     */
    protected static function attendanceIncidents(
        ?EmployeeAttendance $record
    ) {
        if (
            ! $record
            || ! $record->getKey()
            || ! \Illuminate\Support\Facades\Schema::hasTable(
                'employee_incidents'
            )
        ) {
            return collect();
        }

        return \Illuminate\Support\Facades\DB::table(
                'employee_incidents as i'
            )
            ->leftJoin(
                'hr_incident_types as t',
                't.id',
                '=',
                'i.hr_incident_type_id'
            )
            ->leftJoin(
                'users as u',
                'u.id',
                '=',
                'i.approved_by_user_id'
            )
            ->where(
                'i.employee_attendance_id',
                $record->getKey()
            )
            ->orderBy('i.id')
            ->get([
                'i.id',
                'i.title',
                'i.status',
                'i.quantity',
                'i.quantity_unit',
                'i.requires_approval',
                'i.approved_by_user_id',
                'i.approved_at',
                'i.resolution_notes',
                't.code as incident_code',
                't.name as incident_type_name',
                'u.name as approved_by_name',
            ]);
    }

    protected static function incidentStatusLabel(
        ?string $status
    ): string {
        return match ((string) $status) {
            'draft' => 'Borrador',
            'pending' => 'Pendiente',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            'cancelled', 'canceled' => 'Cancelada',
            default => $status
                ? str($status)->headline()->toString()
                : '—',
        };
    }

    protected static function incidentQuantityLabel(
        object $incident
    ): string {
        $quantity = (float) ($incident->quantity ?? 0);
        $unit = (string) ($incident->quantity_unit ?? '');

        if ($unit === 'minutes') {
            return (string) ((int) round($quantity)) . ' min';
        }

        if ($unit === 'days') {
            return rtrim(
                rtrim(
                    number_format($quantity, 2, '.', ''),
                    '0'
                ),
                '.'
            ) . ' día(s)';
        }

        if ($quantity > 0) {
            return rtrim(
                rtrim(
                    number_format($quantity, 2, '.', ''),
                    '0'
                ),
                '.'
            );
        }

        return '';
    }

    protected static function incidentTableSummary(
        ?EmployeeAttendance $record
    ): string {
        $incidents = static::attendanceIncidents($record);

        if ($incidents->isEmpty()) {
            return '—';
        }

        return $incidents
            ->map(function ($incident): string {
                $type = trim(
                    (string) (
                        $incident->incident_type_name
                        ?: $incident->incident_code
                        ?: 'Incidencia'
                    )
                );

                $quantity = static::incidentQuantityLabel(
                    $incident
                );

                $status = static::incidentStatusLabel(
                    $incident->status
                );

                return $type
                    . ($quantity !== ''
                        ? ' · ' . $quantity
                        : '')
                    . ' · '
                    . $status;
            })
            ->implode(' | ');
    }

    protected static function incidentReviewHtml(
        ?EmployeeAttendance $record
    ): \Illuminate\Support\HtmlString {
        $incidents = static::attendanceIncidents($record);

        if ($incidents->isEmpty()) {
            return new \Illuminate\Support\HtmlString(
                '<div class="rounded-xl border border-gray-200 '
                . 'bg-gray-50 px-4 py-3 text-sm text-gray-600 '
                . 'dark:border-gray-700 dark:bg-gray-900 '
                . 'dark:text-gray-300">'
                . 'No existen incidencias generadas para esta asistencia.'
                . '</div>'
            );
        }

        $html = '<div class="space-y-3">';

        foreach ($incidents as $incident) {
            $type = e(
                (string) (
                    $incident->incident_type_name
                    ?: $incident->incident_code
                    ?: 'Incidencia'
                )
            );

            $quantity = e(
                static::incidentQuantityLabel($incident)
            );

            $status = e(
                static::incidentStatusLabel(
                    $incident->status
                )
            );

            $statusClass = match (
                (string) $incident->status
            ) {
                'approved' =>
                    'text-green-700 dark:text-green-300',
                'rejected' =>
                    'text-red-700 dark:text-red-300',
                'pending', 'draft' =>
                    'text-amber-700 dark:text-amber-300',
                default =>
                    'text-gray-700 dark:text-gray-300',
            };

            $tenant = \Filament\Facades\Filament::getTenant();

            $urlParameters = [
                'record' => (int) $incident->id,
            ];

            if ($tenant) {
                $urlParameters['tenant'] = $tenant;
            } elseif (! empty($record?->company_id)) {
                /*
                 * En pruebas CLI no existe tenant Filament activo.
                 * La ruta de EmployeeIncidentResource sí lo requiere,
                 * por lo que usamos la empresa de la propia asistencia.
                 */
                $urlParameters['tenant'] =
                    (int) $record->company_id;
            }

            $url = \App\Filament\Resources\EmployeeIncidentResource::getUrl(
                'edit',
                $urlParameters
            );

            $resolved = '';

            if (
                $incident->approved_by_name
                || $incident->approved_at
            ) {
                $resolved = '<div class="mt-1 text-xs '
                    . 'text-gray-500 dark:text-gray-400">';

                if ($incident->approved_by_name) {
                    $resolved .= 'Resuelto por: '
                        . e(
                            (string) $incident->approved_by_name
                        );
                }

                if ($incident->approved_at) {
                    $resolved .= (
                        $incident->approved_by_name
                            ? ' · '
                            : ''
                    )
                        . e(
                            \Carbon\Carbon::parse(
                                $incident->approved_at
                            )->format('d/m/Y H:i')
                        );
                }

                $resolved .= '</div>';
            }

            $notes = '';

            if (
                trim(
                    (string) (
                        $incident->resolution_notes ?? ''
                    )
                ) !== ''
            ) {
                $notes =
                    '<div class="mt-1 text-xs '
                    . 'text-gray-500 dark:text-gray-400">'
                    . 'Resolución: '
                    . e(
                        (string) $incident->resolution_notes
                    )
                    . '</div>';
            }

            $html .=
                '<div class="rounded-xl border '
                . 'border-gray-200 bg-white px-4 py-3 '
                . 'dark:border-gray-700 dark:bg-gray-900">'
                . '<div class="flex items-start '
                . 'justify-between gap-4">'
                . '<div>'
                . '<div class="font-medium text-gray-950 '
                . 'dark:text-white">'
                . $type
                . ($quantity !== ''
                    ? ' · ' . $quantity
                    : '')
                . '</div>'
                . '<div class="mt-1 text-sm '
                . $statusClass
                . '">'
                . $status
                . '</div>'
                . $resolved
                . $notes
                . '</div>'
                . '<div class="shrink-0">'
                . '<a href="'
                . e($url)
                . '" class="text-sm font-medium '
                . 'text-primary-600 hover:underline '
                . 'dark:text-primary-400">'
                . 'Abrir incidencia'
                . '</a>'
                . '</div>'
                . '</div>'
                . '</div>';
        }

        $html .=
            '<div class="text-xs text-gray-500 '
            . 'dark:text-gray-400">'
            . 'La aprobación formal se mantiene en '
            . 'Mis aprobaciones. El jefe directo o RRHH '
            . 'puede resolver la incidencia.'
            . '</div>';

        $html .= '</div>';

        return new \Illuminate\Support\HtmlString($html);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                // v5790l_qr_geofence_device_columns
                \Filament\Tables\Columns\TextColumn::make('source')
                    // v5790l6_origen_qr_get_state
                    ->getStateUsing(fn ($record): string => match ($record->source) {
                        'qr_link' => 'QR',
                        'clock' => 'Checador',
                        'manual' => 'Manual',
                        default => $record->source ? str($record->source)->headline()->toString() : '—',
                    })
                    ->label('Origen')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-source'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-source'])
                    // v5790l4_origen_qr_state
                    ->state(fn ($record): string => match ($record->source) {
                        'qr_link' => 'QR',
                        'clock' => 'Checador',
                        'manual' => 'Manual',
                        default => $record->source ? str($record->source)->headline()->toString() : '—',
                    })
                    // v5790l1_origen_qr_label
                    
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'qr_link' => 'QR',
                        'clock' => 'Checador',
                        'manual' => 'Manual',
                        default => $state ? str($state)->headline()->toString() : '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'QR' => 'success',
                        'Checador' => 'info',
                        'Manual' => 'gray',
                        'qr_link' => 'success',
                        'clock' => 'info',
                        'manual' => 'gray',
                        default => 'gray',
                    })
                    ->toggleable(),

                \Filament\Tables\Columns\TextColumn::make('mobile_review_status')
                    ->label('Rev. móvil')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-mobile-review'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-mobile-review'])
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'accepted' => 'Aceptada',
                        'pending' => 'Pendiente',
                        'rejected' => 'Rechazada',
                        default => $state ? str($state)->headline()->toString() : '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'accepted' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),

                \Filament\Tables\Columns\TextColumn::make('clock_in_location_status')
                    ->label('Geo entrada')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-clock-in-geo'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-clock-in-geo'])
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'inside' => 'Dentro',
                        'outside' => 'Fuera',
                        'poor_accuracy' => 'GPS bajo',
                        'no_location' => 'Sin ubicación',
                        'no_geofence' => 'Sin geocerca',
                        default => $state ? str($state)->headline()->toString() : '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'inside' => 'success',
                        'outside' => 'danger',
                        'poor_accuracy' => 'warning',
                        'no_location' => 'gray',
                        'no_geofence' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),

                \Filament\Tables\Columns\TextColumn::make('clock_out_location_status')
                    ->label('Geo salida')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-clock-out-geo'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-clock-out-geo'])
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'inside' => 'Dentro',
                        'outside' => 'Fuera',
                        'poor_accuracy' => 'GPS bajo',
                        'no_location' => 'Sin ubicación',
                        'no_geofence' => 'Sin geocerca',
                        default => $state ? str($state)->headline()->toString() : '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'inside' => 'success',
                        'outside' => 'danger',
                        'poor_accuracy' => 'warning',
                        'no_location' => 'gray',
                        'no_geofence' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),

                \Filament\Tables\Columns\TextColumn::make('clock_in_distance_meters')
                    ->label('Dist. entrada')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-clock-in-distance'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-clock-in-distance'])
                    ->formatStateUsing(fn ($state): string => $state === null ? '—' : ((int) $state) . ' m')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                \Filament\Tables\Columns\TextColumn::make('clock_out_distance_meters')
                    ->label('Dist. salida')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-clock-out-distance'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-clock-out-distance'])
                    ->formatStateUsing(fn ($state): string => $state === null ? '—' : ((int) $state) . ' m')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                \Filament\Tables\Columns\TextColumn::make('clock_in_device_guard_status')
                    ->label('Guard entrada')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-clock-in-guard'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-clock-in-guard'])
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'ok' => 'OK',
                        'no_fingerprint' => 'Sin huella',
                        'disabled_by_company' => 'Desactivado',
                        'blocked_same_device_other_employee' => 'Bloqueado',
                        'schema_not_ready' => 'Sin esquema',
                        default => $state ? str($state)->headline()->toString() : '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'ok' => 'success',
                        'blocked_same_device_other_employee' => 'danger',
                        'no_fingerprint' => 'warning',
                        'disabled_by_company' => 'gray',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                \Filament\Tables\Columns\TextColumn::make('clock_out_device_guard_status')
                    ->label('Guard salida')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-clock-out-guard'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-clock-out-guard'])
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'ok' => 'OK',
                        'no_fingerprint' => 'Sin huella',
                        'disabled_by_company' => 'Desactivado',
                        'blocked_same_device_other_employee' => 'Bloqueado',
                        'schema_not_ready' => 'Sin esquema',
                        default => $state ? str($state)->headline()->toString() : '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'ok' => 'success',
                        'blocked_same_device_other_employee' => 'danger',
                        'no_fingerprint' => 'warning',
                        'disabled_by_company' => 'gray',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                \Filament\Tables\Columns\TextColumn::make('clock_in_device_fingerprint')
                    ->label('Equipo entrada')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-clock-in-device'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-clock-in-device'])
                    ->formatStateUsing(fn (?string $state): string => $state ? substr($state, 0, 10) . '…' : '—')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                \Filament\Tables\Columns\TextColumn::make('clock_out_device_fingerprint')
                    ->label('Equipo salida')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-clock-out-device'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-clock-out-device'])
                    ->formatStateUsing(fn (?string $state): string => $state ? substr($state, 0, 10) . '…' : '—')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('attendance_date')
                    ->label('Fecha')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-date'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-date'])
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Empleado')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-employee'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-employee'])
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('workSchedule.name')
                    ->label('Horario')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-schedule'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-schedule'])
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-status'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-status'])
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'attendance_incidents_summary'
                )
                    ->label('Incidencias')
                    ->getStateUsing(
                        fn (
                            EmployeeAttendance $record
                        ): string =>
                            static::incidentTableSummary(
                                $record
                            )
                    )
                    ->wrap()
                    ->tooltip(
                        fn (
                            EmployeeAttendance $record
                        ): string =>
                            static::incidentTableSummary(
                                $record
                            )
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('expected_start_at')
                    ->label('Entrada esperada')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-expected-start'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-expected-start'])
                    ->dateTime('H:i')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('clock_in_at')
                    ->label('Entrada real')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-clock-in'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-clock-in'])
                    ->dateTime('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_end_at')
                    ->label('Salida esperada')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-expected-end'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-expected-end'])
                    ->dateTime('H:i')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('clock_out_at')
                    ->label('Salida real')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-clock-out'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-clock-out'])
                    ->dateTime('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('worked_hours')
                    ->label('Horas')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-worked-hours'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-worked-hours'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('late_minutes')
                    ->label('Retardo')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-late'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-late'])
                    ->suffix(' min')
                    ->sortable(),

                Tables\Columns\TextColumn::make('early_leave_minutes')
                    ->label('Salida temp.')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-early-leave'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-early-leave'])
                    ->suffix(' min')
                    ->sortable(),

                Tables\Columns\TextColumn::make('meal_summary')
                    ->label('Comida')
                    ->getStateUsing(function (EmployeeAttendance $record): string {
                        $actual = EmployeeAttendanceIncidentSync::mealActualMinutes($record);
                        $allowed = max(0, (int) ($record->break_minutes ?? 0));
                        $excess = EmployeeAttendanceIncidentSync::mealExcessMinutes($record);

                        if ($allowed <= 0) {
                            return 'Sin comida';
                        }

                        if ($actual === null) {
                            return $allowed . ' min permitidos';
                        }

                        if ($excess > 0) {
                            return $actual . '/' . $allowed . ' min · +' . $excess;
                        }

                        return $actual . '/' . $allowed . ' min';
                    })
                    ->badge()
                    ->color(function (EmployeeAttendance $record): string {
                        if (EmployeeAttendanceIncidentSync::mealExcessMinutes($record) > 0) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('overtime_minutes')
                    ->label('Extra')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-overtime'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-overtime'])
                    ->suffix(' min')
                    ->sortable(),

                Tables\Columns\TextColumn::make('source')
                    // v5790l6_origen_qr_get_state
                    ->getStateUsing(fn ($record): string => match ($record->source) {
                        'qr_link' => 'QR',
                        'clock' => 'Checador',
                        'manual' => 'Manual',
                        default => $record->source ? str($record->source)->headline()->toString() : '—',
                    })
                    ->label('Origen')
                    ->extraHeaderAttributes(['class' => 'bexia-employee-attendance-col-source'])
                    ->extraCellAttributes(['class' => 'bexia-employee-attendance-col-source'])
                    ->badge()
                    ->toggleable(),
            ])
            ->filters([

                // v5790l_qr_geofence_device_filters
                \Filament\Tables\Filters\SelectFilter::make('source')
                    ->label('Origen')
                    ->options([
                        'qr_link' => 'QR',
                        'clock' => 'Mi checador',
                        'manual' => 'Manual',
                    ]),

                \Filament\Tables\Filters\SelectFilter::make('mobile_review_status')
                    ->label('Revisión móvil')
                    ->options([
                        'accepted' => 'Aceptada',
                        'pending' => 'Pendiente',
                        'rejected' => 'Rechazada',
                    ]),

                \Filament\Tables\Filters\Filter::make('pending_mobile_outside')
                    ->label('Pendientes fuera de geocerca')
                    ->query(fn ($query) => $query
                        ->where('mobile_review_status', 'pending')
                        ->where(function ($query): void {
                            $query->where('clock_in_location_status', 'outside')
                                ->orWhere('clock_out_location_status', 'outside');
                        })),

                \Filament\Tables\Filters\Filter::make('qr_mobile')
                    ->label('Solo QR / móvil')
                    ->query(fn ($query) => $query->where(function ($query): void {
                        $query->where('source', 'qr_link')
                            ->orWhere('clock_in_method', 'qr_link')
                            ->orWhere('clock_out_method', 'qr_link')
                            ->orWhereNotNull('clock_in_latitude')
                            ->orWhereNotNull('clock_out_latitude');
                    })),

                \Filament\Tables\Filters\Filter::make('outside_geofence')
                    ->label('Fuera de geocerca')
                    ->query(fn ($query) => $query->where(function ($query): void {
                        $query->where('clock_in_location_status', 'outside')
                            ->orWhere('clock_out_location_status', 'outside');
                    })),

                \Filament\Tables\Filters\Filter::make('inside_geofence')
                    ->label('Dentro de geocerca')
                    ->query(fn ($query) => $query->where(function ($query): void {
                        $query->where('clock_in_location_status', 'inside')
                            ->orWhere('clock_out_location_status', 'inside');
                    })),

                \Filament\Tables\Filters\Filter::make('device_suspicious')
                    ->label('Dispositivo sospechoso')
                    ->query(fn ($query) => $query->where(function ($query): void {
                        $query->where('clock_in_device_guard_status', 'blocked_same_device_other_employee')
                            ->orWhere('clock_out_device_guard_status', 'blocked_same_device_other_employee')
                            ->orWhere('clock_in_device_guard_status', 'no_fingerprint')
                            ->orWhere('clock_out_device_guard_status', 'no_fingerprint');
                    })),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(EmployeeAttendance::statusOptions()),

                Filter::make('today')
                    ->label('Hoy')
                    ->query(fn (Builder $query): Builder => $query->whereDate('attendance_date', today())),

                Filter::make('current_month')
                    ->label('Mes actual')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereDate('attendance_date', '>=', now()->startOfMonth())
                        ->whereDate('attendance_date', '<=', now()->endOfMonth())),

                Filter::make('with_late')
                    ->label('Con retardo')
                    ->query(fn (Builder $query): Builder => $query->where('late_minutes', '>', 0)),

                Filter::make(
                    'with_pending_incidents'
                )
                    ->label('Incidencias pendientes')
                    ->query(
                        fn (
                            Builder $query
                        ): Builder =>
                            $query->whereExists(
                                function ($subQuery): void {
                                    $subQuery
                                        ->selectRaw('1')
                                        ->from(
                                            'employee_incidents as ei'
                                        )
                                        ->whereColumn(
                                            'ei.employee_attendance_id',
                                            'employee_attendances.id'
                                        )
                                        ->whereIn(
                                            'ei.status',
                                            [
                                                'draft',
                                                'pending',
                                            ]
                                        );
                                }
                            )
                    ),

                Filter::make('with_meal_excess')
                    ->label('Con exceso de comida')
                    ->query(function (Builder $query): Builder {
                        return $query->whereExists(function ($subQuery): void {
                            $subQuery
                                ->selectRaw('1')
                                ->from('employee_attendance_breaks as eab')
                                ->whereColumn('eab.employee_attendance_id', 'employee_attendances.id')
                                ->where('eab.break_type', 'meal')
                                ->whereNotNull('eab.started_at')
                                ->whereNotNull('eab.ended_at')
                                ->whereRaw('employee_attendances.break_minutes > 0')
                                ->whereRaw(
                                    "EXTRACT(EPOCH FROM (eab.ended_at - eab.started_at)) / 60 > employee_attendances.break_minutes"
                                );
                        });
                    }),

                Filter::make('with_overtime')
                    ->label('Con horas extra')
                    ->query(fn (Builder $query): Builder => $query->where('overtime_minutes', '>', 0)),
            ])
            ->recordUrl(fn (EmployeeAttendance $record): string => static::getUrl('edit', ['record' => $record]))
            ->actions([

                Tables\Actions\Action::make(
                    'view_attendance_incidents'
                )
                    ->label('Incidencias')
                    ->icon(
                        'heroicon-o-exclamation-triangle'
                    )
                    ->color('warning')
                    ->modalHeading(
                        'Incidencias de asistencia'
                    )
                    ->modalContent(
                        fn (
                            EmployeeAttendance $record
                        ) =>
                            static::incidentReviewHtml(
                                $record
                            )
                    )
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->visible(
                        fn (
                            EmployeeAttendance $record
                        ): bool =>
                            static::attendanceIncidents(
                                $record
                            )->isNotEmpty()
                    ),

                Tables\Actions\Action::make('open_attendance_review')
                    ->label('Abrir / revisar')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (EmployeeAttendance $record): string => static::getUrl('edit', ['record' => $record]))
                    ->visible(fn (EmployeeAttendance $record): bool => static::canEdit($record)),

                Tables\Actions\DeleteAction::make()->label('Eliminar')->visible(fn (): bool => static::canManageAttendanceRecords()),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('generate_incidents')
                    ->label('Generar incidencias')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->requiresConfirmation()
                    ->action(function ($records): void {
                        $generated = 0;
                        $skipped = 0;
                        $errors = [];

                        foreach ($records as $record) {
                            try {
                                $incidents = EmployeeAttendanceIncidentSync::syncAll($record, auth()->id(), true);

                                if (! empty($incidents)) {
                                    $generated += count($incidents);
                                } else {
                                    $skipped++;
                                }
                            } catch (\Throwable $e) {
                                $errors[] = '#' . $record->id . ': ' . $e->getMessage();
                            }
                        }

                        $body = 'Generadas/encontradas: ' . $generated . '. Omitidas: ' . $skipped . '.';

                        if (! empty($errors)) {
                            $body .= ' Errores: ' . substr(implode(' | ', array_slice($errors, 0, 3)), 0, 500);
                        }

                        Notification::make()
                            ->title(empty($errors) ? 'Incidencias procesadas' : 'Incidencias procesadas con errores')
                            ->body($body)
                            ->color(empty($errors) ? 'success' : 'warning')
                            ->send();
                    }),

                Tables\Actions\DeleteBulkAction::make()->label('Eliminar seleccionadas'),
            ])
            ->defaultSort('attendance_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeAttendances::route('/'),
            'create' => Pages\CreateEmployeeAttendance::route('/create'),
            'edit' => Pages\EditEmployeeAttendance::route('/{record}/edit'),
        ];
    }
    // v5790l1_can_manage_attendance_records
    // v5790l2_can_manage_attendance_records
    // v5790l3_can_manage_attendance_records

    public static function canEdit(Model $record): bool
    {
        return static::canManageAttendanceRecords()
            || static::canReviewMobileAttendance($record);
    }

    protected static function canManageAttendanceRecords(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasRole')) {
            try {
                if (
                    $user->hasRole('super_admin')
                    || $user->hasRole('superadmin')
                    || $user->hasRole('Super Admin')
                    || $user->hasRole('SuperAdmin')
                ) {
                    return true;
                }
            } catch (\Throwable) {
                // Continuar con relación de roles.
            }
        }

        try {
            if (method_exists($user, 'roles')) {
                return $user->roles()
                    ->where(function ($query): void {
                        $query->whereRaw('LOWER(name) = ?', ['super_admin'])
                            ->orWhereRaw('LOWER(name) = ?', ['superadmin'])
                            ->orWhereRaw('LOWER(name) = ?', ['super admin']);
                    })
                    ->exists();
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    public static function canCreate(): bool
    {
        return static::canManageAttendanceRecords()
            || static::bexiaCanExactAsistenciaPermission('rrhh.asistencias.crear');
    }

    public static function canDelete($record): bool
    {
        return static::canManageAttendanceRecords()
            || static::bexiaCanExactAsistenciaPermission('rrhh.asistencias.eliminar');
    }

    public static function canDeleteAny(): bool
    {
        return static::canManageAttendanceRecords()
            || static::bexiaCanExactAsistenciaPermission('rrhh.asistencias.eliminar');
    }


}
