<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaxRateResource\Pages;
use App\Models\TaxRate;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TaxRateResource extends Resource
{
    /**
     * BEXIA_TAX_RATE_RESOURCE_RESPONSIVE_V5_79_80C
     *
     * Visual-only responsive classes for TaxRateResource.
     */
    protected static ?string $model = TaxRate::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationGroup = 'Catálogos';
    protected static ?string $navigationLabel = 'Impuestos';
    protected static ?string $modelLabel = 'Impuesto';
    protected static ?string $pluralModelLabel = 'Impuestos';
    protected static ?int $navigationSort = 60;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static function currentCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            return (int) $tenant->getKey();
        }

        $user = Filament::auth()->user();

        return $user && isset($user->company_id)
            ? (int) $user->company_id
            : null;
    }

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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $companyId = static::currentCompanyId();

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

public static function shouldRegisterNavigation(): bool
{
    return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
        'resources.taxrateresource',
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
                Forms\Components\Section::make('Datos del impuesto')
                    ->extraAttributes([
                        'class' => 'bexia-tax-section bexia-tax-section-main',
                    ])
                    ->schema([
                        Forms\Components\Hidden::make('company_id')
                            ->default(fn (): ?int => static::currentCompanyId())
                            ->required(),

                        Forms\Components\TextInput::make('code')
                            ->extraAttributes([
                                'class' => 'bexia-tax-field bexia-tax-code-field bexia-tax-compact-field',
                            ])
                            ->label('Código')
                            ->required()
                            ->maxLength(80)
                            ->placeholder('Ej. IVA16')
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('name')
                            ->extraAttributes([
                                'class' => 'bexia-tax-field bexia-tax-name-field bexia-tax-wide-field',
                            ])
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej. IVA 16%')
                            ->columnSpan(5),

                        Forms\Components\Select::make('tax_type')
                            ->extraAttributes([
                                'class' => 'bexia-tax-field bexia-tax-type-field bexia-tax-select-field',
                            ])
                            ->label('Tipo')
                            ->options([
                                'iva' => 'IVA',
                                'ieps' => 'IEPS',
                                'isr' => 'ISR',
                                'local' => 'Impuesto local',
                                'other' => 'Otro',
                            ])
                            ->default('iva')
                            ->required()
                            ->native(false)
                            ->columnSpan(2),

                        Forms\Components\Select::make('factor_type')
                            ->extraAttributes([
                                'class' => 'bexia-tax-field bexia-tax-factor-field bexia-tax-select-field',
                            ])
                            ->label('Tipo factor')
                            ->options([
                                'tasa' => 'Tasa',
                                'cuota' => 'Cuota',
                                'exento' => 'Exento',
                            ])
                            ->default('tasa')
                            ->required()
                            ->native(false)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('rate')
                            ->extraAttributes([
                                'class' => 'bexia-tax-field bexia-tax-rate-field bexia-tax-compact-field',
                            ])
                            ->label('Tasa decimal')
                            ->helperText('Ejemplo: 0.160000 para IVA 16%, 0 para tasa 0 o exento.')
                            ->numeric()
                            ->default(0)
                            ->step('0.000001')
                            ->required()
                            ->columnSpan(3),

                        Forms\Components\Toggle::make('is_withholding')
                            ->extraAttributes([
                                'class' => 'bexia-tax-field bexia-tax-toggle-field bexia-tax-withholding-field',
                            ])
                            ->label('Retención')
                            ->default(false)
                            ->columnSpan(3),

                        Forms\Components\Toggle::make('is_active')
                            ->extraAttributes([
                                'class' => 'bexia-tax-field bexia-tax-toggle-field bexia-tax-active-field',
                            ])
                            ->label('Activo')
                            ->default(true)
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('sort_order')
                            ->extraAttributes([
                                'class' => 'bexia-tax-field bexia-tax-sort-field bexia-tax-compact-field',
                            ])
                            ->label('Orden')
                            ->numeric()
                            ->default(0)
                            ->columnSpan(3),

                        Forms\Components\Textarea::make('description')
                            ->extraAttributes([
                                'class' => 'bexia-tax-field bexia-tax-description-field bexia-tax-full-field',
                            ])
                            ->label('Descripción')
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
                        'class' => 'bexia-tax-header bexia-tax-col-code',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-tax-cell bexia-tax-col-code bexia-tax-col-compact',
                    ])
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-tax-header bexia-tax-col-name',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-tax-cell bexia-tax-col-name bexia-tax-col-wide',
                    ])
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tax_type')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-tax-header bexia-tax-col-type',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-tax-cell bexia-tax-col-type bexia-tax-col-badge',
                    ])
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'iva' => 'IVA',
                        'ieps' => 'IEPS',
                        'isr' => 'ISR',
                        'local' => 'Local',
                        'other' => 'Otro',
                        default => (string) $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('factor_type')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-tax-header bexia-tax-col-factor',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-tax-cell bexia-tax-col-factor bexia-tax-col-badge',
                    ])
                    ->label('Factor')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'tasa' => 'Tasa',
                        'cuota' => 'Cuota',
                        'exento' => 'Exento',
                        default => (string) $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('rate')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-tax-header bexia-tax-col-rate',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-tax-cell bexia-tax-col-rate bexia-tax-col-numeric',
                    ])
                    ->label('Tasa')
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state * 100, 4, '.', ''), '0'), '.') . '%')
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_withholding')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-tax-header bexia-tax-col-withholding',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-tax-cell bexia-tax-col-withholding bexia-tax-col-bool',
                    ])
                    ->label('Retención')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-tax-header bexia-tax-col-active',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-tax-cell bexia-tax-col-active bexia-tax-col-bool',
                    ])
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-tax-header bexia-tax-col-sort',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-tax-cell bexia-tax-col-sort bexia-tax-col-compact',
                    ])
                    ->label('Orden')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('tax_type')
                    ->label('Tipo')
                    ->options([
                        'iva' => 'IVA',
                        'ieps' => 'IEPS',
                        'isr' => 'ISR',
                        'local' => 'Impuesto local',
                        'other' => 'Otro',
                    ]),

                Tables\Filters\TernaryFilter::make('is_withholding')
                    ->label('Retención'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxRates::route('/'),
            'create' => Pages\CreateTaxRate::route('/create'),
            'edit' => Pages\EditTaxRate::route('/{record}/edit'),
        ];
    }
}
