<?php

namespace App\Filament\Resources;


use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\Concerns\UsesTenantCompany;
use App\Filament\Resources\HrJobPositionResource\Pages;
use App\Models\HrDepartment;
use App\Models\HrJobPosition;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HrJobPositionResource extends Resource
{
    /**
     * BEXIA_HR_JOB_POSITION_RESOURCE_RESPONSIVE_V5_79_90C
     *
     * Visual-only responsive classes for HrJobPositionResource.
     */
    use UsesTenantCompany;

    protected static ?string $model = HrJobPosition::class;
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationGroup = 'RRHH';
    protected static ?string $navigationLabel = 'Puestos';
    protected static ?string $modelLabel = 'puesto';
    protected static ?string $pluralModelLabel = 'puestos';
    protected static ?int $navigationSort = 20;
    protected static ?string $tenantOwnershipRelationshipName = 'company';

    public static function form(Form $form): Form
    {
        return $form
            ->extraAttributes([
                'class' => 'bexia-hjp-form bexia-hjp-form-main',
            ])
            ->schema([
            Forms\Components\Section::make('Puesto')
                ->extraAttributes([
                    'class' => 'bexia-hjp-section bexia-hjp-section-main',
                ])
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->extraAttributes([
                            'class' => 'bexia-hjp-field bexia-hjp-name-field bexia-hjp-wide-field',
                        ])
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('code')
                        ->extraAttributes([
                            'class' => 'bexia-hjp-field bexia-hjp-code-field bexia-hjp-compact-field',
                        ])
                        ->label('Código')
                        ->maxLength(80),

                    Forms\Components\Select::make('department_id')
                        ->extraAttributes([
                            'class' => 'bexia-hjp-field bexia-hjp-department-field bexia-hjp-select-field',
                        ])
                        ->label('Departamento')
                        ->options(fn () => HrDepartment::query()
                            ->where('company_id', static::currentCompanyId())
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray())
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\TextInput::make('level')
                        ->extraAttributes([
                            'class' => 'bexia-hjp-field bexia-hjp-level-field bexia-hjp-compact-field',
                        ])
                        ->label('Nivel')
                        ->maxLength(80),

                    Forms\Components\Textarea::make('description')
                        ->extraAttributes([
                            'class' => 'bexia-hjp-field bexia-hjp-description-field bexia-hjp-wide-field',
                        ])
                        ->label('Descripción')
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('is_active')
                        ->extraAttributes([
                            'class' => 'bexia-hjp-field bexia-hjp-active-field bexia-hjp-toggle-field',
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
                        'class' => 'bexia-hjp-header bexia-hjp-col-name',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hjp-cell bexia-hjp-col-name bexia-hjp-col-wide',
                    ])->label('Nombre')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hjp-header bexia-hjp-col-code',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hjp-cell bexia-hjp-col-code bexia-hjp-col-compact',
                    ])->label('Código')->searchable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hjp-header bexia-hjp-col-department',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hjp-cell bexia-hjp-col-department bexia-hjp-col-relation',
                    ])->label('Departamento')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('level')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hjp-header bexia-hjp-col-level',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hjp-cell bexia-hjp-col-level bexia-hjp-col-compact',
                    ])->label('Nivel')->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hjp-header bexia-hjp-col-active',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hjp-cell bexia-hjp-col-active bexia-hjp-col-bool',
                    ])->label('Activo')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hjp-header bexia-hjp-col-updated',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hjp-cell bexia-hjp-col-updated bexia-hjp-col-date',
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
            'index' => Pages\ListHrJobPositions::route('/'),
            'create' => Pages\CreateHrJobPosition::route('/create'),
            'edit' => Pages\EditHrJobPosition::route('/{record}/edit'),
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
