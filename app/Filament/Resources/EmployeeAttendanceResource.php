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

    public static function canCreate(): bool
    {
        return static::bexiaCanAsistenciaPermission('rrhh.asistencias.crear');
    }

    public static function canEdit(Model $record): bool
    {
        return static::bexiaCanAsistenciaPermission('rrhh.asistencias.editar');
    }

    public static function canDelete(Model $record): bool
    {
        return static::bexiaCanAsistenciaPermission('rrhh.asistencias.eliminar');
    }

    public static function canDeleteAny(): bool
    {
        return static::bexiaCanAsistenciaPermission('rrhh.asistencias.eliminar');
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
                    ->label('Origen')
                    ->badge()
                    ->toggleable(),
            ])
            ->filters([
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
            ->actions([
                Tables\Actions\Action::make('generate_incident')
                    ->label('Generar incidencia')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->requiresConfirmation()
                    ->visible(fn (EmployeeAttendance $record): bool => EmployeeAttendanceIncidentSync::isEligible($record))
                    ->action(function (EmployeeAttendance $record): void {
                        try {
                            $incident = EmployeeAttendanceIncidentSync::sync($record, auth()->id(), true);

                            if (! $incident) {
                                Notification::make()
                                    ->title('No aplica incidencia')
                                    ->body('Esta asistencia no genera Retardo o Falta.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Incidencia generada')
                                ->body('Incidencia #' . $incident->id . ' · Estado: ' . $incident->status)
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo generar la incidencia')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->label('Eliminar'),
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
}
