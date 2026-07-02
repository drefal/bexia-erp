<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SatUnitCodeResource\Pages;
use App\Models\Product;
use App\Models\SatUnitCode;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SatUnitCodeResource extends Resource
{
    /**
     * BEXIA_SAT_UNIT_CODE_RESOURCE_RESPONSIVE_V5_79_85C
     *
     * Visual-only responsive classes for SatUnitCodeResource.
     */
    // sat_unit_catalog_fields_v1
    protected static bool $isScopedToTenant = false;

    protected static ?string $model = SatUnitCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Catálogos';
    protected static ?string $navigationLabel = 'Códigos unidad SAT';
    protected static ?string $modelLabel = 'Unidad SAT';
    protected static ?string $pluralModelLabel = 'Unidades SAT';
    protected static ?int $navigationSort = 21;

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
        'resources.satunitcoderesource',
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
                Forms\Components\Section::make('Unidad SAT')
                    ->extraAttributes([
                        'class' => 'bexia-suc-section bexia-suc-section-main',
                    ])
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->extraAttributes([
                                'class' => 'bexia-suc-field bexia-suc-code-field bexia-suc-compact-field',
                            ])
                            ->label('Clave')
                            ->required()
                            ->maxLength(20)
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('name')
                            ->extraAttributes([
                                'class' => 'bexia-suc-field bexia-suc-name-field bexia-suc-wide-field',
                            ])
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(5),

                        Forms\Components\TextInput::make('symbol')
                            ->extraAttributes([
                                'class' => 'bexia-suc-field bexia-suc-symbol-field bexia-suc-compact-field',
                            ])
                            ->label('Símbolo')
                            ->maxLength(30)
                            ->columnSpan(3),

                        Forms\Components\DatePicker::make('valid_from')
                            ->extraAttributes([
                                'class' => 'bexia-suc-field bexia-suc-date-field bexia-suc-valid-from-field bexia-suc-compact-field',
                            ])
                            ->label('Inicio vigencia')
                            ->columnSpan(3),

                        Forms\Components\DatePicker::make('valid_to')
                            ->extraAttributes([
                                'class' => 'bexia-suc-field bexia-suc-date-field bexia-suc-valid-to-field bexia-suc-compact-field',
                            ])
                            ->label('Fin vigencia')
                            ->columnSpan(3),

                        Forms\Components\Toggle::make('is_active')
                            ->extraAttributes([
                                'class' => 'bexia-suc-field bexia-suc-active-field bexia-suc-toggle-field',
                            ])
                            ->label('Activa')
                            ->default(true)
                            ->columnSpan(3),

                        Forms\Components\Textarea::make('description')
                            ->extraAttributes([
                                'class' => 'bexia-suc-field bexia-suc-description-field bexia-suc-wide-field bexia-suc-textarea-field',
                            ])
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('note')
                            ->extraAttributes([
                                'class' => 'bexia-suc-field bexia-suc-note-field bexia-suc-wide-field bexia-suc-textarea-field',
                            ])
                            ->label('Nota')
                            ->rows(3)
                            ->columnSpanFull(),
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
                        'class' => 'bexia-suc-header bexia-suc-col-code',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-suc-cell bexia-suc-col-code bexia-suc-col-compact',
                    ])
                    ->label('Clave')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-suc-header bexia-suc-col-name',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-suc-cell bexia-suc-col-name bexia-suc-col-wide',
                    ])
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('symbol')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-suc-header bexia-suc-col-symbol',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-suc-cell bexia-suc-col-symbol bexia-suc-col-compact',
                    ])
                    ->label('Símbolo')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('description')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-suc-header bexia-suc-col-description',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-suc-cell bexia-suc-col-description bexia-suc-col-wide',
                    ])
                    ->label('Descripción')
                    ->searchable()
                    ->wrap()
                    ->limit(80)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('valid_from')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-suc-header bexia-suc-col-valid-from',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-suc-cell bexia-suc-col-valid-from bexia-suc-col-date',
                    ])
                    ->label('Inicio')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('valid_to')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-suc-header bexia-suc-col-valid-to',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-suc-cell bexia-suc-col-valid-to bexia-suc-col-date',
                    ])
                    ->label('Fin')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-suc-header bexia-suc-col-active',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-suc-cell bexia-suc-col-active bexia-suc-col-bool',
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
            'index' => Pages\ListSatUnitCodes::route('/'),
            'create' => Pages\CreateSatUnitCode::route('/create'),
            'edit' => Pages\EditSatUnitCode::route('/{record}/edit'),
        ];
    }
}
