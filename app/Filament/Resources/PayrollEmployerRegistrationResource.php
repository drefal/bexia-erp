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
                        ->label('Registro patronal')
                        ->maxLength(80),

                    Forms\Components\TextInput::make('risk_class')
                        ->extraAttributes([
                            'class' => 'bexia-perg-field bexia-perg-risk-field bexia-perg-compact-field',
                        ])
                        ->label('Clase de riesgo')
                        ->maxLength(80),

                    Forms\Components\TextInput::make('state')
                        ->extraAttributes([
                            'class' => 'bexia-perg-field bexia-perg-state-field bexia-perg-compact-field',
                        ])
                        ->label('Estado')
                        ->maxLength(80),

                    Forms\Components\Toggle::make('is_active')
                        ->extraAttributes([
                            'class' => 'bexia-perg-field bexia-perg-active-field bexia-perg-toggle-field',
                        ])
                        ->label('Activo')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
                Tables\Columns\TextColumn::make('state')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-perg-header bexia-perg-col-state',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-perg-cell bexia-perg-col-state bexia-perg-col-compact',
                    ])
                    ->label('Estado')->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-perg-header bexia-perg-col-active',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-perg-cell bexia-perg-col-active bexia-perg-col-bool',
                    ])
                    ->label('Activo')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Activo'),
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
