<?php

namespace App\Filament\Resources;


use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\Concerns\UsesTenantCompany;
use App\Filament\Resources\HrIncidentTypeResource\Pages;
use App\Models\HrIncidentType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HrIncidentTypeResource extends Resource
{
    /**
     * BEXIA_HR_INCIDENT_TYPE_RESOURCE_RESPONSIVE_V5_79_88C
     *
     * Visual-only responsive classes for HrIncidentTypeResource.
     */
    use UsesTenantCompany;

    protected static ?string $model = HrIncidentType::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationGroup = 'RRHH';
    protected static ?string $navigationLabel = 'Tipos de incidencia';
    protected static ?string $modelLabel = 'tipo de incidencia';
    protected static ?string $pluralModelLabel = 'tipos de incidencia';
    protected static ?int $navigationSort = 40;
    protected static ?string $tenantOwnershipRelationshipName = 'company';

    public static function form(Form $form): Form
    {
        return $form
            ->extraAttributes([
                'class' => 'bexia-hit-form bexia-hit-form-main',
            ])
            ->schema([
            Forms\Components\Section::make('Tipo de incidencia')
                ->extraAttributes([
                    'class' => 'bexia-hit-section bexia-hit-section-main',
                ])
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->extraAttributes([
                            'class' => 'bexia-hit-field bexia-hit-name-field bexia-hit-wide-field',
                        ])
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('code')
                        ->extraAttributes([
                            'class' => 'bexia-hit-field bexia-hit-code-field bexia-hit-compact-field',
                        ])
                        ->label('Código')
                        ->maxLength(80),

                    Forms\Components\Select::make('effect')
                        ->extraAttributes([
                            'class' => 'bexia-hit-field bexia-hit-effect-field bexia-hit-select-field',
                        ])
                        ->label('Efecto')
                        ->required()
                        ->options([
                            'informational' => 'Informativo',
                            'deduction' => 'Deducción',
                            'perception' => 'Percepción',
                        ])
                        ->default('informational'),

                    Forms\Components\Toggle::make('requires_approval')
                        ->extraAttributes([
                            'class' => 'bexia-hit-field bexia-hit-approval-field bexia-hit-toggle-field',
                        ])
                        ->label('Requiere aprobación')
                        ->default(true),

                    Forms\Components\Toggle::make('affects_payroll')
                        ->extraAttributes([
                            'class' => 'bexia-hit-field bexia-hit-payroll-field bexia-hit-toggle-field',
                        ])
                        ->label('Afecta pre-nómina/nómina'),

                    Forms\Components\Toggle::make('is_active')
                        ->extraAttributes([
                            'class' => 'bexia-hit-field bexia-hit-active-field bexia-hit-toggle-field',
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
                        'class' => 'bexia-hit-header bexia-hit-col-name',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hit-cell bexia-hit-col-name bexia-hit-col-wide',
                    ])->label('Nombre')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hit-header bexia-hit-col-code',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hit-cell bexia-hit-col-code bexia-hit-col-compact',
                    ])->label('Código')->searchable(),
                Tables\Columns\TextColumn::make('effect')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hit-header bexia-hit-col-effect',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hit-cell bexia-hit-col-effect bexia-hit-col-badge',
                    ])->label('Efecto')->badge(),
                Tables\Columns\IconColumn::make('requires_approval')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hit-header bexia-hit-col-approval',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hit-cell bexia-hit-col-approval bexia-hit-col-bool',
                    ])->label('Aprobación')->boolean(),
                Tables\Columns\IconColumn::make('affects_payroll')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hit-header bexia-hit-col-payroll',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hit-cell bexia-hit-col-payroll bexia-hit-col-bool',
                    ])->label('Nómina')->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hit-header bexia-hit-col-active',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hit-cell bexia-hit-col-active bexia-hit-col-bool',
                    ])->label('Activo')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Activo'),
                Tables\Filters\TernaryFilter::make('affects_payroll')->label('Afecta nómina'),
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
            'index' => Pages\ListHrIncidentTypes::route('/'),
            'create' => Pages\CreateHrIncidentType::route('/create'),
            'edit' => Pages\EditHrIncidentType::route('/{record}/edit'),
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
        return static::bexiaCanCatalogPermission('rrhh.catalogos.ver');
    }

    public static function canView(Model $record): bool
    {
        return static::bexiaCanCatalogPermission('rrhh.catalogos.ver');
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanCatalogPermission('rrhh.catalogos.crear');
    }

    public static function canEdit(Model $record): bool
    {
        return static::bexiaCanCatalogPermission('rrhh.catalogos.editar');
    }

    public static function canDelete(Model $record): bool
    {
        return static::bexiaCanCatalogPermission('rrhh.catalogos.eliminar');
    }

    public static function canDeleteAny(): bool
    {
        return static::bexiaCanCatalogPermission('rrhh.catalogos.eliminar');
    }
    /*
     * V5.64.1i-end
     */

}
