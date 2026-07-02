<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CurrencyResource\Pages;
use App\Models\Currency;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CurrencyResource extends Resource
{
    /**
     * BEXIA_CURRENCY_RESOURCE_RESPONSIVE_V5_79_87C
     *
     * Visual-only responsive classes for CurrencyResource.
     */
    protected static ?string $model = Currency::class;

    protected static ?string $navigationGroup = 'Catálogos';

    protected static ?string $navigationLabel = 'Monedas';

    protected static ?string $modelLabel = 'moneda';

    protected static ?string $pluralModelLabel = 'monedas';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 30;

    protected static bool $isScopedToTenant = false;

public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('currencies', 'company_id')) {
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        return $query;
    }

public static function shouldRegisterNavigation(): bool
{
    return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
        'resources.currencyresource',
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
            ->extraAttributes([
                'class' => 'bexia-cur-form bexia-cur-form-main',
            ])
            ->schema([
            Forms\Components\Section::make('Moneda')
                ->extraAttributes([
                    'class' => 'bexia-cur-section bexia-cur-section-main',
                ])
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('company_id')
                        ->extraAttributes([
                            'class' => 'bexia-cur-field bexia-cur-company-field bexia-cur-wide-field',
                        ])
                        ->label('Empresa')
                        ->options(fn () => static::companyOptions())
                        ->default(fn () => static::currentCompanyId())
                        ->searchable()
                        ->preload(),

                    Forms\Components\TextInput::make('code')
                        ->extraAttributes([
                            'class' => 'bexia-cur-field bexia-cur-code-field bexia-cur-compact-field',
                        ])
                        ->label('Código')
                        ->required()
                        ->maxLength(10)
                        ->placeholder('MXN'),

                    Forms\Components\TextInput::make('name')
                        ->extraAttributes([
                            'class' => 'bexia-cur-field bexia-cur-name-field bexia-cur-wide-field',
                        ])
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Peso mexicano'),

                    Forms\Components\TextInput::make('symbol')
                        ->extraAttributes([
                            'class' => 'bexia-cur-field bexia-cur-symbol-field bexia-cur-compact-field',
                        ])
                        ->label('Símbolo')
                        ->maxLength(10)
                        ->placeholder('$'),

                    Forms\Components\TextInput::make('exchange_rate')
                        ->extraAttributes([
                            'class' => 'bexia-cur-field bexia-cur-rate-field bexia-cur-number-field',
                        ])
                        ->label('Tipo de cambio')
                        ->numeric()
                        ->default(1),

                    Forms\Components\TextInput::make('sort_order')
                        ->extraAttributes([
                            'class' => 'bexia-cur-field bexia-cur-sort-field bexia-cur-number-field',
                        ])
                        ->label('Orden')
                        ->numeric()
                        ->default(10),

                    Forms\Components\Toggle::make('is_default')
                        ->extraAttributes([
                            'class' => 'bexia-cur-field bexia-cur-default-field bexia-cur-toggle-field',
                        ])
                        ->label('Moneda predeterminada'),

                    Forms\Components\Toggle::make('is_active')
                        ->extraAttributes([
                            'class' => 'bexia-cur-field bexia-cur-active-field bexia-cur-toggle-field',
                        ])
                        ->label('Activa')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-cur-header bexia-cur-col-code',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-cur-cell bexia-cur-col-code bexia-cur-col-compact',
                    ])->label('Código')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-cur-header bexia-cur-col-name',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-cur-cell bexia-cur-col-name bexia-cur-col-wide',
                    ])->label('Moneda')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('symbol')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-cur-header bexia-cur-col-symbol',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-cur-cell bexia-cur-col-symbol bexia-cur-col-compact',
                    ])->label('Símbolo'),
                Tables\Columns\TextColumn::make('exchange_rate')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-cur-header bexia-cur-col-rate',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-cur-cell bexia-cur-col-rate bexia-cur-col-number',
                    ])
                    ->label('Tipo de cambio')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 6, '.', ',')), 
                Tables\Columns\IconColumn::make('is_default')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-cur-header bexia-cur-col-default',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-cur-cell bexia-cur-col-default bexia-cur-col-bool',
                    ])->label('Default')->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-cur-header bexia-cur-col-active',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-cur-cell bexia-cur-col-active bexia-cur-col-bool',
                    ])->label('Activa')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Editar'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCurrencies::route('/'),
            'create' => Pages\CreateCurrency::route('/create'),
            'edit' => Pages\EditCurrency::route('/{record}/edit'),
        ];
    }

    protected static function currentCompanyId(): ?int
    {
        try {
            $tenant = Filament::getTenant();

            if (is_object($tenant) && method_exists($tenant, 'getKey')) {
                return (int) $tenant->getKey();
            }

            if (is_numeric($tenant)) {
                return (int) $tenant;
            }
        } catch (\Throwable $e) {
        }

        $tenant = request()->route('tenant');

        if (is_object($tenant) && method_exists($tenant, 'getKey')) {
            return (int) $tenant->getKey();
        }

        if (is_numeric($tenant)) {
            return (int) $tenant;
        }

        return auth()->user()?->company_id ? (int) auth()->user()->company_id : null;
    }

    protected static function companyOptions(): array
    {
        if (! Schema::hasTable('companies')) {
            return [];
        }

        return DB::table('companies')->orderBy('name')->pluck('name', 'id')->all();
    }
}
