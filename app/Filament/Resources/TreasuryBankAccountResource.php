<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TreasuryBankAccountResource\Pages;
use App\Models\TreasuryAccount;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TreasuryBankAccountResource extends Resource
{
    protected static ?string $model = TreasuryAccount::class;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = 'Tesorería';

    protected static ?string $navigationLabel = 'Cuentas bancarias';

    protected static ?string $modelLabel = 'Cuenta bancaria';

    protected static ?string $pluralModelLabel = 'Cuentas bancarias';

    protected static ?int $navigationSort = 21;

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
            ->where(function (Builder $query): void {
                $query->where('type', 'bank')
                    ->orWhere('cash_scope', 'bank')
                    ->orWhereNotNull('bank_id')
                    ->orWhereNotNull('account_number')
                    ->orWhereNotNull('clabe');
            })
            ->orderBy('name');
    }

    /*
     * BEXIA_TBAC_RESOURCE_RESPONSIVE_V5_79_60C
     * Visual-only responsive marker.
     */


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default(fn (): ?int => static::currentCompanyId()),

                Forms\Components\Section::make('Cuenta bancaria')
                    ->extraAttributes(['class' => 'bexia-tbac-section bexia-tbac-section-main'])
                    ->description('Administra cuentas bancarias separadas de las cajas operativas.')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->extraAttributes(['class' => 'bexia-tbac-field bexia-tbac-field-name'])
                            ->label('Nombre de la cuenta')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('bank_id')
                            ->extraAttributes(['class' => 'bexia-tbac-field bexia-tbac-field-bank bexia-tbac-select'])
                            ->label('Banco')
                            ->options(fn (): array => static::bankOptions())
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('currency_code')
                            ->extraAttributes(['class' => 'bexia-tbac-field bexia-tbac-field-currency'])
                            ->label('Moneda')
                            ->default('MXN')
                            ->required()
                            ->maxLength(10),

                        Forms\Components\TextInput::make('account_number')
                            ->extraAttributes(['class' => 'bexia-tbac-field bexia-tbac-field-account-number bexia-tbac-code-field'])
                            ->label('Número de cuenta')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('clabe')
                            ->extraAttributes(['class' => 'bexia-tbac-field bexia-tbac-field-clabe bexia-tbac-code-field'])
                            ->label('CLABE')
                            ->maxLength(100),

                        Forms\Components\Toggle::make('is_default_concentrator')
                            ->extraAttributes(['class' => 'bexia-tbac-field bexia-tbac-field-default-concentrator bexia-tbac-toggle'])
                            ->label('Concentradora por defecto de la empresa')
                            ->default(false)
                            ->helperText('Solo debe existir una por empresa. Si activas esta, se desactivan las demas.'),

                        Forms\Components\Toggle::make('is_active')
                            ->extraAttributes(['class' => 'bexia-tbac-field bexia-tbac-field-active bexia-tbac-toggle'])
                            ->label('Activa')
                            ->default(true),
                    ]),

                Forms\Components\Section::make('Saldos')
                    ->extraAttributes(['class' => 'bexia-tbac-section bexia-tbac-section-balances'])
                    ->description('El saldo actual se actualiza por movimientos de Tesorería.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('opening_balance')
                            ->extraAttributes(['class' => 'bexia-tbac-field bexia-tbac-field-opening-balance bexia-tbac-money-field'])
                            ->label('Saldo inicial')
                            ->numeric()
                            ->default(0)
                            ->prefix('$')
                            ->step('0.01'),

                        Forms\Components\TextInput::make('current_balance')
                            ->extraAttributes(['class' => 'bexia-tbac-field bexia-tbac-field-current-balance bexia-tbac-money-field'])
                            ->label('Saldo actual')
                            ->numeric()
                            ->prefix('$')
                            ->disabled()
                            ->dehydrated(false),
                    ]),

                Forms\Components\Section::make('Notas')
                    ->extraAttributes(['class' => 'bexia-tbac-section bexia-tbac-section-notes'])
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->extraAttributes(['class' => 'bexia-tbac-field bexia-tbac-field-notes'])
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
                    ->extraHeaderAttributes(['class' => 'bexia-tbac-col-name bexia-tbac-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-tbac-col-name bexia-tbac-col-primary'])
                    ->label('Cuenta bancaria')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('company_id')
                    ->extraHeaderAttributes(['class' => 'bexia-tbac-col-company bexia-tbac-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-tbac-col-company bexia-tbac-col-context'])
                    ->label('Empresa')
                    ->formatStateUsing(fn ($state): string => static::companyLabel($state))
                    ->toggleable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('bank_id')
                    ->extraHeaderAttributes(['class' => 'bexia-tbac-col-bank bexia-tbac-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-tbac-col-bank bexia-tbac-col-context'])
                    ->label('Banco')
                    ->formatStateUsing(fn ($state): string => static::bankLabel($state))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('account_number')
                    ->extraHeaderAttributes(['class' => 'bexia-tbac-col-account-number bexia-tbac-col-code'])
                    ->extraCellAttributes(['class' => 'bexia-tbac-col-account-number bexia-tbac-col-code'])
                    ->label('Cuenta')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('clabe')
                    ->extraHeaderAttributes(['class' => 'bexia-tbac-col-clabe bexia-tbac-col-code'])
                    ->extraCellAttributes(['class' => 'bexia-tbac-col-clabe bexia-tbac-col-code'])
                    ->label('CLABE')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('current_balance')
                    ->extraHeaderAttributes(['class' => 'bexia-tbac-col-current-balance bexia-tbac-col-money'])
                    ->extraCellAttributes(['class' => 'bexia-tbac-col-current-balance bexia-tbac-col-money'])
                    ->label('Saldo')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_default_concentrator')
                    ->extraHeaderAttributes(['class' => 'bexia-tbac-col-default-concentrator bexia-tbac-col-icon'])
                    ->extraCellAttributes(['class' => 'bexia-tbac-col-default-concentrator bexia-tbac-col-icon'])
                    ->label('Concentradora')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes(['class' => 'bexia-tbac-col-active bexia-tbac-col-icon'])
                    ->extraCellAttributes(['class' => 'bexia-tbac-col-active bexia-tbac-col-icon'])
                    ->label('Activa')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_default_concentrator')
                    ->label('Concentradora'),
                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Empresa')
                    ->options(fn (): array => static::companyOptions()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activa'),
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
            'index' => Pages\ListTreasuryBankAccounts::route('/'),
            'create' => Pages\CreateTreasuryBankAccount::route('/create'),
            'edit' => Pages\EditTreasuryBankAccount::route('/{record}/edit'),
        ];
    }

    public static function currentCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        return $tenant?->id ? (int) $tenant->id : null;
    }

    public static function sanitizeBankAccountData(array $data): array
    {
        $data['company_id'] = static::currentCompanyId() ?: ($data['company_id'] ?? null);
        $data['type'] = 'bank';
        $data['cash_scope'] = 'bank';

        $data['branch_id'] = null;
        $data['warehouse_id'] = null;
        $data['pos_point_id'] = null;
        $data['parent_treasury_account_id'] = null;
        $data['requires_approval'] = false;

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

    public static function bankOptions(): array
    {
        if (! Schema::hasTable('banks')) {
            return [];
        }

        $companyId = static::currentCompanyId();

        $query = DB::table('banks')->orderBy('name');

        if ($companyId && Schema::hasColumn('banks', 'company_id')) {
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

    public static function bankLabel($id): string
    {
        if (! $id || ! Schema::hasTable('banks')) {
            return '-';
        }

        $row = DB::table('banks')->where('id', $id)->first();

        return $row ? trim(($row->code ? $row->code . ' - ' : '') . $row->name) : 'Banco #' . $id;
    }
}
