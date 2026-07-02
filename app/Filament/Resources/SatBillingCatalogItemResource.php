<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SatBillingCatalogItemResource\Pages;
use App\Models\SatBillingCatalog;
use App\Models\SatBillingCatalogItem;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SatBillingCatalogItemResource extends Resource
{
    /**
     * BEXIA_SAT_BILLING_CATALOG_ITEM_RESOURCE_RESPONSIVE_V5_79_84C
     *
     * Visual-only responsive classes for SatBillingCatalogItemResource.
     */
    protected static ?string $model = SatBillingCatalogItem::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Catálogos';
    protected static ?string $navigationLabel = 'Catálogos CFDI';
    protected static ?string $modelLabel = 'Elemento CFDI';
    protected static ?string $pluralModelLabel = 'Catálogos CFDI';
    protected static ?int $navigationSort = 70;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
            return true;
        }

        if (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin()) {
            return true;
        }

        return $user->can('accounting.update') || $user->can('inventory.update');
    }

    protected static function catalogOptions(): array
    {
        return SatBillingCatalog::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'catalog_key')
            ->all();
    }

public static function shouldRegisterNavigation(): bool
{
    return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
        'resources.satbillingcatalogitemresource',
        fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
    );
}

protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('invoicing.view')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('invoicing.view')
            );
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Catálogo CFDI')
                    ->extraAttributes([
                        'class' => 'bexia-sbci-section bexia-sbci-section-main',
                    ])
                    ->schema([
                        Forms\Components\Select::make('catalog_key')
                            ->extraAttributes([
                                'class' => 'bexia-sbci-field bexia-sbci-catalog-field bexia-sbci-select-field bexia-sbci-medium-field',
                            ])
                            ->label('Catálogo')
                            ->options(fn (): array => static::catalogOptions())
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('source_sheet')
                            ->extraAttributes([
                                'class' => 'bexia-sbci-field bexia-sbci-sheet-field bexia-sbci-compact-field',
                            ])
                            ->label('Hoja origen')
                            ->maxLength(120)
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('code')
                            ->extraAttributes([
                                'class' => 'bexia-sbci-field bexia-sbci-code-field bexia-sbci-compact-field',
                            ])
                            ->label('Código')
                            ->required()
                            ->maxLength(120)
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('name')
                            ->extraAttributes([
                                'class' => 'bexia-sbci-field bexia-sbci-name-field bexia-sbci-wide-field',
                            ])
                            ->label('Nombre')
                            ->maxLength(500)
                            ->columnSpan(6),

                        Forms\Components\Textarea::make('description')
                            ->extraAttributes([
                                'class' => 'bexia-sbci-field bexia-sbci-description-field bexia-sbci-wide-field bexia-sbci-textarea-field',
                            ])
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpan(6),

                        Forms\Components\DatePicker::make('valid_from')
                            ->extraAttributes([
                                'class' => 'bexia-sbci-field bexia-sbci-date-field bexia-sbci-valid-from-field bexia-sbci-compact-field',
                            ])
                            ->label('Inicio vigencia')
                            ->columnSpan(3),

                        Forms\Components\DatePicker::make('valid_to')
                            ->extraAttributes([
                                'class' => 'bexia-sbci-field bexia-sbci-date-field bexia-sbci-valid-to-field bexia-sbci-compact-field',
                            ])
                            ->label('Fin vigencia')
                            ->columnSpan(3),

                        Forms\Components\Toggle::make('is_active')
                            ->extraAttributes([
                                'class' => 'bexia-sbci-field bexia-sbci-active-field bexia-sbci-toggle-field',
                            ])
                            ->label('Activo')
                            ->default(true)
                            ->columnSpan(3),
                    ])
                    ->columns(12),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('catalog.name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-sbci-header bexia-sbci-col-catalog',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-sbci-cell bexia-sbci-col-catalog bexia-sbci-col-medium',
                    ])
                    ->label('Catálogo')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('code')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-sbci-header bexia-sbci-col-code',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-sbci-cell bexia-sbci-col-code bexia-sbci-col-compact',
                    ])
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-sbci-header bexia-sbci-col-name',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-sbci-cell bexia-sbci-col-name bexia-sbci-col-wide',
                    ])
                    ->label('Nombre')
                    ->searchable()
                    ->limit(70)
                    ->wrap()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-sbci-header bexia-sbci-col-description',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-sbci-cell bexia-sbci-col-description bexia-sbci-col-wide',
                    ])
                    ->label('Descripción')
                    ->searchable()
                    ->limit(100)
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('source_sheet')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-sbci-header bexia-sbci-col-sheet',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-sbci-cell bexia-sbci-col-sheet bexia-sbci-col-compact',
                    ])
                    ->label('Hoja')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('valid_from')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-sbci-header bexia-sbci-col-valid-from',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-sbci-cell bexia-sbci-col-valid-from bexia-sbci-col-date',
                    ])
                    ->label('Inicio')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('valid_to')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-sbci-header bexia-sbci-col-valid-to',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-sbci-cell bexia-sbci-col-valid-to bexia-sbci-col-date',
                    ])
                    ->label('Fin')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-sbci-header bexia-sbci-col-active',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-sbci-cell bexia-sbci-col-active bexia-sbci-col-bool',
                    ])
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('catalog_key')
            ->filters([
                Tables\Filters\SelectFilter::make('catalog_key')
                    ->label('Catálogo')
                    ->options(fn (): array => static::catalogOptions())
                    ->searchable(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSatBillingCatalogItems::route('/'),
            'create' => Pages\CreateSatBillingCatalogItem::route('/create'),
            'edit' => Pages\EditSatBillingCatalogItem::route('/{record}/edit'),
        ];
    }
}
