<?php

namespace App\Filament\Resources;


use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\Concerns\UsesTenantCompany;
use App\Filament\Resources\HrDocumentTypeResource\Pages;
use App\Models\HrDocumentType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HrDocumentTypeResource extends Resource
{
    /**
     * BEXIA_HR_DOCUMENT_TYPE_RESOURCE_RESPONSIVE_V5_79_93C
     *
     * Visual-only responsive classes for HrDocumentTypeResource.
     */
    use UsesTenantCompany;

    protected static ?string $model = HrDocumentType::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'RRHH';
    protected static ?string $navigationLabel = 'Tipos de documento';
    protected static ?string $modelLabel = 'tipo de documento';
    protected static ?string $pluralModelLabel = 'tipos de documento';
    protected static ?int $navigationSort = 30;
    protected static ?string $tenantOwnershipRelationshipName = 'company';

    public static function form(Form $form): Form
    {
        return $form
            ->extraAttributes([
                'class' => 'bexia-hdt-form bexia-hdt-form-main bexia-hdt-shell',
            ])
            ->schema([
            Forms\Components\Section::make('Tipo de documento')
                ->extraAttributes([
                    'class' => 'bexia-hdt-section bexia-hdt-section-main',
                ])
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->extraAttributes([
                            'class' => 'bexia-hdt-field bexia-hdt-name-field bexia-hdt-wide-field',
                        ])
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('code')
                        ->extraAttributes([
                            'class' => 'bexia-hdt-field bexia-hdt-code-field bexia-hdt-compact-field',
                        ])
                        ->label('Código')
                        ->maxLength(80),

                    Forms\Components\Toggle::make('requires_expiration_date')
                        ->extraAttributes([
                            'class' => 'bexia-hdt-field bexia-hdt-expiration-field bexia-hdt-toggle-field',
                        ])
                        ->label('Requiere vencimiento'),

                    Forms\Components\Toggle::make('is_required_by_default')
                        ->extraAttributes([
                            'class' => 'bexia-hdt-field bexia-hdt-required-field bexia-hdt-toggle-field',
                        ])
                        ->label('Requerido por default'),

                    Forms\Components\Toggle::make('is_active')
                        ->extraAttributes([
                            'class' => 'bexia-hdt-field bexia-hdt-active-field bexia-hdt-toggle-field',
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
                        'class' => 'bexia-hdt-header bexia-hdt-col-name',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hdt-cell bexia-hdt-col-name bexia-hdt-col-wide',
                    ])
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hdt-header bexia-hdt-col-code',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hdt-cell bexia-hdt-col-code bexia-hdt-col-compact',
                    ])
                    ->label('Código')
                    ->searchable(),
                Tables\Columns\IconColumn::make('requires_expiration_date')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hdt-header bexia-hdt-col-expiration',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hdt-cell bexia-hdt-col-expiration bexia-hdt-col-bool',
                    ])
                    ->label('Vence')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_required_by_default')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hdt-header bexia-hdt-col-required',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hdt-cell bexia-hdt-col-required bexia-hdt-col-bool',
                    ])
                    ->label('Requerido')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-hdt-header bexia-hdt-col-active',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-hdt-cell bexia-hdt-col-active bexia-hdt-col-bool',
                    ])
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Activo'),
                Tables\Filters\TernaryFilter::make('is_required_by_default')->label('Requerido'),
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
            'index' => Pages\ListHrDocumentTypes::route('/'),
            'create' => Pages\CreateHrDocumentType::route('/create'),
            'edit' => Pages\EditHrDocumentType::route('/{record}/edit'),
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
