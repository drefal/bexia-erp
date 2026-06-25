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

class EmployeeAttendanceResource extends Resource
{
    protected static ?string $model = EmployeeAttendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'RRHH';

    protected static ?string $navigationLabel = 'Asistencias';

    protected static ?string $modelLabel = 'asistencia';

    protected static ?string $pluralModelLabel = 'asistencias';

    protected static ?int $navigationSort = 21;

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

        return $user->can($permission)
            || $user->can('rrhh.incidencias.ver')
            || $user->can('company.update');
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
            || $user->can('rrhh.asistencias.editar')
            || $user->can('company.update')
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
                    ->description('Captura entrada y salida. El sistema calcula estado, retardo, salida temprana, horas trabajadas y horas extra contra el horario operativo.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('employee_id')
                                    ->label('Empleado')
                                    ->options(fn () => self::employeeOptions())
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                DatePicker::make('attendance_date')
                                    ->label('Fecha')
                                    ->native(false)
                                    ->default(now())
                                    ->required(),

                                DateTimePicker::make('clock_in_at')
                                    ->label('Entrada real')
                                    ->seconds(false)
                                    ->native(false),

                                DateTimePicker::make('clock_out_at')
                                    ->label('Salida real')
                                    ->seconds(false)
                                    ->native(false),

                                Select::make('source')
                                    ->label('Origen')
                                    ->options([
                                        'manual' => 'Manual',
                                        'clock' => 'Checador',
                                        'import' => 'Importación',
                                        'system' => 'Sistema',
                                    ])
                                    ->default('manual'),

                                Placeholder::make('calculation_hint')
                                    ->label('Cálculo automático')
                                    ->content(new HtmlString('<div class="rounded-xl border border-sky-300 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-700 dark:bg-sky-950 dark:text-sky-200">Al guardar se calcula el horario esperado, minutos trabajados, retardo, salida temprana, horas extra y estado.</div>'))
                                    ->columnSpanFull(),

                                Textarea::make('notes')
                                    ->label('Notas')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('Revisión móvil / geocerca')
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
                            ->schema([
                                Select::make('mobile_review_status')
                                    ->label('Estado revisión móvil')
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
                                    ->content(fn (?EmployeeAttendance $record): string => $record?->mobileReviewer?->name ?? '—'),

                                Placeholder::make('mobile_reviewed_at_label')
                                    ->label('Revisado el')
                                    ->content(fn (?EmployeeAttendance $record): string => $record?->mobile_reviewed_at?->format('d/m/Y H:i') ?? '—'),

                                Placeholder::make('clock_in_geo_summary')
                                    ->label('Entrada / geocerca')
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
                                    ->content('Super Admin, admin de empresa, RRHH autorizado o jefe directo del empleado.'),
                            ]),

                        Textarea::make('mobile_review_notes')
                            ->label('Notas de revisión móvil')
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
                    ->formatStateUsing(fn ($state): string => $state === null ? '—' : ((int) $state) . ' m')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                \Filament\Tables\Columns\TextColumn::make('clock_out_distance_meters')
                    ->label('Dist. salida')
                    ->formatStateUsing(fn ($state): string => $state === null ? '—' : ((int) $state) . ' m')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                \Filament\Tables\Columns\TextColumn::make('clock_in_device_guard_status')
                    ->label('Guard entrada')
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
                    ->formatStateUsing(fn (?string $state): string => $state ? substr($state, 0, 10) . '…' : '—')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                \Filament\Tables\Columns\TextColumn::make('clock_out_device_fingerprint')
                    ->label('Equipo salida')
                    ->formatStateUsing(fn (?string $state): string => $state ? substr($state, 0, 10) . '…' : '—')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('attendance_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Empleado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('workSchedule.name')
                    ->label('Horario')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_start_at')
                    ->label('Entrada esperada')
                    ->dateTime('H:i')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('clock_in_at')
                    ->label('Entrada real')
                    ->dateTime('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_end_at')
                    ->label('Salida esperada')
                    ->dateTime('H:i')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('clock_out_at')
                    ->label('Salida real')
                    ->dateTime('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('worked_hours')
                    ->label('Horas')
                    ->sortable(),

                Tables\Columns\TextColumn::make('late_minutes')
                    ->label('Retardo')
                    ->suffix(' min')
                    ->sortable(),

                Tables\Columns\TextColumn::make('early_leave_minutes')
                    ->label('Salida temp.')
                    ->suffix(' min')
                    ->sortable(),

                Tables\Columns\TextColumn::make('overtime_minutes')
                    ->label('Extra')
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

                Filter::make('with_overtime')
                    ->label('Con horas extra')
                    ->query(fn (Builder $query): Builder => $query->where('overtime_minutes', '>', 0)),
            ])
            ->recordUrl(fn (EmployeeAttendance $record): string => static::getUrl('edit', ['record' => $record]))
            ->actions([

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
                                $incident = EmployeeAttendanceIncidentSync::sync($record, auth()->id(), true);

                                if ($incident) {
                                    $generated++;
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
        return static::canManageAttendanceRecords();
    }

    public static function canDelete($record): bool
    {
        return static::canManageAttendanceRecords();
    }

    public static function canDeleteAny(): bool
    {
        return static::canManageAttendanceRecords();
    }


}
