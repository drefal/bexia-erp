<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TreasuryAccountResource\Pages;
use App\Models\AccountingAccount;
use App\Models\Bank;
use App\Models\TreasuryAccount;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TreasuryAccountResource extends Resource
{
    protected static ?string $navigationLabel = 'Cuentas / Cajas';
    protected static ?string $model = TreasuryAccount::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $tenantRelationshipName = 'treasuryAccounts';

    protected static ?string $navigationGroup = 'Tesorería';

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $modelLabel = 'cuenta / caja';

    protected static ?string $pluralModelLabel = 'cuentas / cajas';

    protected static ?int $navigationSort = 20;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('treasury.view');
    }

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

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('company_id')
                ->default(fn (): ?int => static::companyId()),

            Forms\Components\Select::make('type')
                ->label('Tipo')
                ->options([
                    'bank' => 'Banco',
                    'cash' => 'Caja',
                ])
                ->required()
                ->live()
                ->default('bank'),

            Forms\Components\Select::make('bank_id')
                ->label('Banco')
                ->options(fn (): array => Bank::query()
                    ->where('company_id', static::companyId())
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->preload()
                ->visible(fn (Forms\Get $get): bool => $get('type') === 'bank'),

            Forms\Components\TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('account_number')
                ->label('Número de cuenta')
                ->maxLength(100),

            Forms\Components\TextInput::make('clabe')
                ->label('CLABE')
                ->maxLength(32),

            Forms\Components\TextInput::make('currency_code')
                ->label('Moneda')
                ->default('MXN')
                ->required()
                ->maxLength(3),

            Forms\Components\TextInput::make('opening_balance')
                ->label('Saldo inicial')
                ->numeric()
                ->prefix('$')
                ->inputMode('decimal')
                ->step('0.01')
                ->default(0),

            Forms\Components\TextInput::make('current_balance')
                ->label('Saldo actual')
                ->numeric()
                ->prefix('$')
                ->inputMode('decimal')
                ->step('0.01')
                ->default(0),

            Forms\Components\Select::make('accounting_account_id')
                ->label('Cuenta contable relacionada')
                ->options(fn (): array => class_exists(AccountingAccount::class)
                    ? AccountingAccount::query()
                        ->where('company_id', static::companyId())
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn ($account) => [$account->id => "{$account->code} - {$account->name}"])
                        ->all()
                    : [])
                ->searchable()
                ->preload(),

            Forms\Components\Toggle::make('is_active')
                ->label('Activa')
                ->default(true),

            Forms\Components\Textarea::make('notes')
                ->label('Notas')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('company_id', static::companyId()))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Cuenta / Caja')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'bank' => 'Banco',
                        'cash' => 'Caja',
                        default => (string) $state,
                    })
                    ->badge(),

                Tables\Columns\TextColumn::make('bank.name')
                    ->label('Banco')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('currency_code')
                    ->label('Moneda'),

                Tables\Columns\TextColumn::make('current_balance')
                    ->label('Saldo')
                    // BEXIA_V5524B6_TREASURY_MONEY_FORMAT_ACCOUNT_BALANCE
                    ->formatStateUsing(fn ($state, $record): string => self::moneyLabel($state, $record?->currency_code)),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTreasuryAccounts::route('/'),
            'create' => Pages\CreateTreasuryAccount::route('/create'),
            'edit' => Pages\EditTreasuryAccount::route('/{record}/edit'),
        ];
    }

    public static function companyId(): ?int
    {
        return Filament::getTenant()?->id
            ?? auth()->user()?->company_id
            ?? null;
    }

    public static function moneyLabel($amount, ?string $currencyCode = 'MXN'): string
    {
        /*
         * BEXIA_V5524B6_TREASURY_MONEY_HELPER
         * Formato operativo: $1,500.00
         */
        $value = is_numeric($amount) ? (float) $amount : 0.0;
        $formatted = '$'.number_format($value, 2, '.', ',');

        $currencyCode = strtoupper((string) ($currencyCode ?: 'MXN'));

        return $currencyCode !== 'MXN'
            ? "{$formatted} {$currencyCode}"
            : $formatted;
    }


}
