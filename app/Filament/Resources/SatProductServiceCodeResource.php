<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SatProductServiceCodeResource\Pages;
use App\Models\Product;
use App\Models\SatProductServiceCode;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SatProductServiceCodeResource extends Resource
{
    /**
     * BEXIA_SAT_PRODUCT_SERVICE_CODE_RESOURCE_RESPONSIVE_V5_79_83C
     *
     * Visual-only responsive classes for SatProductServiceCodeResource.
     */
    // sat_prodserv_catalog_fields_v1
    protected static bool $isScopedToTenant = false;

    protected static ?string $model = SatProductServiceCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Catálogos';
    protected static ?string $navigationLabel = 'Claves producto/servicio SAT';
    protected static ?string $modelLabel = 'Clave producto/servicio SAT';
    protected static ?string $pluralModelLabel = 'Claves producto/servicio SAT';
    protected static ?int $navigationSort = 50;

    protected static function canManage(): bool
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

    public static function canAccess(): bool
    {
        return static::canManage();
    }

public static function shouldRegisterNavigation(): bool
{
    return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
        'resources.satproductservicecoderesource',
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
                Forms\Components\Section::make('Clave SAT')
                    ->extraAttributes([
                        'class' => 'bexia-spsc-section bexia-spsc-section-main',
                    ])
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->extraAttributes([
                                'class' => 'bexia-spsc-field bexia-spsc-code-field bexia-spsc-compact-field',
                            ])
                            ->label('Clave')
                            ->required()
                            ->maxLength(20)
                            ->columnSpan(3),

                        Forms\Components\Textarea::make('description')
                            ->extraAttributes([
                                'class' => 'bexia-spsc-field bexia-spsc-description-field bexia-spsc-wide-field',
                            ])
                            ->label('Descripción')
                            ->required()
                            ->rows(2)
                            ->columnSpan(8),

                        Forms\Components\Toggle::make('include_iva')
                            ->extraAttributes([
                                'class' => 'bexia-spsc-field bexia-spsc-tax-field bexia-spsc-iva-field bexia-spsc-toggle-field',
                            ])
                            ->label('Incluye IVA')
                            ->default(false)
                            ->columnSpan(3),

                        Forms\Components\Toggle::make('include_ieps')
                            ->extraAttributes([
                                'class' => 'bexia-spsc-field bexia-spsc-tax-field bexia-spsc-ieps-field bexia-spsc-toggle-field',
                            ])
                            ->label('Incluye IEPS')
                            ->default(false)
                            ->columnSpan(3),

                        Forms\Components\Toggle::make('border_stimulus')
                            ->extraAttributes([
                                'class' => 'bexia-spsc-field bexia-spsc-tax-field bexia-spsc-border-field bexia-spsc-toggle-field',
                            ])
                            ->label('Estímulo frontera')
                            ->default(false)
                            ->columnSpan(3),

                        Forms\Components\Toggle::make('is_active')
                            ->extraAttributes([
                                'class' => 'bexia-spsc-field bexia-spsc-active-field bexia-spsc-toggle-field',
                            ])
                            ->label('Activa')
                            ->default(true)
                            ->columnSpan(3),

                        Forms\Components\Textarea::make('required_complement')
                            ->extraAttributes([
                                'class' => 'bexia-spsc-field bexia-spsc-complement-field bexia-spsc-wide-field bexia-spsc-textarea-field',
                            ])
                            ->label('Complemento requerido')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('similar_words')
                            ->extraAttributes([
                                'class' => 'bexia-spsc-field bexia-spsc-words-field bexia-spsc-wide-field bexia-spsc-textarea-field',
                            ])
                            ->label('Palabras similares')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\DatePicker::make('valid_from')
                            ->extraAttributes([
                                'class' => 'bexia-spsc-field bexia-spsc-date-field bexia-spsc-valid-from-field bexia-spsc-compact-field',
                            ])
                            ->label('Inicio vigencia')
                            ->columnSpan(3),

                        Forms\Components\DatePicker::make('valid_to')
                            ->extraAttributes([
                                'class' => 'bexia-spsc-field bexia-spsc-date-field bexia-spsc-valid-to-field bexia-spsc-compact-field',
                            ])
                            ->label('Fin vigencia')
                            ->columnSpan(3),
                    ])
                    ->columns(12),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-spsc-header bexia-spsc-col-code',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-spsc-cell bexia-spsc-col-code bexia-spsc-col-compact',
                    ])
                    ->label('Clave')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-spsc-header bexia-spsc-col-description',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-spsc-cell bexia-spsc-col-description bexia-spsc-col-wide',
                    ])
                    ->label('Descripción')
                    ->searchable()
                    ->wrap()
                    ->limit(100)
                    ->sortable(),

                Tables\Columns\IconColumn::make('include_iva')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-spsc-header bexia-spsc-col-iva',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-spsc-cell bexia-spsc-col-iva bexia-spsc-col-bool',
                    ])
                    ->label('IVA')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('include_ieps')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-spsc-header bexia-spsc-col-ieps',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-spsc-cell bexia-spsc-col-ieps bexia-spsc-col-bool',
                    ])
                    ->label('IEPS')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('border_stimulus')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-spsc-header bexia-spsc-col-border',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-spsc-cell bexia-spsc-col-border bexia-spsc-col-bool',
                    ])
                    ->label('Frontera')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('valid_from')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-spsc-header bexia-spsc-col-valid-from',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-spsc-cell bexia-spsc-col-valid-from bexia-spsc-col-date',
                    ])
                    ->label('Inicio')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('valid_to')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-spsc-header bexia-spsc-col-valid-to',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-spsc-cell bexia-spsc-col-valid-to bexia-spsc-col-date',
                    ])
                    ->label('Fin')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-spsc-header bexia-spsc-col-active',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-spsc-cell bexia-spsc-col-active bexia-spsc-col-bool',
                    ])
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('code')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activa'),
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
            'index' => Pages\ListSatProductServiceCodes::route('/'),
            'create' => Pages\CreateSatProductServiceCode::route('/create'),
            'edit' => Pages\EditSatProductServiceCode::route('/{record}/edit'),
        ];
    }
}
