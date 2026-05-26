<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Filament\Resources\EmployeeAttendanceResource;
use App\Models\EmployeeAttendance;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    protected static ?string $title = 'Asistencias';

    protected static ?string $modelLabel = 'asistencia';

    protected static ?string $pluralModelLabel = 'asistencias';

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

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return static::bexiaCanAsistenciaPermission('rrhh.asistencias.ver');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Asistencia')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('attendance_date')
                                    ->label('Fecha')
                                    ->native(false)
                                    ->default(now())
                                    ->required(),

                                Select::make('source')
                                    ->label('Origen')
                                    ->options([
                                        'manual' => 'Manual',
                                        'clock' => 'Checador',
                                        'import' => 'Importación',
                                        'system' => 'Sistema',
                                    ])
                                    ->default('manual'),

                                DateTimePicker::make('clock_in_at')
                                    ->label('Entrada real')
                                    ->seconds(false)
                                    ->native(false),

                                DateTimePicker::make('clock_out_at')
                                    ->label('Salida real')
                                    ->seconds(false)
                                    ->native(false),

                                Placeholder::make('calculation_hint')
                                    ->label('Cálculo automático')
                                    ->content(new HtmlString('<div class="rounded-xl border border-sky-300 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-700 dark:bg-sky-950 dark:text-sky-200">Al guardar se recalcula contra el horario operativo del empleado.</div>'))
                                    ->columnSpanFull(),

                                Textarea::make('notes')
                                    ->label('Notas')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('attendance_date')
            ->columns([
                Tables\Columns\TextColumn::make('attendance_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => EmployeeAttendanceResource::statusLabel($state)),

                Tables\Columns\TextColumn::make('clock_in_at')
                    ->label('Entrada')
                    ->dateTime('H:i'),

                Tables\Columns\TextColumn::make('clock_out_at')
                    ->label('Salida')
                    ->dateTime('H:i'),

                Tables\Columns\TextColumn::make('worked_hours')
                    ->label('Horas'),

                Tables\Columns\TextColumn::make('late_minutes')
                    ->label('Retardo')
                    ->suffix(' min'),

                Tables\Columns\TextColumn::make('early_leave_minutes')
                    ->label('Salida temp.')
                    ->suffix(' min'),

                Tables\Columns\TextColumn::make('overtime_minutes')
                    ->label('Extra')
                    ->suffix(' min'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Registrar asistencia')
                    ->mutateFormDataUsing(function (array $data): array {
                        $owner = $this->getOwnerRecord();

                        $data['company_id'] = $owner->company_id;
                        $data['created_by_user_id'] = auth()->id();
                        $data['updated_by_user_id'] = auth()->id();

                        return $data;
                    })
                    ->visible(fn (): bool => static::bexiaCanAsistenciaPermission('rrhh.asistencias.crear')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['updated_by_user_id'] = auth()->id();

                        return $data;
                    })
                    ->visible(fn (): bool => static::bexiaCanAsistenciaPermission('rrhh.asistencias.editar')),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn (): bool => static::bexiaCanAsistenciaPermission('rrhh.asistencias.eliminar')),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Eliminar seleccionadas')
                    ->visible(fn (): bool => static::bexiaCanAsistenciaPermission('rrhh.asistencias.eliminar')),
            ])
            ->defaultSort('attendance_date', 'desc');
    }
}
