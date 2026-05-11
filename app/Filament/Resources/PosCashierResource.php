<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PosCashierResource\Pages;
use App\Models\PosCashier;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PosCashierResource extends Resource
{
    protected static ?string $model = PosCashier::class;

    protected static ?string $navigationGroup = 'Punto de Venta';

    protected static ?string $navigationLabel = 'Cajeros PDV';

    protected static ?string $modelLabel = 'cajero PDV';

    protected static ?string $pluralModelLabel = 'cajeros PDV';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 30;

    protected static bool $isScopedToTenant = false;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('pos_cashiers', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Cajero')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->options(fn () => static::optionsFromTable('companies', ['name']))
                            ->default(fn () => static::currentCompanyId())
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('pos_point_id')
                            ->label('Punto de venta')
                            ->options(fn () => static::optionsFromTable('pos_points', ['name', 'code']))
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('user_id')
                            ->label('Usuario relacionado')
                            ->options(fn () => static::optionsFromTable('users', ['name', 'email']))
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del cajero')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->maxLength(80),

                        Forms\Components\TextInput::make('plain_pin')
                            ->label('PIN / clave')
                            ->password()
                            ->revealable()
                            ->helperText('Déjalo vacío para entrada directa o para conservar la clave actual.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                    ]),

                Forms\Components\Section::make('Permisos del cajero')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Toggle::make('can_discount')
                            ->label('Puede aplicar descuentos')
                            ->default(true),

                        Forms\Components\Toggle::make('can_cancel')
                            ->label('Puede cancelar ventas')
                            ->default(true),

                        Forms\Components\Toggle::make('can_open_cash_drawer')
                            ->label('Puede abrir cajón')
                            ->default(false),

                        Forms\Components\TextInput::make('max_discount_percent')
                            ->label('Descuento máximo %')
                            ->numeric()
                            ->default(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Cajero')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('pos_point_id')
                    ->label('Punto de venta')
                    ->state(fn ($record): string => static::labelFromTable('pos_points', $record->pos_point_id, ['name', 'code'])),

                Tables\Columns\IconColumn::make('pin_hash')
                    ->label('Clave')
                    ->boolean()
                    ->state(fn ($record): bool => ! empty($record->pin_hash)),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosCashiers::route('/'),
            'create' => Pages\CreatePosCashier::route('/create'),
            'edit' => Pages\EditPosCashier::route('/{record}/edit'),
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
            //
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

    protected static function optionsFromTable(string $table, array $labelColumns): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $query = DB::table($table);

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn($table, 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->mapWithKeys(function ($row) use ($labelColumns) {
                $parts = [];

                foreach ($labelColumns as $column) {
                    if (isset($row->{$column}) && trim((string) $row->{$column}) !== '') {
                        $parts[] = trim((string) $row->{$column});
                    }
                }

                return [$row->id => $parts ? implode(' - ', $parts) : ('#' . $row->id)];
            })
            ->all();
    }

    protected static function labelFromTable(string $table, mixed $id, array $labelColumns): string
    {
        if (! $id || ! Schema::hasTable($table)) {
            return '—';
        }

        $row = DB::table($table)->where('id', $id)->first();

        if (! $row) {
            return '—';
        }

        $parts = [];

        foreach ($labelColumns as $column) {
            if (isset($row->{$column}) && trim((string) $row->{$column}) !== '') {
                $parts[] = trim((string) $row->{$column});
            }
        }

        return $parts ? implode(' - ', $parts) : ('#' . $id);
    }
}
