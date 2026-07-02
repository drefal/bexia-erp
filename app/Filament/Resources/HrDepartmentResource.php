<?php

namespace App\Filament\Resources;


use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\Concerns\UsesTenantCompany;
use App\Filament\Resources\HrDepartmentResource\Pages;
use App\Models\Employee;
use App\Models\HrDepartment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HrDepartmentResource extends Resource
{
    /**
     * BEXIA_HR_DEPARTMENT_RESOURCE_RESPONSIVE_V5_79_89C
     *
     * Visual-only responsive classes for HrDepartmentResource.
     */
    use UsesTenantCompany;

    protected static ?string $model = HrDepartment::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'RRHH';
    protected static ?string $navigationLabel = 'Departamentos';
    protected static ?string $modelLabel = 'departamento';
    protected static ?string $pluralModelLabel = 'departamentos';
    protected static ?int $navigationSort = 10;
    protected static ?string $tenantOwnershipRelationshipName = 'company';

    public static function form(Form $form): Form
    {
        return $form
            ->extraAttributes([
                'class' => 'bexia-hdp-form bexia-hdp-form-main',
            ])
            ->schema([
            Forms\Components\Section::make('Departamento')
                ->extraAttributes([
                    'class' => 'bexia-hdp-section bexia-hdp-section-main',
                ])
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->extraAttributes([
                            'class' => 'bexia-hdp-field bexia-hdp-name-field bexia-hdp-wide-field',
                        ])
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('code')
                        ->extraAttributes([
                            'class' => 'bexia-hdp-field bexia-hdp-code-field bexia-hdp-compact-field',
                        ])
                        ->label('Código')
                        ->maxLength(80),

                    Forms\Components\Select::make('parent_id')
                        ->extraAttributes([
                            'class' => 'bexia-hdp-field bexia-hdp-parent-field bexia-hdp-select-field',
                        ])
                        ->label('Departamento padre')
                        ->options(fn () => HrDepartment::query()
                            ->where('company_id', static::currentCompanyId())
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray())
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\Select::make('manager_employee_id')
                        ->extraAttributes([
                            'class' => 'bexia-hdp-field bexia-hdp-manager-field bexia-hdp-select-field',
                        ])
                        ->label('Responsable')
                        ->options(fn () => Employee::query()
                            ->where('company_id', static::currentCompanyId())
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray())
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\Textarea::make('description')
                        ->extraAttributes([
                            'class' => 'bexia-hdp-field bexia-hdp-description-field bexia-hdp-wide-field',
                        ])
                        ->label('Descripción')
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('is_active')
                        ->extraAttributes([
                            'class' => 'bexia-hdp-field bexia-hdp-active-field bexia-hdp-toggle-field',
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
                        'class' => 'bexia-hdp-header bexia-hdp-col-name',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hdp-cell bexia-hdp-col-name bexia-hdp-col-wide',
                    ])->label('Nombre')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hdp-header bexia-hdp-col-code',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hdp-cell bexia-hdp-col-code bexia-hdp-col-compact',
                    ])->label('Código')->searchable(),
                Tables\Columns\TextColumn::make('parent.name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hdp-header bexia-hdp-col-parent',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hdp-cell bexia-hdp-col-parent bexia-hdp-col-relation',
                    ])->label('Padre')->toggleable(),
                Tables\Columns\TextColumn::make('manager.name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hdp-header bexia-hdp-col-manager',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hdp-cell bexia-hdp-col-manager bexia-hdp-col-relation',
                    ])->label('Responsable')->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hdp-header bexia-hdp-col-active',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hdp-cell bexia-hdp-col-active bexia-hdp-col-bool',
                    ])->label('Activo')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hdp-header bexia-hdp-col-updated',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hdp-cell bexia-hdp-col-updated bexia-hdp-col-date',
                    ])->label('Actualizado')->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListHrDepartments::route('/'),
            'create' => Pages\CreateHrDepartment::route('/create'),
            'edit' => Pages\EditHrDepartment::route('/{record}/edit'),
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
