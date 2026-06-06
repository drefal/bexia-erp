<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TreasuryAccountResource\Pages;
use App\Models\TreasuryAccount;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TreasuryAccountResource extends Resource
{
    protected static ?string $model = TreasuryAccount::class;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $navigationGroup = 'Tesorería';

    protected static ?string $navigationLabel = 'Cajas operativas';

    protected static ?string $modelLabel = 'Caja operativa';

    protected static ?string $pluralModelLabel = 'Cajas operativas';

    protected static ?int $navigationSort = 20;

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->can('treasury.view');
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->can('treasury.create');
    }

    public static function canEdit($record): bool
    {
        return auth()->check() && auth()->user()->can('treasury.update');
    }

    public static function canDelete($record): bool
    {
        return auth()->check() && auth()->user()->can('treasury.delete');
    }

    public static function getEloquentQuery(): Builder
    {
        $model = static::getModel();
        $query = $model::query();

        $companyId = static::currentCompanyId();

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query
            ->whereIn('cash_scope', ['pdv', 'branch_cash', 'cedis_cash', 'admin_cash', 'general_cash'])
            ->where('type', 'cash')
            ->orderBy('cash_scope')
            ->orderBy('name');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default(fn (): ?int => static::currentCompanyId()),

                Forms\Components\Section::make('Configuración operativa')
                    ->description('Define si esta caja pertenece a un PDV, sucursal/tienda, bodega/CEDIS, administración o empresa. Las cuentas bancarias se administran en Tesoreria > Cuentas bancarias.')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la caja')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('cash_scope')
                            ->label('Uso operativo')
                            ->required()
                            ->reactive()
                            ->options(static::cashScopeOptions())
                            ->default('general_cash')
                            ->helperText('Este campo determina cómo se usará la caja en retiros, traspasos y dashboard.'),

                        Forms\Components\Select::make('type')
                            ->label('Tipo contable')
                            ->required()
                            ->options([
                                'cash' => 'Efectivo / Caja',
                                'bank' => 'Banco',
                            ])
                            ->default('cash'),

                        Forms\Components\Select::make('branch_id')
                            ->label('Sucursal / tienda')
                            ->options(fn (): array => static::branchOptions())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => in_array($get('cash_scope'), ['branch_cash', 'pdv'], true))
                            ->helperText('Para Caja sucursal debe indicar la tienda/sucursal. En Caja PDV ayuda a validar su ubicación.'),

                        Forms\Components\Select::make('warehouse_id')
                            ->label('Almacén del PDV')
                            ->options(fn (): array => static::warehouseOptions())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('cash_scope') === 'pdv')
                            ->helperText('Solo aplica a Caja PDV si se desea reflejar su almacén operativo.'),

                        Forms\Components\Select::make('pos_point_id')
                            ->label('Punto de venta asociado')
                            ->options(fn (): array => static::posPointOptions())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('cash_scope') === 'pdv')
                            ->helperText('Cada PDV debe tener una Caja PDV para retiros.'),

                        Forms\Components\Select::make('bank_id')
                            ->label('Banco')
                            ->options(fn (): array => static::bankOptions())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('cash_scope') === 'bank' || $get('type') === 'bank'),

                        Forms\Components\TextInput::make('account_number')
                            ->label('Número de cuenta')
                            ->maxLength(100)
                            ->visible(fn (Get $get): bool => $get('cash_scope') === 'bank' || $get('type') === 'bank'),

                        Forms\Components\TextInput::make('clabe')
                            ->label('CLABE')
                            ->maxLength(100)
                            ->visible(fn (Get $get): bool => $get('cash_scope') === 'bank' || $get('type') === 'bank'),

                        Forms\Components\Select::make('parent_treasury_account_id')
                            ->label('Caja superior / agrupadora')
                            ->options(fn (): array => static::accountOptions())
                            ->searchable()
                            ->preload()
                            ->helperText('Opcional. Sirve para agrupar cajas operativas bajo una caja principal.'),

                        Forms\Components\TextInput::make('currency_code')
                            ->label('Moneda')
                            ->default('MXN')
                            ->required()
                            ->maxLength(10),

                        Forms\Components\Toggle::make('requires_approval')
                            ->label('Requiere aprobación')
                            ->default(true)
                            ->helperText('Recomendado para sucursal, administración, bodega/CEDIS y traspasos.'),

                        Forms\Components\Toggle::make('is_default_concentrator')
                            ->label('Concentradora por defecto de la empresa')
                            ->default(false)
                            ->helperText('Solo debe existir una por empresa. Si activas esta, se desactivan las demas.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true),
                    ]),

                Forms\Components\Section::make('Saldos')
                    ->description('El saldo actual se actualiza por movimientos de Tesorería. No debe modificarse manualmente en operación normal.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('opening_balance')
                            ->label('Saldo inicial')
                            ->numeric()
                            ->default(0)
                            ->prefix('$')
                            ->step('0.01'),

                        Forms\Components\TextInput::make('current_balance')
                            ->label('Saldo actual')
                            ->numeric()
                            ->prefix('$')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Calculado por movimientos.'),
                    ]),

                Forms\Components\Section::make('Notas')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3)
                            ->maxLength(3000),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Caja')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('company_id')
                    ->label('Empresa')
                    ->formatStateUsing(fn ($state): string => static::companyLabel($state))
                    ->toggleable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cash_scope')
                    ->label('Uso')
                    ->formatStateUsing(fn (?string $state): string => static::cashScopeLabel($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch_id')
                    ->label('Sucursal')
                    ->formatStateUsing(fn ($state): string => static::branchLabel($state))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('warehouse_id')
                    ->label('Almacén del PDV')
                    ->formatStateUsing(fn ($state): string => static::warehouseLabel($state))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('pos_point_id')
                    ->label('PDV asociado')
                    ->formatStateUsing(fn ($state): string => static::posPointLabel($state))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('current_balance')
                    ->label('Saldo')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\IconColumn::make('requires_approval')
                    ->label('Aprueba')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_default_concentrator')
                    ->label('Concentradora')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_default_concentrator')
                    ->label('Concentradora'),
                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Empresa')
                    ->options(fn (): array => static::companyOptions()),

                Tables\Filters\SelectFilter::make('cash_scope')
                    ->label('Uso operativo')
                    ->options(static::cashScopeOptions()),

                Tables\Filters\SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->options(fn (): array => static::branchOptions()),

                Tables\Filters\SelectFilter::make('pos_point_id')
                    ->label('PDV asociado')
                    ->options(fn (): array => static::posPointOptions()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTreasuryAccounts::route('/'),
            'create' => Pages\CreateTreasuryAccount::route('/create'),
            'edit' => Pages\EditTreasuryAccount::route('/{record}/edit'),
        ];
    }

    public static function currentCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        return $tenant?->id ? (int) $tenant->id : null;
    }

    public static function sanitizeAccountData(array $data): array
    {
        $data['company_id'] = static::currentCompanyId() ?: ($data['company_id'] ?? null);

        $scope = (string) ($data['cash_scope'] ?? 'general_cash');

        if ($scope === 'bank') {
            $data['type'] = 'bank';
        } else {
            $data['type'] = 'cash';
            $data['bank_id'] = null;
            $data['account_number'] = null;
            $data['clabe'] = null;
        }

        if (! in_array($scope, ['branch_cash', 'pdv'], true)) {
            $data['branch_id'] = null;
        }

        if ($scope !== 'pdv') {
            $data['warehouse_id'] = null;
            $data['pos_point_id'] = null;
        }

        if (empty($data['currency_code'])) {
            $data['currency_code'] = 'MXN';
        }

        if (! array_key_exists('opening_balance', $data) || $data['opening_balance'] === null || $data['opening_balance'] === '') {
            $data['opening_balance'] = 0;
        }

        if (! array_key_exists('current_balance', $data) || $data['current_balance'] === null || $data['current_balance'] === '') {
            $data['current_balance'] = $data['opening_balance'] ?? 0;
        }

        $data['is_default_concentrator'] = (bool) ($data['is_default_concentrator'] ?? false);

        return $data;
    }

    public static function cashScopeOptions(): array
    {
        return [
            'pdv' => 'Caja PDV',
            'branch_cash' => 'Caja sucursal / tienda',
            'cedis_cash' => 'Caja bodega / CEDIS',
            'admin_cash' => 'Caja administración',
            'general_cash' => 'Caja general empresa',
        ];
    }

    public static function cashScopeLabel(?string $scope): string
    {
        return static::cashScopeOptions()[$scope ?: ''] ?? ($scope ?: 'Sin uso');
    }

    public static function accountOptions(): array
    {
        if (! Schema::hasTable('treasury_accounts')) {
            return [];
        }

        $companyId = static::currentCompanyId();

        $query = DB::table('treasury_accounts')
            ->where('is_active', true)
            ->orderBy('cash_scope')
            ->orderBy('name');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query
            ->get(['id', 'name', 'cash_scope'])
            ->mapWithKeys(fn ($row): array => [
                $row->id => '[' . static::cashScopeLabel($row->cash_scope ?? null) . '] ' . $row->name,
            ])
            ->toArray();
    }

    public static function setDefaultConcentrator($record): void
    {
        if (! $record || ! ($record->is_default_concentrator ?? false)) {
            return;
        }

        DB::table('treasury_accounts')
            ->where('company_id', $record->company_id)
            ->where('id', '!=', $record->id)
            ->update([
                'is_default_concentrator' => false,
                'updated_at' => now(),
            ]);
    }

    public static function companyOptions(): array
    {
        if (! Schema::hasTable('companies')) {
            return [];
        }

        return DB::table('companies')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->mapWithKeys(fn ($row): array => [
                $row->id => $row->name ?: ('Empresa #' . $row->id),
            ])
            ->toArray();
    }

    public static function companyLabel($id): string
    {
        if (! $id || ! Schema::hasTable('companies')) {
            return '-';
        }

        $row = DB::table('companies')->where('id', $id)->first();

        return $row ? ($row->name ?: ('Empresa #' . $id)) : 'Empresa #' . $id;
    }

    public static function branchOptions(): array
    {
        if (! Schema::hasTable('branches')) {
            return [];
        }

        $companyId = static::currentCompanyId();

        $query = DB::table('branches')->orderBy('name');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query
            ->get(['id', 'name', 'code'])
            ->mapWithKeys(fn ($row): array => [
                $row->id => trim(($row->code ? $row->code . ' - ' : '') . $row->name),
            ])
            ->toArray();
    }

    public static function warehouseOptions(): array
    {
        if (! Schema::hasTable('warehouses')) {
            return [];
        }

        $companyId = static::currentCompanyId();

        $query = DB::table('warehouses')->orderBy('name');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query
            ->get(['id', 'name', 'code'])
            ->mapWithKeys(fn ($row): array => [
                $row->id => trim(($row->code ? $row->code . ' - ' : '') . $row->name),
            ])
            ->toArray();
    }

    public static function posPointOptions(): array
    {
        if (! Schema::hasTable('pos_points')) {
            return [];
        }

        $companyId = static::currentCompanyId();

        $query = DB::table('pos_points')->orderBy('name');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query
            ->get(['id', 'name', 'code'])
            ->mapWithKeys(fn ($row): array => [
                $row->id => trim(($row->code ? $row->code . ' - ' : '') . $row->name),
            ])
            ->toArray();
    }

    public static function bankOptions(): array
    {
        if (! Schema::hasTable('banks')) {
            return [];
        }

        $companyId = static::currentCompanyId();

        $query = DB::table('banks')->orderBy('name');

        if ($companyId) {
            $query->where(function ($q) use ($companyId): void {
                $q->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            });
        }

        return $query
            ->limit(300)
            ->get(['id', 'name', 'code'])
            ->mapWithKeys(fn ($row): array => [
                $row->id => trim(($row->code ? $row->code . ' - ' : '') . $row->name),
            ])
            ->toArray();
    }

    public static function branchLabel($id): string
    {
        if (! $id || ! Schema::hasTable('branches')) {
            return '-';
        }

        $row = DB::table('branches')->where('id', $id)->first();

        return $row ? trim(($row->code ? $row->code . ' - ' : '') . $row->name) : 'Sucursal #' . $id;
    }

    public static function warehouseLabel($id): string
    {
        if (! $id || ! Schema::hasTable('warehouses')) {
            return '-';
        }

        $row = DB::table('warehouses')->where('id', $id)->first();

        return $row ? trim(($row->code ? $row->code . ' - ' : '') . $row->name) : 'Almacén #' . $id;
    }

    public static function posPointLabel($id): string
    {
        if (! $id || ! Schema::hasTable('pos_points')) {
            return '-';
        }

        $row = DB::table('pos_points')->where('id', $id)->first();

        return $row ? trim(($row->code ? $row->code . ' - ' : '') . $row->name) : 'PDV #' . $id;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.treasuryaccountresource',
            fn (): bool => method_exists(static::class, 'canViewAny')
                ? static::canViewAny()
                : (method_exists(static::class, 'canAccess') ? static::canAccess() : true),
        );
    }

}
