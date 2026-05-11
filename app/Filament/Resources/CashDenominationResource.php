<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashDenominationResource\Pages;
use App\Models\CashDenomination;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CashDenominationResource extends Resource
{
    protected static ?string $model = CashDenomination::class;

    protected static ?string $navigationGroup = 'Catálogos de facturación';

    protected static ?string $navigationLabel = 'Denominaciones';

    protected static ?string $modelLabel = 'denominación';

    protected static ?string $pluralModelLabel = 'denominaciones';

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?int $navigationSort = 32;

    protected static bool $isScopedToTenant = false;

    public static function shouldRegisterNavigation(): bool
    {
        return PaymentFormResource::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('cash_denominations', 'company_id')) {
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Denominación')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('company_id')
                        ->label('Empresa')
                        ->options(fn () => static::companyOptions())
                        ->default(fn () => static::currentCompanyId())
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('currency_id')
                        ->label('Moneda')
                        ->options(fn () => static::currencyOptions())
                        ->searchable()
                        ->preload(),

                    Forms\Components\TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Billete de 500'),

                    Forms\Components\TextInput::make('value')
                        ->label('Valor')
                        ->required()
                        ->numeric(),

                    Forms\Components\Select::make('type')
                        ->label('Tipo')
                        ->options([
                            'coin' => 'Moneda',
                            'bill' => 'Billete',
                        ])
                        ->default('bill')
                        ->required(),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('Orden')
                        ->numeric()
                        ->default(10),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Activa')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('value')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Denominación')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('value')
                    ->label('Valor')
                    ->formatStateUsing(fn ($state): string => '$' . number_format((float) $state, 2, '.', ','))
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')->label('Tipo')->formatStateUsing(fn ($state) => $state === 'coin' ? 'Moneda' : 'Billete'),
                Tables\Columns\TextColumn::make('currency_id')->label('Moneda')->state(fn ($record) => static::currencyLabel($record->currency_id)),
                Tables\Columns\IconColumn::make('is_active')->label('Activa')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Editar'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashDenominations::route('/'),
            'create' => Pages\CreateCashDenomination::route('/create'),
            'edit' => Pages\EditCashDenomination::route('/{record}/edit'),
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

    protected static function currencyOptions(): array
    {
        if (! Schema::hasTable('currencies')) {
            return [];
        }

        return DB::table('currencies')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->id => $row->code . ' - ' . $row->name])
            ->all();
    }

    protected static function currencyLabel($id): string
    {
        if (! $id || ! Schema::hasTable('currencies')) {
            return '—';
        }

        $row = DB::table('currencies')->where('id', $id)->first();

        return $row ? ($row->code . ' - ' . $row->name) : '—';
    }
}
