<?php

namespace App\Filament\Resources;


use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\Concerns\UsesTenantCompany;
use App\Filament\Resources\PayrollEmployerRegistrationResource\Pages;
use App\Models\PayrollEmployerRegistration;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PayrollEmployerRegistrationResource extends Resource
{
    /**
     * BEXIA_PAYROLL_EMPLOYER_REGISTRATION_RESOURCE_RESPONSIVE_V5_79_95C
     *
     * Visual-only responsive classes for PayrollEmployerRegistrationResource.
     */
    use UsesTenantCompany;

    protected static ?string $model = PayrollEmployerRegistration::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'Nómina';
    protected static ?string $navigationLabel = 'Registros patronales';
    protected static ?string $modelLabel = 'registro patronal';
    protected static ?string $pluralModelLabel = 'registros patronales';
    protected static ?int $navigationSort = 20;
    protected static ?string $tenantOwnershipRelationshipName = 'company';

    /*
     * Este catálogo conserva company_id como empresa administradora.
     * El scoping se controla mediante UsesTenantCompany y la creación asigna
     * explícitamente company_id. Evitamos que Filament intente usar una
     * relación inversa payrollEmployerRegistrations() en Company.
     */
    protected static bool $isScopedToTenant = false;

    public static function form(Form $form): Form
    {
        return $form
            ->extraAttributes([
                'class' => 'bexia-perg-form bexia-perg-form-main bexia-perg-shell',
            ])
            ->schema([
            Forms\Components\Section::make('Registro patronal')
                ->extraAttributes([
                    'class' => 'bexia-perg-section bexia-perg-section-main',
                ])
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('employer_company_id')
                        ->label('Empresa propietaria / patrón legal')
                        ->options(function (): array {
                            $currentCompanyId = static::currentCompanyId();

                            if (! $currentCompanyId) {
                                return [];
                            }

                            $currentCompany = \App\Models\Company::query()
                                ->find($currentCompanyId);

                            if (! $currentCompany) {
                                return [];
                            }

                            $query = \App\Models\Company::query();

                            if ($currentCompany->company_group_id) {
                                $query->where(
                                    'company_group_id',
                                    $currentCompany->company_group_id
                                );
                            } elseif ($currentCompany->organization_id) {
                                $query->where(
                                    'organization_id',
                                    $currentCompany->organization_id
                                );
                            } else {
                                $query->whereKey($currentCompanyId);
                            }

                            return $query
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Razón social del Grupo L7 a la que legalmente pertenece este registro patronal.'),

                    Forms\Components\TextInput::make('name')
                        ->extraAttributes([
                            'class' => 'bexia-perg-field bexia-perg-name-field bexia-perg-wide-field',
                        ])
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('registration_number')
                        ->extraAttributes([
                            'class' => 'bexia-perg-field bexia-perg-registration-field bexia-perg-wide-field',
                        ])
                        ->label('Número de registro patronal (IMSS)')
                        ->required()
                        ->maxLength(80)
                        ->helperText('Número oficial asignado por el IMSS al patrón.'),

                    Forms\Components\Select::make('risk_class')
                        ->extraAttributes([
                            'class' => 'bexia-perg-field bexia-perg-risk-field bexia-perg-compact-field',
                        ])
                        ->label('Clase de riesgo IMSS')
                        ->options([
                            'I' => 'Clase I',
                            'II' => 'Clase II',
                            'III' => 'Clase III',
                            'IV' => 'Clase IV',
                            'V' => 'Clase V',
                        ])
                        ->searchable()
                        ->native(false)
                        ->placeholder('Seleccionar clase')
                        ->helperText('Clasificación de riesgo de trabajo asignada al patrón por el IMSS.'),

                    Forms\Components\TextInput::make('risk_fraction')
                        ->label('Fracción de riesgo IMSS')
                        ->maxLength(20)
                        ->placeholder('Ej. 682')
                        ->helperText('Fracción o clave de actividad asignada al patrón por el IMSS.'),

                    Forms\Components\TextInput::make('risk_premium_percent')
                        ->label('Prima de Riesgo de Trabajo (%)')
                        ->numeric()
                        ->step('0.00001')
                        ->minValue(0)
                        ->suffix('%')
                        ->placeholder('0.50000')
                        ->helperText('Prima vigente de Riesgos de Trabajo informada al IMSS. Se captura como porcentaje, por ejemplo 0.50000.'),

                    Forms\Components\Select::make('state')
                        ->extraAttributes([
                            'class' => 'bexia-perg-field bexia-perg-state-field bexia-perg-compact-field',
                        ])
                        ->label('Estatus del registro patronal')
                        ->options([
                            'vigente' => 'Vigente',
                            'suspendido' => 'Suspendido',
                            'baja' => 'Baja',
                            'reanudacion' => 'En reanudación',
                            'pendiente_alta' => 'Pendiente de alta',
                        ])
                        ->default('vigente')
                        ->required()
                        ->native(false)
                        ->helperText('Solo los registros vigentes podrán asignarse a nuevos empleados.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employerCompany.name')
                    ->label('Empresa propietaria')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Sin asignar')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-perg-header bexia-perg-col-name',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-perg-cell bexia-perg-col-name bexia-perg-col-wide',
                    ])
                    ->label('Nombre')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('registration_number')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-perg-header bexia-perg-col-registration',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-perg-cell bexia-perg-col-registration bexia-perg-col-wide',
                    ])
                    ->label('Registro')->searchable(),
                Tables\Columns\TextColumn::make('risk_class')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-perg-header bexia-perg-col-risk',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-perg-cell bexia-perg-col-risk bexia-perg-col-compact',
                    ])
                    ->label('Riesgo')->toggleable(),
                Tables\Columns\TextColumn::make('risk_fraction')
                    ->label('Fracción')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('risk_premium_percent')
                    ->label('Prima R.T.')
                    ->formatStateUsing(
                        fn ($state): string => $state !== null
                            ? number_format((float) $state, 5) . '%'
                            : '-'
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('state')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-perg-header bexia-perg-col-state',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-perg-cell bexia-perg-col-state bexia-perg-col-compact',
                    ])
                    ->label('Estatus')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'vigente' => 'Vigente',
                        'suspendido' => 'Suspendido',
                        'baja' => 'Baja',
                        'reanudacion' => 'En reanudación',
                        'pendiente_alta' => 'Pendiente de alta',
                        default => $state ?: 'Sin definir',
                    })
                    ->badge()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('state')
                    ->label('Estatus')
                    ->options([
                        'vigente' => 'Vigente',
                        'suspendido' => 'Suspendido',
                        'baja' => 'Baja',
                        'reanudacion' => 'En reanudación',
                        'pendiente_alta' => 'Pendiente de alta',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollEmployerRegistrations::route('/'),
            'create' => Pages\CreatePayrollEmployerRegistration::route('/create'),
            'edit' => Pages\EditPayrollEmployerRegistration::route('/{record}/edit'),
        ];
    }

    /*
     * V5.64.1i-start
     * Control de permisos RRHH/Nomina.
     * Nota: superadmin puede operar estos catalogos aunque Spatie no resuelva
     * el company_id en auth()->user()->can() dentro de algunos contextos.
     */
    protected static function bexiaCanCatalogPermission(string $permission): bool
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

    public static function canViewAny(): bool
    {
        return static::bexiaCanCatalogPermission('nomina.catalogos.ver');
    }

    public static function canView(Model $record): bool
    {
        return static::bexiaCanCatalogPermission('nomina.catalogos.ver');
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanCatalogPermission('nomina.catalogos.crear');
    }

    public static function canEdit(Model $record): bool
    {
        return static::bexiaCanCatalogPermission('nomina.catalogos.editar');
    }

    public static function canDelete(Model $record): bool
    {
        return static::bexiaCanCatalogPermission('nomina.catalogos.eliminar');
    }

    public static function canDeleteAny(): bool
    {
        return static::bexiaCanCatalogPermission('nomina.catalogos.eliminar');
    }
    /*
     * V5.64.1i-end
     */

}
