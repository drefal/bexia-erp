<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceTechnicianResource\Pages;
use App\Models\Employee;
use App\Support\Service\ServiceAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ServiceTechnicianResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationGroup = 'Atencion y Servicio';

    protected static ?string $navigationLabel = 'Tecnicos activos';

    protected static ?string $modelLabel = 'tecnico de servicio';

    protected static ?string $pluralModelLabel = 'tecnicos activos';

    protected static ?int $navigationSort = 30;

    protected static ?string $tenantOwnershipRelationshipName = null;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return ServiceAccess::can([
            'service.repairs.update',
            'service.cases.update',
            'service.menu.view',
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return ServiceAccess::can([
            'service.repairs.update',
            'service.cases.update',
        ]);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = Employee::query();

        $companyIds = ServiceAccess::companyGroupCompanyIds();

        if ($companyIds !== [] && ServiceAccess::hasColumn('employees', 'company_id')) {
            $query->whereIn('company_id', $companyIds);
        }

        if (ServiceAccess::hasColumn('employees', 'is_service_technician')) {
            $query->where('is_service_technician', true);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Empleado')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Placeholder::make('employee_label')
                            ->label('Empleado')
                            ->content(fn (?Employee $record): string => $record ? (ServiceAccess::employeeLabel((int) $record->getKey()) ?? ('#' . $record->getKey())) : 'Empleado'),

                        Forms\Components\TextInput::make('company_id')
                            ->label('Empresa')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Toggle::make('is_service_technician')
                            ->label('Es tecnico de servicio')
                            ->helperText('Si esta activo, puede seleccionarse como tecnico responsable en tickets y reparaciones.')
                            ->inline(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('employee_number')
                    ->label('No. empleado')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('first_name')
                    ->label('Nombre(s)')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('last_name')
                    ->label('Apellidos')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_service_technician')
                    ->label('Tecnico')
                    ->boolean(),

                Tables\Columns\TextColumn::make('company_id')
                    ->label('Empresa')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_service_technician')
                    ->label('Tecnico de servicio')
                    ->trueLabel('Solo tecnicos')
                    ->falseLabel('No tecnicos')
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Configurar')
                    ->visible(fn (Employee $record): bool => static::canEdit($record)),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceTechnicians::route('/'),
            'edit' => Pages\EditServiceTechnician::route('/{record}/edit'),
        ];
    }
}
